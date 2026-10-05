<?php

namespace App\Domains\Students\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentMergeService
{
    public function __construct(
        private AvailabilityService $availability,
        private AuditLogService $auditLogs,
        private DatabaseCapability $database,
    ) {}

    public function merge(int $primaryId, int $secondaryId, ?int $administratorId = null): Student
    {
        if ($primaryId === $secondaryId) {
            throw ValidationException::withMessages(['secondary_student_id' => 'Choose two different students.']);
        }

        $studentIds = [$primaryId, $secondaryId];
        sort($studentIds, SORT_NUMERIC);
        $bookingSnapshot = Booking::withTrashed()
            ->whereIn('student_id', $studentIds)
            ->orderBy('id')
            ->get(['id', 'student_id', 'status', 'cancelled_at', 'start_at_utc', 'end_at_utc', 'deleted_at']);
        $snapshotIds = $bookingSnapshot->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $snapshotActive = $this->activeBookingSignature($bookingSnapshot);
        $intervals = $bookingSnapshot
            ->filter(fn (Booking $booking): bool => $this->usesCalendar($booking))
            ->map(fn (Booking $booking): array => [
                'start' => CarbonImmutable::instance($booking->start_at_utc),
                'end' => CarbonImmutable::instance($booking->end_at_utc),
            ])
            ->values()
            ->all();

        return $this->database->transaction(function () use ($primaryId, $secondaryId, $studentIds, $snapshotIds, $snapshotActive, $intervals, $administratorId): Student {
            // Calendar-date mutexes are this application's canonical scheduling resource rows.
            $calendarDates = $this->availability->acquireCalendarDateLocksForIntervals($intervals);

            $students = Student::withTrashed()
                ->whereIn('id', $studentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $primary = $students->get($primaryId);
            $secondary = $students->get($secondaryId);

            if (! $primary || ! $secondary || $primary->trashed() || $secondary->trashed()
                || $primary->identity_status === 'merged' || $secondary->identity_status === 'merged') {
                throw ValidationException::withMessages(['merge' => 'Only active, unmerged student records can be merged.']);
            }

            $bookings = Booking::withTrashed()
                ->whereIn('student_id', $studentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'student_id', 'status', 'cancelled_at', 'start_at_utc', 'end_at_utc', 'deleted_at']);
            $lockedIds = $bookings->pluck('id')->map(fn ($id): int => (int) $id)->all();
            sort($snapshotIds, SORT_NUMERIC);
            if ($lockedIds !== $snapshotIds || $this->activeBookingSignature($bookings) !== $snapshotActive) {
                throw ValidationException::withMessages(['merge' => 'A booking changed while the merge was being prepared. Retry the merge.']);
            }

            $lockedIntervals = $bookings
                ->filter(fn (Booking $booking): bool => $this->usesCalendar($booking))
                ->map(fn (Booking $booking): array => [
                    'start' => CarbonImmutable::instance($booking->start_at_utc),
                    'end' => CarbonImmutable::instance($booking->end_at_utc),
                ])
                ->values()
                ->all();
            $requiredCalendarDates = $this->availability->calendarDatesForIntervals($lockedIntervals);
            if (array_diff($requiredCalendarDates, $calendarDates) !== []) {
                throw ValidationException::withMessages(['merge' => 'The booking calendar changed while the merge was being prepared. Retry the merge.']);
            }

            DB::table('student_packages')
                ->whereIn('student_id', $studentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            DB::table('student_package_entitlements')->whereIn('student_id', $studentIds)->orderBy('id')->lockForUpdate()->get(['id']);
            DB::table('payment_records')->whereIn('student_id', $studentIds)->orderBy('id')->lockForUpdate()->get(['id']);
            DB::table('payment_refunds')->whereIn('student_id', $studentIds)->orderBy('id')->lockForUpdate()->get(['id']);
            DB::table('session_ledger_entries')->whereIn('student_id', $studentIds)->orderBy('id')->lockForUpdate()->get(['id']);
            DB::table('form_submissions')->whereIn('student_id', $studentIds)->orderBy('id')->lockForUpdate()->get(['id']);
            DB::table('session_reschedules')->where('actor_type', 'student')->whereIn('actor_id', $studentIds)->orderBy('id')->lockForUpdate()->get(['id']);
            // Materials inherit canonical ownership through the unchanged Booking IDs.
            DB::table('lesson_materials')->whereIn('booking_id', $lockedIds)->orderBy('id')->lockForUpdate()->get(['id']);

            // Reassign only ownership/reference fields; amounts and historical event facts stay untouched.
            foreach ([
                'student_packages',
                'bookings',
                'payment_records',
                'payment_refunds',
                'session_ledger_entries',
                'form_submissions',
                'resource_requests',
                'student_emails',
                'student_bins',
                'homeworks',
                'learning_plans',
                'tutor_preparations',
                'resource_assignments',
                'student_error_logs',
                'teaching_tags',
                'lesson_feedback',
                'student_notifications',
            ] as $table) {
                $changes = ['student_id' => $primaryId];
                if (in_array($table, ['payment_records', 'payment_refunds', 'session_ledger_entries'], true)) {
                    $changes['created_at'] = DB::raw('created_at');
                }

                DB::table($table)->where('student_id', $secondaryId)->update($changes);
            }

            DB::table('session_reschedules')
                ->where('actor_type', 'student')
                ->where('actor_id', $secondaryId)
                ->update(['actor_id' => $primaryId, 'created_at' => DB::raw('created_at')]);

            DB::table('students')->where('merged_into_student_id', $secondaryId)->update(['merged_into_student_id' => $primaryId]);
            DB::table('students')->where('possible_duplicate_of_student_id', $secondaryId)->update(['possible_duplicate_of_student_id' => $primaryId]);
            if ((int) $primary->possible_duplicate_of_student_id === $secondaryId) {
                $primary->possible_duplicate_of_student_id = null;
                $primary->save();
            }

            DB::table('student_email_verifications')->whereIn('student_id', $studentIds)->whereNull('consumed_at')->update(['consumed_at' => now('UTC')]);
            $secondary->identity_status = 'merged';
            $secondary->merged_into_student_id = $primaryId;
            $secondary->possible_duplicate_of_student_id = null;
            $secondary->save();
            $secondary->delete();

            Cache::forget('student_auth_check_'.$primaryId);
            Cache::forget('student_auth_check_'.$secondaryId);
            $this->auditLogs->log(
                action: 'student_merged',
                entityType: Student::class,
                entityId: $primaryId,
                previousData: ['primary_student_id' => $primaryId, 'secondary_student_id' => $secondaryId],
                newData: ['merged_student_id' => $secondaryId, 'surviving_student_id' => $primaryId],
                adminId: $administratorId,
            );

            return $primary->fresh();
        }, 3);
    }

    /** @param  Collection<int, Booking>  $bookings
     * @return array<int, array{0: int, 1: string, 2: string, 3: string}>
     */
    private function activeBookingSignature($bookings): array
    {
        return $bookings
            ->filter(fn (Booking $booking): bool => $this->usesCalendar($booking))
            ->map(fn (Booking $booking): array => [
                (int) $booking->id,
                (string) $booking->status,
                $booking->start_at_utc->toISOString(),
                $booking->end_at_utc->toISOString(),
            ])
            ->values()
            ->all();
    }

    private function usesCalendar(Booking $booking): bool
    {
        return ! $booking->trashed()
            && in_array($booking->status, ['confirmed', 'pending', 'held'], true)
            && $booking->cancelled_at === null
            && $booking->end_at_utc !== null
            && $booking->end_at_utc->isFuture();
    }
}
