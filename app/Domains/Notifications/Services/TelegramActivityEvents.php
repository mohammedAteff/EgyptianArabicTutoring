<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Administration\Models\AdminNotification;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingWaitlist;
use App\Domains\Booking\Models\RecurringLessonPlan;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelegramActivityEvents
{
    /** @var array<string, array{string, string}> */
    public const AUDIT_ALERTS = [
        'booking_marked_completed' => ['Lesson completed', 'Bookings'],
        'booking_marked_no_show' => ['Lesson marked no-show', 'Bookings'],
        'meeting_room_assigned' => ['Meeting room assigned', 'Bookings'],
        'meeting_room_unassigned' => ['Meeting room removed', 'Bookings'],
        'student_waitlist_created' => ['Waitlist preference requested', 'Waitlist'],
        'student_waitlist_status_updated' => ['Waitlist preference status changed', 'Waitlist'],
        'booking_recurring_plan_created' => ['Recurring lesson plan created', 'Scheduling'],
        'booking_recurring_plan_status' => ['Recurring lesson plan status changed', 'Scheduling'],
        'student_unavailability_created' => ['Student holiday added', 'Scheduling'],
        'student_unavailability_cancelled' => ['Student holiday cancelled', 'Scheduling'],
        'student_operational_status_updated' => ['Student operational status changed', 'Students'],
        'student_operational_alert_created' => ['Student operational alert added', 'Students'],
        'student_operational_alert_updated' => ['Student operational alert changed', 'Students'],
        'student_credit_adjusted' => ['Lesson credits adjusted', 'Finance'],
        'credits_transferred' => ['Lesson credits transferred', 'Finance'],
        'finance_package_renewed' => ['Package renewed', 'Finance'],
        'finance_installment_schedule_created' => ['Installment schedule created', 'Finance'],
        'finance_installment_schedule_voided' => ['Installment schedule voided', 'Finance'],
        'package_paused' => ['Package paused', 'Finance'],
        'package_resumed' => ['Package resumed', 'Finance'],
        'package_validity_extended' => ['Package validity extended', 'Finance'],
        'package_first_payment_validity' => ['Package expiry started with first payment', 'Finance'],
        'staff_task_created' => ['Staff task created', 'Staff'],
        'staff_task_updated' => ['Staff task changed or completed', 'Staff'],
        'resource_collection_assigned' => ['Resource collection assigned', 'Learning'],
        'lms_assignment_submitted' => ['Learning assignment submitted', 'Learning'],
        'lms_assignment_reviewed' => ['Learning assignment reviewed', 'Learning'],
        'lms_quiz_submitted' => ['Quiz submitted', 'Learning'],
        'lms_quiz_reviewed' => ['Quiz reviewed', 'Learning'],
        'lms_lesson_completed' => ['Learning lesson completed', 'Learning'],
        'student_progress_report_created' => ['Student progress report created', 'Learning'],
        'student_teaching_homework_saved' => ['Homework assigned or updated', 'Learning'],
        'student_teaching_plan_saved' => ['Learning plan updated', 'Learning'],
        'student_teaching_milestone_saved' => ['Learning milestone updated', 'Learning'],
        'student_teaching_resource_saved' => ['Resource assigned', 'Learning'],
        'student_teaching_resource_withdrawn' => ['Assigned resource withdrawn', 'Learning'],
        'lms_access_grant' => ['Learning access granted', 'Learning'],
        'lms_access_enroll' => ['Student enrolled in a course', 'Learning'],
        'lms_access_assign' => ['Learning assigned', 'Learning'],
        'lms_access_revoke' => ['Learning access revoked', 'Learning'],
        'lms_access_extend' => ['Learning access extended', 'Learning'],
        'lms_access_set_expiration' => ['Learning expiry changed', 'Learning'],
        'lms_access_remove_expiration' => ['Learning expiry removed', 'Learning'],
        'lms_access_set_start' => ['Learning start date changed', 'Learning'],
    ];

    public function register(): void
    {
        AuditLog::created(function (AuditLog $event): void {
            if (! isset(self::AUDIT_ALERTS[$event->action])) {
                return;
            }
            $this->after(function () use ($event): void {
                $values = $event->new_values ?? [];
                $studentId = $event->actor_student_id ?? ($values['student_id'] ?? null);
                $sessionTypeId = $values['session_type_id'] ?? null;
                if ($event->entity_type === Student::class) {
                    $studentId = $event->entity_id;
                } elseif (in_array($event->entity_type, [Booking::class, BookingWaitlist::class, RecurringLessonPlan::class, StudentPackage::class], true)) {
                    $subject = $event->entity_type::query()->find($event->entity_id);
                    $studentId ??= $subject?->getAttribute('student_id');
                    $sessionTypeId ??= $subject?->getAttribute('session_type_id');
                }
                $payload = $this->studentPayload($studentId ? (int) $studentId : null);
                $payload += ['_session_type_id' => $sessionTypeId, 'event' => self::AUDIT_ALERTS[$event->action][0], 'record_type' => class_basename($event->entity_type), 'record_id' => $event->entity_id,
                    'status' => is_string($values['status'] ?? null) ? $values['status'] : 'Recorded',
                    'occurred_at' => $event->created_at->toIso8601String(), 'admin_url' => route('admin.audit-logs')];
                app(TelegramAutomationService::class)->emit($event->action, 'audit:'.$event->event_uuid, $payload);
            });
        });
        foreach ([PaymentRecord::class => 'payment_recorded', PaymentRefund::class => 'payment_refunded', StudentPackage::class => 'package_created'] as $model => $trigger) {
            $model::created(function (Model $record) use ($trigger): void {
                $this->after(function () use ($record, $trigger): void {
                    $payload = $this->studentPayload((int) $record->getAttribute('student_id'));
                    $payload += ['record_id' => $record->getKey(), 'amount' => (string) ($record->getAttribute('amount_paid') ?? $record->getAttribute('amount_refunded') ?? $record->getAttribute('final_price')),
                        'currency' => $record->getAttribute('currency'), 'admin_url' => route('admin.students.show', $record->getAttribute('student_id'))];
                    app(TelegramAutomationService::class)->emit($trigger, $trigger.':'.$record->getKey(), $payload);
                });
            });
        }
        Student::saved(function (Student $student): void {
            if ($student->identity_status === 'verified' && ($student->wasRecentlyCreated || $student->wasChanged('identity_status'))) {
                $this->after(fn () => app(TelegramAutomationService::class)->emit('student_verified', 'student:'.$student->id,
                    $this->studentPayload($student->id) + ['admin_url' => route('admin.students.show', $student->id)]));
            }
        });
        AdminNotification::created(function (AdminNotification $notification): void {
            if (! in_array($notification->type, ['waitlist_opening', 'student_birthday', 'system_warning'], true)) {
                return;
            }
            $this->after(function () use ($notification): void {
                $payload = $this->studentPayload($notification->data['student_id'] ?? null);
                $birthday = $notification->data['birthday_date'] ?? null;
                $payload += ['notification_id' => $notification->id, 'birthday' => $birthday ? CarbonImmutable::parse($birthday)->format('j F') : null,
                    'details' => $notification->type === 'system_warning' ? 'Review System Health for details.' : $notification->message,
                    'admin_url' => $notification->link];
                app(TelegramAutomationService::class)->emit($notification->type, 'notification:'.$notification->id, $payload);
            });
        });
    }

    /** @return array<string, scalar|null> */
    private function studentPayload(?int $studentId): array
    {
        $student = $studentId ? Student::find($studentId) : null;

        return ['_student_id' => $studentId, 'student_name' => $student?->name];
    }

    private function after(callable $callback): void
    {
        DB::afterCommit(function () use ($callback): void {
            try {
                $callback();
            } catch (\Throwable $exception) {
                Log::warning('Telegram activity event failed.', ['exception_type' => $exception::class]);
            }
        });
    }
}
