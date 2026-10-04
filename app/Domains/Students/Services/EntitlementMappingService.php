<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Models\StudentPackageEntitlement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EntitlementMappingService
{
    /** @return array<string, mixed> */
    public function snapshot(int $packageId): array
    {
        $package = DB::table('student_packages')->where('id', $packageId)->first();
        if (! $package) {
            throw new InvalidArgumentException('Unknown purchase.');
        }
        $entries = DB::table('session_ledger_entries')->where('student_package_id', $packageId)->orderBy('id')->get();
        $bookings = DB::table('bookings')->whereIn('id', $entries->pluck('booking_id')->filter())->orderBy('id')->get(['id', 'student_id', 'session_type_id', 'start_at_utc', 'end_at_utc', 'status']);
        $facts = ['package' => (array) $package, 'entries' => $entries->map(fn ($entry): array => (array) $entry)->all(), 'bookings' => $bookings->map(fn ($booking): array => (array) $booking)->all()];

        return ['package_id' => $packageId, 'fingerprint' => hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR)), 'balance' => (int) $entries->sum('credit_change'), 'facts' => $facts];
    }

    /** @return array<string, mixed> */
    public function review(array $manifest, bool $apply = false): array
    {
        foreach (['package_id', 'fingerprint', 'offering_key', 'entitlement_code', 'reviewer', 'evidence'] as $field) {
            if (! isset($manifest[$field]) || trim((string) $manifest[$field]) === '') {
                throw new InvalidArgumentException('Missing review field: '.$field);
            }
        }
        if (! in_array($manifest['offering_key'], [...array_keys(StudentLedgerService::PRESETS), 'custom'], true)) {
            throw new InvalidArgumentException('Unknown reviewed offering.');
        }
        $type = EntitlementType::query()->where('code', $manifest['entitlement_code'])->firstOrFail();
        if (isset(StudentLedgerService::PRESETS[$manifest['offering_key']]) && StudentLedgerService::PRESETS[$manifest['offering_key']]['entitlement_code'] !== $type->code) {
            throw new InvalidArgumentException('Reviewed offering and entitlement disagree.');
        }
        $hash = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($manifest, $apply, $type, $hash): array {
            $initial = StudentPackage::findOrFail($manifest['package_id']);
            DB::table('students')->where('id', $initial->student_id)->lockForUpdate()->first();
            $bookingIds = DB::table('session_ledger_entries')->where('student_package_id', $initial->id)->pluck('booking_id')->filter();
            DB::table('bookings')->whereIn('id', $bookingIds)->orderBy('id')->lockForUpdate()->get();
            $package = StudentPackage::query()->whereKey($initial->id)->lockForUpdate()->firstOrFail();
            $entries = DB::table('session_ledger_entries')->where('student_package_id', $package->id)->orderBy('id')->lockForUpdate()->get();
            $previous = DB::table('entitlement_mapping_reviews')->where('student_package_id', $package->id)->first();
            if ($previous) {
                if ($previous->manifest_hash !== $hash) {
                    throw new InvalidArgumentException('Purchase already mapped by a different review.');
                }

                return ['package_id' => $package->id, 'state' => 'replayed', 'balance' => (int) $entries->sum('credit_change')];
            }
            $snapshot = $this->snapshot($package->id);
            if (! hash_equals($snapshot['fingerprint'], $manifest['fingerprint']) || $package->identity_state !== 'legacy_unclassified' || $entries->contains(fn ($entry): bool => $entry->student_package_entitlement_id !== null)) {
                throw new InvalidArgumentException('Review facts changed or purchase is already classified.');
            }
            $grantedQuantity = (int) $entries->where('entry_type', 'package_grant')->sum('credit_change');
            if ($grantedQuantity !== (int) $package->total_sessions_allocated || $grantedQuantity < 1 || $snapshot['balance'] < 0) {
                throw new InvalidArgumentException('Reconcile historical grants and balance before classification.');
            }
            $validityDays = $package->validity_days;
            if ($package->expiration_date === null && isset(StudentLedgerService::PRESETS[$manifest['offering_key']])) {
                if (! array_key_exists('validity_days', $manifest)
                    || ($manifest['validity_days'] !== null && (! is_int($manifest['validity_days']) || $manifest['validity_days'] < 1))) {
                    throw new InvalidArgumentException('Review null expiry terms explicitly: validity_days must be positive or null for unlimited.');
                }
                $validityDays = $manifest['validity_days'];
            }
            foreach ($entries as $entry) {
                if ((int) $entry->student_id !== (int) $package->student_id) {
                    throw new InvalidArgumentException('Historical ownership mismatch.');
                }
                if ($entry->entry_type === 'cancellation_restore') {
                    $consumption = $entries->first(fn ($candidate): bool => $candidate->booking_id === $entry->booking_id && $candidate->entry_type === 'session_consumed');
                    if (! $consumption || $entry->credit_change !== -$consumption->credit_change) {
                        throw new InvalidArgumentException('Historical restoration has no exact original debit.');
                    }
                }
                if ($entry->entry_type === 'session_consumed') {
                    $booking = DB::table('bookings')->where('id', $entry->booking_id)->first();
                    if (! $booking || (int) $booking->student_id !== (int) $package->student_id || $entry->credit_change >= 0) {
                        throw new InvalidArgumentException('Invalid historical consumption.');
                    }
                    $restore = $entries->first(fn ($candidate): bool => $candidate->booking_id === $entry->booking_id && $candidate->entry_type === 'cancellation_restore');
                    if ($restore && $restore->credit_change !== -$entry->credit_change) {
                        throw new InvalidArgumentException('Historical restoration mismatch.');
                    }
                }
            }
            $result = ['package_id' => $package->id, 'state' => $apply ? 'mapped' : 'preview', 'balance' => $snapshot['balance'], 'rows' => $entries->count(), 'fingerprint' => $snapshot['fingerprint']];
            if (! $apply) {
                return $result;
            }
            $allocation = StudentPackageEntitlement::create(['student_package_id' => $package->id, 'student_id' => $package->student_id, 'entitlement_type_id' => $type->id, 'granted_quantity' => $grantedQuantity]);
            // Reviewed classification changes provenance only, preserving append-only event facts and timestamps.
            DB::table('student_packages')->where('id', $package->id)->update(['offering_key' => $manifest['offering_key'], 'identity_state' => $manifest['offering_key'] === 'custom' ? 'custom' : 'mapped', 'validity_days' => $validityDays]);
            DB::table('session_ledger_entries')->whereIn('id', $entries->pluck('id'))->update(['student_package_entitlement_id' => $allocation->id, 'entitlement_type_id' => $type->id]);
            foreach ($entries->where('entry_type', 'session_consumed') as $entry) {
                DB::table('bookings')->where('id', $entry->booking_id)->update(['funding_mode' => 'package', 'entitlement_type_id' => $type->id, 'entitlement_code' => $type->code, 'entitlement_units' => -$entry->credit_change, 'student_package_id' => $package->id, 'student_package_entitlement_id' => $allocation->id, 'consumed_ledger_entry_id' => $entry->id]);
            }
            DB::table('entitlement_mapping_reviews')->insert(['student_package_id' => $package->id, 'manifest_hash' => $hash, 'reviewer' => $manifest['reviewer'], 'evidence' => $manifest['evidence'], 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'reviewed_at' => now('UTC')]);

            return $result;
        }, 5);
    }
}
