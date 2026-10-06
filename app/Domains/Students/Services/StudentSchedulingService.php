<?php

namespace App\Domains\Students\Services;

use App\Domains\Administration\Services\OperationalReasonCatalog;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\BookingWaitlist;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentUnavailability;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StudentSchedulingService
{
    public function assertAvailable(int $studentId, CarbonImmutable $start, CarbonImmutable $end): void
    {
        if (StudentUnavailability::query()->where('student_id', $studentId)->where('status', 'active')
            ->where('starts_at_utc', '<', $end)->where('ends_at_utc', '>', $start)->exists()) {
            throw new SlotUnavailableException('This time overlaps your recorded unavailability.');
        }
    }

    /** @param array<string, mixed> $data */
    public function holiday(Student $student, array $data, ?int $administratorId = null): StudentUnavailability
    {
        $values = Validator::make($data, [
            'date_from' => ['required', 'date_format:Y-m-d'], 'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'timezone' => ['required', 'timezone'], 'reason_code' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'], 'idempotency_key' => ['required', 'string', 'max:100'],
        ])->validate();
        OperationalReasonCatalog::validate($values['reason_code'] ?? null);
        $timezones = app(TimezoneService::class);
        $nextDate = CarbonImmutable::parse($values['date_to'])->addDay()->toDateString();
        try {
            $start = $timezones->resolveLocalWallTime($values['date_from'].' 00:00:00', $values['timezone'], 'first');
            $end = $timezones->resolveLocalWallTime($nextDate.' 00:00:00', $values['timezone'], 'first');
        } catch (SlotUnavailableException) {
            throw ValidationException::withMessages(['date_from' => 'A date boundary does not exist in this timezone because of a clock change. Choose another date or timezone.']);
        }
        if ($end->diffInDays($start, true) > 367) {
            throw ValidationException::withMessages(['date_to' => 'Use a period of at most one year.']);
        }
        $fingerprint = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));

        return app(DatabaseCapability::class)->transaction(function () use ($student, $values, $start, $end, $fingerprint, $administratorId): StudentUnavailability {
            Student::query()->lockForUpdate()->findOrFail($student->id);
            $existing = StudentUnavailability::query()->where('idempotency_key', $values['idempotency_key'])->first();
            if ($existing) {
                $this->assertReplay($existing->student_id, $student->id, $existing->fingerprint, $fingerprint);

                return $existing;
            }
            $record = StudentUnavailability::query()->create([
                'student_id' => $student->id, 'starts_at_utc' => $start, 'ends_at_utc' => $end,
                'timezone' => $values['timezone'], 'reason_code' => $values['reason_code'] ?? null, 'notes' => $values['notes'] ?? null,
                'idempotency_key' => $values['idempotency_key'], 'fingerprint' => $fingerprint,
            ]);
            if ($administratorId === null) {
                app(AuditLogService::class)->logStudent($student->id, 'student_unavailability_created', StudentUnavailability::class, $record->id, null, ['status' => 'active']);
            } else {
                app(AuditLogService::class)->log('student_unavailability_created', StudentUnavailability::class, $record->id, null, ['student_id' => $student->id, 'status' => 'active'], $administratorId);
            }

            return $record;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function waitlist(Student $student, array $data): BookingWaitlist
    {
        $values = Validator::make($data, [
            'session_type_id' => ['required', 'integer', 'exists:session_types,id'],
            'date_from' => ['required', 'date_format:Y-m-d'], 'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'timezone' => ['required', 'timezone'], 'reason_code' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'], 'idempotency_key' => ['required', 'string', 'max:100'],
        ])->validate();
        OperationalReasonCatalog::validate($values['reason_code'] ?? null);
        if (CarbonImmutable::parse($values['date_from'])->diffInDays(CarbonImmutable::parse($values['date_to'])) > 90) {
            throw ValidationException::withMessages(['date_to' => 'Waitlist interests cover at most 90 days.']);
        }
        $fingerprint = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));

        return app(DatabaseCapability::class)->transaction(function () use ($student, $values, $fingerprint): BookingWaitlist {
            Student::query()->lockForUpdate()->findOrFail($student->id);
            $existing = BookingWaitlist::query()->where('idempotency_key', $values['idempotency_key'])->first();
            if ($existing) {
                $this->assertReplay($existing->student_id, $student->id, $existing->fingerprint, $fingerprint);

                return $existing;
            }
            $record = BookingWaitlist::query()->create(array_merge($values, ['student_id' => $student->id, 'fingerprint' => $fingerprint]));
            app(AuditLogService::class)->logStudent($student->id, 'student_waitlist_created', BookingWaitlist::class, $record->id, null, ['status' => 'open']);

            return $record;
        }, 3);
    }

    public function closeHoliday(StudentUnavailability $holiday, ?int $administratorId = null): void
    {
        app(DatabaseCapability::class)->transaction(function () use ($holiday, $administratorId): void {
            Student::query()->lockForUpdate()->findOrFail($holiday->student_id);
            $locked = StudentUnavailability::query()->lockForUpdate()->findOrFail($holiday->id);
            $locked->update(['status' => 'cancelled']);
            if ($administratorId === null) {
                app(AuditLogService::class)->logStudent($locked->student_id, 'student_unavailability_cancelled', StudentUnavailability::class, $locked->id, null, ['status' => 'cancelled']);
            } else {
                app(AuditLogService::class)->log('student_unavailability_cancelled', StudentUnavailability::class, $locked->id, null, ['status' => 'cancelled'], $administratorId);
            }
        }, 3);
    }

    public function waitlistStatus(BookingWaitlist $interest, string $status, ?int $administratorId = null): void
    {
        Validator::make(['status' => $status], ['status' => ['required', 'in:open,contacted,closed,withdrawn']])->validate();
        app(DatabaseCapability::class)->transaction(function () use ($interest, $status, $administratorId): void {
            Student::query()->lockForUpdate()->findOrFail($interest->student_id);
            $locked = BookingWaitlist::query()->lockForUpdate()->findOrFail($interest->id);
            $previous = $locked->status;
            $locked->update(['status' => $status]);
            if ($administratorId === null) {
                app(AuditLogService::class)->logStudent($locked->student_id, 'student_waitlist_status_updated', BookingWaitlist::class, $locked->id, ['status' => $previous], ['status' => $status]);
            } else {
                app(AuditLogService::class)->log('student_waitlist_status_updated', BookingWaitlist::class, $locked->id, ['status' => $previous], ['status' => $status], $administratorId);
            }
        }, 3);
    }

    public function operationalStatus(Student $student, string $status, ?string $reasonCode, ?int $administratorId): void
    {
        Validator::make(['status' => $status], ['status' => ['required', 'in:active,inactive,archived']])->validate();
        OperationalReasonCatalog::validate($reasonCode);
        app(DatabaseCapability::class)->transaction(function () use ($student, $status, $reasonCode, $administratorId): void {
            $locked = Student::query()->lockForUpdate()->findOrFail($student->id);
            $previous = $locked->operational_status;
            $locked->update(['operational_status' => $status, 'operational_status_changed_at' => now('UTC'), 'operational_reason_code' => $reasonCode]);
            app(AuditLogService::class)->log('student_operational_status_updated', Student::class, $locked->id, ['operational_status' => $previous], ['operational_status' => $status, 'reason_code' => $reasonCode], $administratorId);
        }, 3);
    }

    private function assertReplay(int $owner, int $studentId, string $existing, string $fingerprint): void
    {
        if ($owner !== $studentId || ! hash_equals($existing, $fingerprint)) {
            throw ValidationException::withMessages(['idempotency_key' => 'This request key was already used for different details.']);
        }
    }
}
