<?php

namespace App\Domains\Students\Services;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Forms\Models\FormAnswer;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentPrivacyService
{
    public function __construct(
        private AvailabilityService $availability,
        private AuditLogService $auditLogs,
        private DatabaseCapability $database,
        private LessonMaterialService $lessonMaterials,
        private TeachingRecordService $teaching,
    ) {}

    public function anonymize(int $studentId, ?int $administratorId = null): Student
    {
        $bookingSnapshot = Booking::withTrashed()
            ->where('student_id', $studentId)
            ->orderBy('id')
            ->get(['id', 'contact_id', 'status', 'cancelled_at', 'start_at_utc', 'end_at_utc', 'deleted_at']);
        $snapshotIds = $bookingSnapshot->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $snapshotSignature = $this->activeBookingSignature($bookingSnapshot);
        $snapshotContactIds = $bookingSnapshot->pluck('contact_id')->filter()->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
        $intervals = $bookingSnapshot
            ->filter(fn (Booking $booking): bool => $this->usesCalendar($booking))
            ->map(fn (Booking $booking): array => [
                'start' => CarbonImmutable::instance($booking->start_at_utc),
                'end' => CarbonImmutable::instance($booking->end_at_utc),
            ])
            ->values()
            ->all();

        return $this->database->transaction(function () use ($studentId, $administratorId, $snapshotIds, $snapshotSignature, $snapshotContactIds, $intervals): Student {
            $calendarDates = $this->availability->acquireCalendarDateLocksForIntervals($intervals);

            $contacts = Contact::withTrashed()
                ->whereIn('id', $snapshotContactIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $student = Student::withTrashed()->whereKey($studentId)->lockForUpdate()->firstOrFail();
            if ($student->trashed()) {
                throw ValidationException::withMessages(['student' => 'This student record has already been anonymized or removed.']);
            }

            $bookings = Booking::withTrashed()->where('student_id', $studentId)->orderBy('id')->lockForUpdate()->get([
                'id', 'contact_id', 'status', 'cancelled_at', 'start_at_utc', 'end_at_utc', 'deleted_at', 'notes', 'cancellation_reason',
            ]);
            $lockedIds = $bookings->pluck('id')->map(fn ($id): int => (int) $id)->all();
            if ($lockedIds !== $snapshotIds || $this->activeBookingSignature($bookings) !== $snapshotSignature) {
                throw ValidationException::withMessages(['student' => 'A booking changed while privacy erasure was being prepared. Retry the action.']);
            }

            $lockedContactIds = $bookings->pluck('contact_id')->filter()->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
            if ($lockedContactIds !== $snapshotContactIds) {
                throw ValidationException::withMessages(['student' => 'A booking contact changed while privacy erasure was being prepared. Retry the action.']);
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
                throw ValidationException::withMessages(['student' => 'The booking calendar changed while privacy erasure was being prepared. Retry the action.']);
            }

            $anonymousEmail = 'anonymized_'.$student->id.'@internal.invalid';
            $anonymousContact = null;
            foreach ($contacts as $contactId => $contact) {
                $hasSharedContact = DB::table('bookings')
                    ->where('contact_id', $contactId)
                    ->where(function ($query) use ($studentId): void {
                        $query->whereNull('student_id')->orWhere('student_id', '!=', $studentId);
                    })
                    ->exists();

                if ($hasSharedContact) {
                    if (! $anonymousContact) {
                        $anonymousContact = Contact::create([
                            'name' => 'Anonymized Student',
                            'email' => $anonymousEmail,
                            'display_email' => $anonymousEmail,
                        ]);
                    }

                    DB::table('bookings')->where('student_id', $studentId)->where('contact_id', $contactId)
                        ->update(['contact_id' => $anonymousContact->id]);

                    continue;
                }

                $contact->forceFill([
                    'name' => 'Anonymized Student',
                    'email' => 'anonymized_'.$student->id.'_contact_'.$contact->id.'@internal.invalid',
                    'display_email' => 'anonymized_'.$student->id.'_contact_'.$contact->id.'@internal.invalid',
                    'phone' => null,
                    'notes' => null,
                    'deleted_at' => null,
                ])->save();
            }

            DB::table('student_bins')->where('student_id', $studentId)->update(['title' => 'Redacted educational note', 'body' => '[redacted]', 'student_visible' => false]);
            $this->lessonMaterials->eraseForBookings($lockedIds, $administratorId);
            $this->teaching->erase($studentId);
            DB::table('staff_tasks')->where('student_id', $studentId)->update(['title' => 'Redacted student follow-up', 'description' => null, 'student_id' => null, 'status' => 'cancelled', 'completed_at' => null]);
            DB::table('student_operational_alerts')->where('student_id', $studentId)->update(['title' => 'Redacted operational alert', 'body' => '[redacted]', 'status' => 'archived']);
            DB::table('staff_recent_views')->where('entity_type', 'student')->where('entity_id', $studentId)->delete();
            DB::table('bookings')->where('student_id', $studentId)->update([
                'notes' => null,
                'cancellation_reason' => null,
            ]);
            FormAnswer::query()->whereHas('submission', fn ($query) => $query->where('student_id', $studentId))
                ->update(['value_text' => '[redacted]']);

            DB::table('form_submission_revisions')
                ->select(['id', 'snapshot_answers', 'created_at'])
                ->whereIn('form_submission_id', DB::table('form_submissions')->select('id')->where('student_id', $studentId))
                ->orderBy('id')
                ->chunkById(200, function ($revisions): void {
                    foreach ($revisions as $revision) {
                        $snapshot = json_decode((string) $revision->snapshot_answers, true);
                        if (! is_array($snapshot)) {
                            $snapshot = [];
                        }
                        foreach ($snapshot as $key => $value) {
                            $snapshot[$key] = '[redacted]';
                        }
                        DB::table('form_submission_revisions')->where('id', $revision->id)->update([
                            'snapshot_answers' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                            'created_at' => $revision->created_at,
                        ]);
                    }
                });

            $this->scrubAuditPayloads($student);
            DB::table('student_emails')->where('student_id', $studentId)->delete();
            DB::table('student_email_verifications')->where('student_id', $studentId)->delete();
            DB::table('resource_requests')->where('student_id', $studentId)->update(['submitted_email' => null]);

            $student->forceFill([
                'first_name' => 'Anonymized',
                'last_name' => 'Student',
                'name_normalized' => 'anonymized student',
                'email' => $anonymousEmail,
                'email_normalized' => $anonymousEmail,
                'phone' => null,
                'phone_normalized' => null,
                'date_of_birth' => null,
                'internal_notes' => null,
                'possible_duplicate_of_student_id' => null,
            ])->save();
            $student->delete();

            Cache::forget('student_auth_check_'.$studentId);
            $this->auditLogs->log(
                action: 'student_privacy_erased',
                entityType: Student::class,
                entityId: $studentId,
                previousData: ['student_id' => $studentId],
                newData: ['student_id' => $studentId, 'personal_data_anonymized' => true],
                adminId: $administratorId,
            );

            return Student::withTrashed()->whereKey($studentId)->firstOrFail();
        }, 3);
    }

    private function scrubAuditPayloads(Student $student): void
    {
        $identifiers = array_values(array_filter([
            $student->first_name,
            $student->last_name,
            trim($student->first_name.' '.$student->last_name),
            $student->email,
            $student->email_normalized,
            ...$student->verifiedEmails()->pluck('email_normalized')->all(),
            $student->phone,
            $student->phone_normalized,
            $student->date_of_birth?->toDateString(),
            $student->internal_notes,
        ], fn (?string $value): bool => $value !== null && mb_strlen(trim($value), 'UTF-8') >= 3));

        DB::table('audit_logs')
            ->select([
                'id', 'actor_student_id', 'entity_type', 'entity_id', 'target_type', 'target_id',
                'previous_data', 'new_data', 'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($logs) use ($identifiers, $student): void {
                foreach ($logs as $log) {
                    $originalOld = $this->decodePayload($log->old_values ?? $log->previous_data);
                    $originalNew = $this->decodePayload($log->new_values ?? $log->new_data);
                    $redactedOld = $this->redactIdentifiers($originalOld, $identifiers);
                    $redactedNew = $this->redactIdentifiers($originalNew, $identifiers);
                    $studentIsAuditActorOrSubject = (int) $log->actor_student_id === (int) $student->id
                        || ($log->entity_type === Student::class && (int) $log->entity_id === (int) $student->id)
                        || ($log->target_type === Student::class && (int) $log->target_id === (int) $student->id);
                    $studentIdentifiersWereRedacted = $redactedOld !== $originalOld || $redactedNew !== $originalNew;
                    $redactRequestMetadata = $studentIsAuditActorOrSubject || $studentIdentifiersWereRedacted;
                    $old = AuditLog::scrubPayload($redactedOld);
                    $new = AuditLog::scrubPayload($redactedNew);
                    $oldJson = $old === null ? null : json_encode($old, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    $newJson = $new === null ? null : json_encode($new, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    DB::table('audit_logs')->where('id', $log->id)->update([
                        'previous_data' => $oldJson,
                        'new_data' => $newJson,
                        'old_values' => $oldJson,
                        'new_values' => $newJson,
                        'ip_address' => $redactRequestMetadata ? null : $log->ip_address,
                        'user_agent' => $redactRequestMetadata ? null : $log->user_agent,
                        'created_at' => $log->created_at,
                    ]);
                }
            });
    }

    /** @return array<mixed>|null */
    private function decodePayload(mixed $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = is_array($json) ? $json : json_decode((string) $json, true);

        return is_array($decoded) ? $decoded : ['redacted_payload' => '[redacted]'];
    }

    /** @param array<mixed>|null $payload
     * @param  array<int, string>  $identifiers
     * @return array<mixed>|null
     */
    private function redactIdentifiers(?array $payload, array $identifiers): ?array
    {
        if ($payload === null || $identifiers === []) {
            return $payload;
        }

        $redact = function (mixed $value) use (&$redact, $identifiers): mixed {
            if (is_array($value)) {
                foreach ($value as $key => $child) {
                    $value[$key] = $redact($child);
                }

                return $value;
            }

            if (! is_string($value)) {
                return $value;
            }

            foreach ($identifiers as $identifier) {
                $pattern = '/(?<![\\p{L}\\p{N}])'.preg_quote($identifier, '/').'(?![\\p{L}\\p{N}])/iu';
                $value = preg_replace($pattern, '[redacted]', $value) ?? $value;
            }

            return $value;
        };

        return $redact($payload);
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
