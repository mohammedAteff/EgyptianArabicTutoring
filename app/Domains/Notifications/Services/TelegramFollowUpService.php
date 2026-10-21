<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Administration\Models\StaffTask;
use App\Domains\Booking\Models\Booking;
use App\Domains\Notifications\Models\TelegramRule;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\InstallmentScheduleService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;

class TelegramFollowUpService
{
    public const TRIGGERS = ['installment_overdue', 'staff_task_overdue', 'meeting_missing'];

    public function scan(TelegramRule $rule): void
    {
        $query = match ($rule->trigger) {
            'installment_overdue' => StudentPackage::query()->whereHas('installments', fn ($query) => $query->where('status', '!=', 'voided')->where('due_date', '<', $this->today()->toDateString()))->with(['student', 'installments', 'payments', 'refunds', 'ledgerEntries', 'entitlements.type']),
            'staff_task_overdue' => StaffTask::query()->whereIn('status', ['open', 'in_progress'])->where('due_date', '<', $this->today()->toDateString())->with('student'),
            'meeting_missing' => Booking::query()->where('status', 'confirmed')->where('start_at_utc', '>', now('UTC'))->where('start_at_utc', '<=', now('UTC')->addMinutes($rule->minutes))->where(fn ($query) => $query->whereNull('meeting_room_id')->orWhereNull('meeting_url_snapshot')->orWhere('meeting_url_snapshot', ''))->with(['student', 'contact', 'sessionType']),
            default => throw new \InvalidArgumentException('Unknown follow-up alert.'),
        };
        $query->chunkById(75, function ($records) use ($rule): void {
            foreach ($records as $record) {
                $facts = $this->facts($rule, $record);
                if ($facts !== null) {
                    $entity = $rule->trigger.':'.$record->getKey();
                    app(TelegramAutomationService::class)->emit($rule->trigger, $entity.':'.now('UTC')->timestamp, $facts, $entity, $rule->id);
                }
            }
        });
    }

    /** @param array<string, scalar|null> $source
     * @return array<string, scalar|null>|null */
    public function currentFacts(TelegramRule $rule, array $source): ?array
    {
        $record = match ($rule->trigger) {
            'installment_overdue' => StudentPackage::find($source['record_id'] ?? null),
            'staff_task_overdue' => StaffTask::find($source['record_id'] ?? null),
            'meeting_missing' => Booking::find($source['booking_id'] ?? null),
            default => null,
        };
        if (! $record || ($record instanceof Booking && $record->start_at_utc->timestamp !== ($source['_booking_start'] ?? null))) {
            return null;
        }

        return $this->facts($rule, $record);
    }

    /** @return array<string, scalar|null>|null */
    private function facts(TelegramRule $rule, StudentPackage|StaffTask|Booking $record): ?array
    {
        if ($record instanceof Booking) {
            if ($record->status !== 'confirmed' || ! $record->start_at_utc->isFuture() || $record->start_at_utc->gt(now('UTC')->addMinutes($rule->minutes)) || ($record->meeting_room_id && $record->meeting_url_snapshot)) {
                return null;
            }

            return app(TelegramReadService::class)->booking($record);
        }
        $payload = ['record_id' => $record->id, '_student_id' => $record->student_id, 'student_name' => $record->student?->name];
        if ($record instanceof StaffTask) {
            if (! in_array($record->status, ['open', 'in_progress'], true) || ! $record->due_date || $record->due_date->toDateString() >= $this->today()->toDateString() || $record->due_date->toDateString() > $this->today()->subDays($rule->threshold)->toDateString()) {
                return null;
            }

            return $payload + ['due_date' => $record->due_date->toDateString(), 'admin_url' => route('admin.tasks.index')];
        }
        $amount = '0.00';
        $dueDate = null;
        foreach (app(InstallmentScheduleService::class)->projection($record) as $installment) {
            if ($installment['status'] === 'overdue' && $installment['due_date'] <= $this->today()->subDays($rule->threshold)->toDateString()) {
                $amount = bcadd($amount, $installment['due'], 2);
                $dueDate = $dueDate === null || $installment['due_date'] < $dueDate ? $installment['due_date'] : $dueDate;
            }
        }
        if (bccomp($amount, '0.00', 2) <= 0) {
            return null;
        }

        return $payload + ['amount' => $amount, 'currency' => $record->currency, 'due_date' => $dueDate, 'admin_url' => route('admin.students.show', $record->student_id)];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(app(TimezoneService::class)->getBusinessTimezone())->startOfDay();
    }
}
