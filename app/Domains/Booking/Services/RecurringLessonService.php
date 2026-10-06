<?php

namespace App\Domains\Booking\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Availability\Services\SlotResolver;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\RecurringLessonOccurrence;
use App\Domains\Booking\Models\RecurringLessonPlan;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentBookingService;
use App\Domains\Timezone\Exceptions\DstFoldAmbiguityException;
use App\Domains\Timezone\Exceptions\DstGapException;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecurringLessonService
{
    /** @param array<string, mixed> $data */
    public function create(Student $student, array $data, int $administratorId): RecurringLessonPlan
    {
        $this->authorize($administratorId);
        $values = Validator::make($data, [
            'session_type_id' => ['required', 'integer', 'exists:session_types,id'],
            'cadence' => ['required', 'in:weekly,fortnightly'],
            'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'occurrence_count' => ['nullable', 'integer', 'between:1,104'], 'preferred_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'timezone'], 'fold_policy' => ['required', 'in:first,second,reject'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ])->validate();
        $fingerprint = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));

        return app(DatabaseCapability::class)->transaction(function () use ($student, $values, $fingerprint, $administratorId): RecurringLessonPlan {
            $locked = Student::query()->lockForUpdate()->findOrFail($student->id);
            if ($locked->identity_status !== 'verified' || $locked->suspended_at !== null) {
                throw ValidationException::withMessages(['student' => 'Verify the student and resolve any suspension before creating a recurring plan.']);
            }
            $this->assertActiveStudent($locked);
            $existing = RecurringLessonPlan::query()->where('idempotency_key', $values['idempotency_key'])->first();
            if ($existing) {
                if ($existing->student_id !== $student->id || $existing->fingerprint !== $fingerprint) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This plan key was already used for different details.']);
                }

                return $existing;
            }
            $plan = RecurringLessonPlan::query()->create(array_merge($values, ['student_id' => $student->id, 'created_by' => $administratorId, 'fingerprint' => $fingerprint]));
            app(AuditLogService::class)->log('booking_recurring_plan_created', RecurringLessonPlan::class, $plan->id, null, ['student_id' => $student->id, 'status' => 'active'], $administratorId);

            return $plan;
        }, 3);
    }

    public function authorize(int $administratorId): void
    {
        abort_unless(Administrator::query()->whereKey($administratorId)->whereIn('role', ['admin', 'super_admin'])->whereNull('suspended_at')->exists(), 403);
    }

    public function assertActiveStudent(Student $student): void
    {
        if ($student->operational_status !== 'active') {
            throw ValidationException::withMessages(['student' => 'Restore the student to active before generating new recurring lessons.']);
        }
    }

    public function status(RecurringLessonPlan $plan, string $status, int $administratorId): void
    {
        $this->authorize($administratorId);
        Validator::make(['status' => $status], ['status' => ['required', 'in:active,paused,completed']])->validate();
        app(DatabaseCapability::class)->transaction(function () use ($plan, $status, $administratorId): void {
            $locked = RecurringLessonPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $previous = $locked->status;
            $locked->update(['status' => $status]);
            app(AuditLogService::class)->log('booking_recurring_plan_status', RecurringLessonPlan::class, $locked->id, ['status' => $previous], ['status' => $status], $administratorId);
        }, 3);
    }

    /** @return array<int, RecurringLessonOccurrence> */
    public function generate(RecurringLessonPlan $plan, int $administratorId, int $fromSequence = 1, int $count = 4): array
    {
        $this->authorize($administratorId);
        Validator::make(['from' => $fromSequence, 'count' => $count], ['from' => ['integer', 'between:1,104'], 'count' => ['integer', 'between:1,12']])->validate();
        $results = [];
        for ($sequence = $fromSequence; $sequence < min(105, $fromSequence + $count); $sequence++) {
            $plan = RecurringLessonPlan::query()->with(['student', 'sessionType'])->findOrFail($plan->id);
            if ($plan->status !== 'active') {
                break;
            }
            $this->assertActiveStudent($plan->student);
            $date = $plan->start_date->addDays(($sequence - 1) * ($plan->cadence === 'weekly' ? 7 : 14));
            if (($plan->occurrence_count !== null && $sequence > $plan->occurrence_count) || ($plan->end_date !== null && $date->greaterThan($plan->end_date))) {
                break;
            }
            $occurrence = RecurringLessonOccurrence::query()->firstOrCreate(['recurring_lesson_plan_id' => $plan->id, 'sequence' => $sequence], ['local_date' => $date->toDateString()]);
            if ($occurrence->booking_id !== null) {
                $results[] = $occurrence;

                continue;
            }
            try {
                $start = app(TimezoneService::class)->resolveLocalWallTime($date->toDateString().' '.$plan->preferred_time, $plan->timezone, $plan->fold_policy);
                $availability = app(AvailabilityService::class);
                $config = $availability->resolveSlotConfiguration($plan->sessionType, $start);
                $availability->validateSlotForBooking($plan->sessionType, $start, $config['end_utc']);
                $customerDate = $start->setTimezone($plan->timezone)->startOfDay();
                $slots = $availability->getAvailableSlotsGroupedByDate($plan->sessionType, $plan->timezone, $customerDate, $customerDate);
                $slot = collect($slots[$date->toDateString()] ?? [])->first(fn (array $slot): bool => CarbonImmutable::parse($slot['slot_start_utc'], 'UTC')->equalTo($start));
                if ($slot === null) {
                    throw new SlotUnavailableException('Outside tutor availability.');
                }
                $owner = 'recurring:'.$occurrence->id;
                $slotId = app(SlotResolver::class)->issue($plan->sessionType, $slot, $plan->timezone, $owner);
                app(StudentBookingService::class)->create($plan->student, $slotId, $plan->session_type_id, $plan->timezone, $owner, $owner, $occurrence, $administratorId);
            } catch (SlotUnavailableException|DstGapException|DstFoldAmbiguityException|ModelNotFoundException|\InvalidArgumentException|ValidationException $exception) {
                RecurringLessonOccurrence::query()->whereKey($occurrence->id)->whereNull('booking_id')->update(['status' => 'blocked', 'reason_code' => $this->reasonCode($exception->getMessage())]);
            }
            $results[] = $occurrence->refresh();
        }

        return $results;
    }

    public function reasonCode(string $message): string
    {
        $message = strtolower($message);

        return match (true) {
            str_contains($message, 'unavailability') => 'student_unavailable',
            str_contains($message, 'inactive'), str_contains($message, 'not currently active'), str_contains($message, 'no query results') => 'lesson_or_student_unavailable',
            str_contains($message, 'blocked') => 'blocked_date',
            str_contains($message, 'notice') => 'minimum_notice',
            str_contains($message, 'conflict'), str_contains($message, 'taken'), str_contains($message, 'reserved') => 'booking_conflict',
            str_contains($message, 'entitlement'), str_contains($message, 'credit'), str_contains($message, 'package') => 'compatible_credit_unavailable',
            str_contains($message, 'ambiguous'), str_contains($message, 'does not exist') => 'daylight_saving_time',
            default => 'outside_availability',
        };
    }
}
