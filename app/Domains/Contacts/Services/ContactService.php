<?php

namespace App\Domains\Contacts\Services;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ContactService
{
    /**
     * Resolve an existing contact by normalized email or create a new one.
     *
     * @param  array<string, mixed>  $attribution
     */
    public function resolveOrCreate(
        string $email,
        ?string $name = null,
        ?string $phone = null,
        array $attribution = []
    ): Contact {
        $normalized = Contact::normalizeEmail($email);
        $now = now();

        $contact = Contact::withTrashed()->where('email', $normalized)->first();

        if ($contact) {
            // If contact was soft-deleted and not merged, restore it so active customer records are visible
            if ($contact->trashed() && $contact->merged_into_contact_id === null) {
                $contact->restore();
            }

            // If this contact was merged, follow pointer to canonical record
            while ($contact->merged_into_contact_id !== null) {
                $canonical = Contact::withTrashed()->find($contact->merged_into_contact_id);
                if (! $canonical || $canonical->id === $contact->id) {
                    break;
                }
                $contact = $canonical;
            }

            $updates = ['last_seen_at' => $now];
            if (! empty($name) && empty($contact->name)) {
                $updates['name'] = trim($name);
            }
            if (! empty($phone) && empty($contact->phone)) {
                $updates['phone'] = trim($phone);
            }

            $contact->update($updates);

            return $contact;
        }

        try {
            return Contact::create([
                'name' => $name ? trim($name) : null,
                'email' => $normalized,
                'display_email' => trim($email),
                'phone' => $phone ? trim($phone) : null,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'utm_source' => $attribution['utm_source'] ?? null,
                'utm_medium' => $attribution['utm_medium'] ?? null,
                'utm_campaign' => $attribution['utm_campaign'] ?? null,
                'utm_content' => $attribution['utm_content'] ?? null,
                'utm_term' => $attribution['utm_term'] ?? null,
            ]);
        } catch (QueryException $e) {
            // Concurrent insert race condition caught by unique index contacts_email_unique
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) {
                return $this->resolveOrCreate($email, $name, $phone, $attribution);
            }
            throw $e;
        }
    }

    /**
     * Manually merge duplicate contact into canonical contact safely with row locks.
     */
    public function merge(Contact $canonical, Contact $duplicate, ?int $adminId = null): void
    {
        if ($canonical->id === $duplicate->id) {
            return;
        }

        DB::transaction(function () use ($canonical, $duplicate, $adminId) {
            // Pessimistic lock on both contact records
            $lockedCanonical = Contact::withTrashed()->where('id', $canonical->id)->lockForUpdate()->firstOrFail();
            $lockedDuplicate = Contact::withTrashed()->where('id', $duplicate->id)->lockForUpdate()->firstOrFail();

            $prevData = [
                'canonical_id' => $lockedCanonical->id,
                'duplicate_id' => $lockedDuplicate->id,
                'duplicate_email' => $lockedDuplicate->email,
                'duplicate_bookings_count' => $lockedDuplicate->bookings()->count(),
                'duplicate_requests_count' => $lockedDuplicate->resourceRequests()->count(),
            ];

            // Re-assign bookings
            $lockedDuplicate->bookings()->update(['contact_id' => $lockedCanonical->id]);

            // Re-assign resource requests & downloads
            $lockedDuplicate->resourceRequests()->update(['contact_id' => $lockedCanonical->id]);
            $lockedDuplicate->resourceDownloads()->update(['contact_id' => $lockedCanonical->id]);

            // Field preservation:
            // 1. Phone
            if (empty($lockedCanonical->phone) && ! empty($lockedDuplicate->phone)) {
                $lockedCanonical->phone = $lockedDuplicate->phone;
            }

            // 2. Name
            if (empty($lockedCanonical->name) && ! empty($lockedDuplicate->name)) {
                $lockedCanonical->name = $lockedDuplicate->name;
            }

            // 3. Notes consolidation
            if (! empty($lockedDuplicate->notes)) {
                $combinedNotes = trim(($lockedCanonical->notes ? $lockedCanonical->notes."\n---\n" : '')."[Merged from Contact #{$lockedDuplicate->id}]: ".$lockedDuplicate->notes);
                $lockedCanonical->notes = $combinedNotes;
            }

            // 4. Earliest first seen
            if ($lockedDuplicate->first_seen_at && (! $lockedCanonical->first_seen_at || $lockedDuplicate->first_seen_at < $lockedCanonical->first_seen_at)) {
                $lockedCanonical->first_seen_at = $lockedDuplicate->first_seen_at;
            }

            // 5. Attribution fallback
            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $utm) {
                if (empty($lockedCanonical->{$utm}) && ! empty($lockedDuplicate->{$utm})) {
                    $lockedCanonical->{$utm} = $lockedDuplicate->{$utm};
                }
            }

            $lockedCanonical->save();

            // Set pointer and soft-delete duplicate
            $lockedDuplicate->update([
                'merged_into_contact_id' => $lockedCanonical->id,
            ]);
            $lockedDuplicate->delete();

            // Record audit log
            AuditLog::create([
                'administrator_id' => $adminId,
                'action' => 'contact_merged',
                'entity_type' => Contact::class,
                'entity_id' => $lockedCanonical->id,
                'previous_data' => $prevData,
                'new_data' => [
                    'canonical_id' => $lockedCanonical->id,
                    'merged_duplicate_id' => $lockedDuplicate->id,
                ],
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Find suspected duplicate contacts based on phone numbers or normalized emails.
     *
     * @return array<int, array{reason: string, value: string, contacts: Collection}>
     */
    public function findSuspectedDuplicates(): array
    {
        $groups = [];

        // 1. Phone number collisions
        $duplicatePhones = Contact::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNull('deleted_at')
            ->select('phone')
            ->groupBy('phone')
            ->havingRaw('count(*) > 1')
            ->pluck('phone');

        foreach ($duplicatePhones as $phone) {
            $contacts = Contact::query()
                ->where('phone', $phone)
                ->withCount(['bookings', 'resourceRequests'])
                ->orderBy('created_at')
                ->get();

            if ($contacts->count() > 1) {
                $groups[] = [
                    'reason' => 'Matching Phone Number',
                    'value' => (string) $phone,
                    'contacts' => $contacts,
                ];
            }
        }

        return $groups;
    }
}
