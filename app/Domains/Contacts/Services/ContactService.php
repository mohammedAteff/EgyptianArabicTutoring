<?php

namespace App\Domains\Contacts\Services;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactService
{
    public function __construct(
        private AvailabilityService $availability,
        private DatabaseCapability $database,
    ) {}

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
            $contact = $this->resolveCanonicalContact($contact->id, DB::transactionLevel() > 0);

            if (! $contact) {
                return $this->resolveOrCreate($email, $name, $phone, $attribution);
            }

            return $this->refreshResolvedContact($contact, $name, $phone, $now);
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
            $isEmailDuplicate = $e->getCode() === '23000'
                && (($e->errorInfo[1] ?? null) === 1062)
                && str_contains($e->errorInfo[2] ?? '', 'contacts_email_unique');
            if ($isEmailDuplicate) {
                $contact = Contact::withTrashed()
                    ->where('email', $normalized)
                    ->lockForUpdate()
                    ->first();

                if ($contact) {
                    $contact = $this->resolveCanonicalContact($contact->id, DB::transactionLevel() > 0);
                    if ($contact) {
                        return $this->refreshResolvedContact($contact, $name, $phone, $now);
                    }
                }
            }

            throw $e;
        }
    }

    private function refreshResolvedContact(Contact $contact, ?string $name, ?string $phone, CarbonInterface $now): Contact
    {
        if ($contact->trashed() && $contact->merged_into_contact_id === null) {
            $contact->restore();
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

    /**
     * Manually merge duplicate contact into canonical contact safely with row locks.
     */
    public function merge(Contact $canonical, Contact $duplicate, ?int $adminId = null): void
    {
        if ($canonical->id === $duplicate->id) {
            return;
        }

        $bookingSnapshot = Booking::withTrashed()
            ->where('contact_id', $duplicate->id)
            ->orderBy('id')
            ->get(['id', 'contact_id', 'student_id', 'status', 'cancelled_at', 'start_at_utc', 'end_at_utc', 'deleted_at']);
        $snapshotSignature = $this->bookingSignature($bookingSnapshot);
        $intervals = $bookingSnapshot
            ->filter(fn (Booking $booking): bool => $this->usesCalendar($booking))
            ->map(fn (Booking $booking): array => [
                'start' => CarbonImmutable::instance($booking->start_at_utc),
                'end' => CarbonImmutable::instance($booking->end_at_utc),
            ])
            ->values()
            ->all();
        $studentIds = $bookingSnapshot->pluck('student_id')->filter()->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
        $contactIds = [(int) $canonical->id, (int) $duplicate->id];
        sort($contactIds, SORT_NUMERIC);

        $this->database->transaction(function () use ($canonical, $duplicate, $adminId, $snapshotSignature, $intervals, $studentIds, $contactIds): void {
            $calendarDates = $this->availability->acquireCalendarDateLocksForIntervals($intervals);

            $contacts = Contact::withTrashed()->whereIn('id', $contactIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lockedCanonical = $contacts->get($canonical->id);
            $lockedDuplicate = $contacts->get($duplicate->id);
            if (! $lockedCanonical || ! $lockedDuplicate || $lockedCanonical->trashed() || $lockedDuplicate->trashed()
                || $lockedCanonical->merged_into_contact_id !== null || $lockedDuplicate->merged_into_contact_id !== null) {
                throw ValidationException::withMessages(['duplicate_id' => 'Only active, unmerged contacts can be merged.']);
            }

            if ($studentIds !== []) {
                Student::withTrashed()->whereIn('id', $studentIds)->orderBy('id')->lockForUpdate()->get(['id']);
            }

            $bookings = Booking::withTrashed()
                ->where('contact_id', $duplicate->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'contact_id', 'student_id', 'status', 'cancelled_at', 'start_at_utc', 'end_at_utc', 'deleted_at']);

            if ($this->bookingSignature($bookings) !== $snapshotSignature) {
                throw ValidationException::withMessages(['duplicate_id' => 'Bookings changed while the contact merge was being prepared. Retry the merge.']);
            }

            $lockedIntervals = $bookings
                ->filter(fn (Booking $booking): bool => $this->usesCalendar($booking))
                ->map(fn (Booking $booking): array => [
                    'start' => CarbonImmutable::instance($booking->start_at_utc),
                    'end' => CarbonImmutable::instance($booking->end_at_utc),
                ])
                ->values()
                ->all();
            if (array_diff($this->availability->calendarDatesForIntervals($lockedIntervals), $calendarDates) !== []) {
                throw ValidationException::withMessages(['duplicate_id' => 'The booking calendar changed while the contact merge was being prepared. Retry the merge.']);
            }

            // A booking writer may have committed while this transaction waited for the contact lock.
            $currentBookingSignature = $this->bookingSignature(Booking::withTrashed()
                ->where('contact_id', $lockedDuplicate->id)
                ->orderBy('id')
                ->get(['id', 'contact_id', 'student_id', 'status', 'cancelled_at', 'start_at_utc', 'end_at_utc', 'deleted_at']));
            if ($currentBookingSignature !== $snapshotSignature) {
                throw ValidationException::withMessages(['duplicate_id' => 'A booking was added while the contact merge was being prepared. Retry the merge.']);
            }

            $prevData = [
                'canonical_id' => $lockedCanonical->id,
                'duplicate_id' => $lockedDuplicate->id,
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
        }, 3);
    }

    /**
     * Resolve a contact pointer chain and optionally lock its rows in deterministic ID order.
     */
    public function resolveCanonicalContact(int $contactId, bool $lock = false): ?Contact
    {
        $chain = [];
        $visited = [];
        $contact = Contact::withTrashed()->find($contactId);

        while ($contact) {
            if (isset($visited[$contact->id])) {
                throw ValidationException::withMessages(['contact' => 'The contact merge chain contains a cycle and needs administrator review.']);
            }

            $visited[$contact->id] = true;
            $chain[] = (int) $contact->id;
            if ($contact->merged_into_contact_id === null) {
                break;
            }

            $contact = Contact::withTrashed()->find($contact->merged_into_contact_id);
        }

        if (! $contact) {
            throw ValidationException::withMessages(['contact' => 'The contact merge chain is incomplete and needs administrator review.']);
        }

        if (! $lock || DB::transactionLevel() === 0) {
            return $contact;
        }

        sort($chain, SORT_NUMERIC);
        $lockedContacts = Contact::withTrashed()
            ->whereIn('id', $chain)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        if ($lockedContacts->count() !== count($chain)) {
            throw ValidationException::withMessages(['contact' => 'The contact changed while it was being resolved. Retry the request.']);
        }

        $contact = $lockedContacts->get($contactId);
        $visited = [];
        while ($contact && $contact->merged_into_contact_id !== null) {
            if (isset($visited[$contact->id])) {
                throw ValidationException::withMessages(['contact' => 'The contact merge chain contains a cycle and needs administrator review.']);
            }

            $visited[$contact->id] = true;
            $contact = $lockedContacts->get($contact->merged_into_contact_id);
            if (! $contact) {
                throw ValidationException::withMessages(['contact' => 'The contact changed while it was being resolved. Retry the request.']);
            }
        }

        return $contact;
    }

    /** @param Collection<int, Booking> $bookings
     * @return array<int, array<int, int|string|null>>
     */
    private function bookingSignature(Collection $bookings): array
    {
        return $bookings->map(fn (Booking $booking): array => [
            (int) $booking->id,
            $booking->student_id === null ? null : (int) $booking->student_id,
            (string) $booking->status,
            $booking->cancelled_at?->toISOString(),
            $booking->start_at_utc?->toISOString(),
            $booking->end_at_utc?->toISOString(),
            $booking->deleted_at?->toISOString(),
        ])->values()->all();
    }

    private function usesCalendar(Booking $booking): bool
    {
        return ! $booking->trashed()
            && in_array($booking->status, ['confirmed', 'pending', 'held'], true)
            && $booking->cancelled_at === null
            && $booking->end_at_utc !== null
            && $booking->end_at_utc->isFuture();
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
