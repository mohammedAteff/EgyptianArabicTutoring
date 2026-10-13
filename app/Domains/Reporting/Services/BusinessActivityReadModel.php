<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Administration\Models\StaffTask;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingPolicyDecision;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;

class BusinessActivityReadModel
{
    /** @return array<string, mixed> */
    public function day(string $date, bool $includePrivilegedActions = false): array
    {
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $day = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $from = $day->utc();
        $to = $day->addDay()->utc();
        $payments = PaymentRecord::query()->where('paid_at', '>=', $from)->where('paid_at', '<', $to)->get(['currency', 'amount_paid']);
        $currencies = [];
        foreach ($payments as $payment) {
            $currencies[$payment->currency] = bcadd($currencies[$payment->currency] ?? '0.00', $payment->amount_paid, 2);
        }
        $staffQuery = AuditLog::query()->whereNotNull('administrator_id')->where('created_at', '>=', $from)->where('created_at', '<', $to);
        if (! $includePrivilegedActions) {
            $staffQuery->whereIn('entity_type', [Booking::class, PaymentRecord::class, Student::class, StaffTask::class])
                ->whereNotIn('action', ['student_anonymized', 'student_merged', 'account_suspended', 'account_restored']);
        }
        $staff = (clone $staffQuery)->select('administrator_id', 'action')->selectRaw('COUNT(*) as action_count')->groupBy('administrator_id', 'action')->orderBy('administrator_id')->get();
        $staffSummaries = [];
        foreach ((clone $staffQuery)->orderBy('id')->lazyById(200) as $event) {
            $id = $event->administrator_id;
            $staffSummaries[$id] ??= ['bookings_changed' => 0, 'payments_entered' => 0, 'students_modified' => 0, 'tasks_completed' => 0];
            if ($event->entity_type === Booking::class) {
                $staffSummaries[$id]['bookings_changed']++;
            }
            if ($event->action === 'student_payment_recorded') {
                $staffSummaries[$id]['payments_entered']++;
            }
            if (in_array($event->action, ['student_identity_updated', 'student_updated', 'student_operational_status_changed', 'student_merged', 'student_anonymized'], true)) {
                $staffSummaries[$id]['students_modified']++;
            }
            if (in_array($event->action, ['staff_task_created', 'staff_task_updated'], true) && ($event->new_data['status'] ?? null) === 'completed' && ($event->previous_data['status'] ?? null) !== 'completed') {
                $staffSummaries[$id]['tasks_completed']++;
            }
        }

        return ['date' => $date, 'timezone' => $timezone,
            'delivered' => Booking::query()->where('start_at_utc', '>=', $from)->where('start_at_utc', '<', $to)->where('status', 'completed')->count(),
            'cancellations' => BookingPolicyDecision::query()->where('action', 'cancellation')->where('created_at', '>=', $from)->where('created_at', '<', $to)->count(),
            'newBookings' => Booking::query()->where('created_at', '>=', $from)->where('created_at', '<', $to)->count(),
            'newLeads' => Contact::query()->where('created_at', '>=', $from)->where('created_at', '<', $to)->count(),
            'paymentsCount' => $payments->count(), 'payments' => $currencies,
            'tasksRemaining' => StaffTask::query()->whereIn('status', ['open', 'in_progress'])->count(),
            'tasksCompleted' => StaffTask::query()->where('completed_at', '>=', $from)->where('completed_at', '<', $to)->count(),
            'staffSummaries' => $staffSummaries, 'staffActivity' => $staff];
    }
}
