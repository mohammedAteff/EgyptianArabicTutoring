<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\StudentPackage;
use App\Domains\Timezone\Services\TimezoneService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReceivablesReadModel
{
    /** @return array<string, mixed> */
    public function filters(Request $request): array
    {
        $filters = $request->validate(['student_id' => ['nullable', 'integer', 'exists:students,id'],
            'currency' => ['nullable', 'regex:/^[A-Z]{3}$/'], 'state' => ['nullable', 'in:all,due,overdue,partial,overpaid'],
            'after_id' => ['nullable', 'integer', 'min:0'], 'format' => ['nullable', 'in:csv,xlsx']]);
        $filters['state'] ??= 'due';

        return $filters;
    }

    /** @param array<string, mixed> $filters
     * @return Builder<StudentPackage> */
    public function query(array $filters): Builder
    {
        return StudentPackage::query()->with(['student', 'payments', 'refunds', 'ledgerEntries', 'entitlements.type', 'installments'])
            ->when(! empty($filters['student_id']), fn (Builder $query) => $query->where('student_id', $filters['student_id']))
            ->when(! empty($filters['currency']), fn (Builder $query) => $query->where('currency', $filters['currency']))
            ->when(! empty($filters['after_id']), fn (Builder $query) => $query->where('id', '>', $filters['after_id']));
    }

    /** @param array<string, mixed> $filters
     * @return \Generator<int, array<string, mixed>> */
    public function rows(array $filters): \Generator
    {
        $ledger = app(StudentLedgerService::class);
        $installments = app(InstallmentScheduleService::class);
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        foreach ($this->query($filters)->lazyById(75) as $package) {
            $summary = $ledger->summary($package, true, $timezone);
            $schedule = $installments->projection($package, $summary, $timezone);
            $overdue = '0.00';
            foreach ($schedule as $row) {
                if ($row['status'] === 'overdue') {
                    $overdue = bcadd($overdue, $row['due'], 2);
                }
            }
            $partial = bccomp($summary['net_paid'], '0', 2) > 0 && bccomp($summary['balance_due'], '0', 2) > 0;
            $matches = match ($filters['state'] ?? 'due') {
                'due' => bccomp($summary['balance_due'], '0', 2) > 0,
                'overdue' => bccomp($overdue, '0', 2) > 0,
                'partial' => $partial,
                'overpaid' => bccomp($summary['overpaid'], '0', 2) > 0,
                default => true,
            };
            if ($matches) {
                yield ['package' => $package, 'summary' => $summary, 'installments' => $schedule, 'overdue' => $overdue, 'partial' => $partial];
            }
        }
    }
}
