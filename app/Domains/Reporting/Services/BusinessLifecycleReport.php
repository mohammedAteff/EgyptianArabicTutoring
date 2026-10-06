<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\ReceivablesReadModel;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessLifecycleReport
{
    /** @return array<string, mixed> */
    public function filters(Request $request): array
    {
        $filters = $request->validate(['student_id' => ['nullable', 'integer', 'exists:students,id'],
            'currency' => ['nullable', 'regex:/^[A-Z]{3}$/'], 'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'], 'format' => ['nullable', 'in:csv,xlsx']]);
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $filters['date_from'] ??= now($timezone)->startOfMonth()->toDateString();
        $filters['date_to'] ??= now($timezone)->toDateString();
        if ($filters['date_to'] < $filters['date_from']) {
            throw ValidationException::withMessages(['date_to' => 'The end date must be on or after the start date.']);
        }

        return $filters;
    }

    /** @param array<string, mixed> $filters
     * @return array{CarbonImmutable, CarbonImmutable} */
    public function bounds(array $filters): array
    {
        $timezone = app(TimezoneService::class)->getBusinessTimezone();

        return [CarbonImmutable::parse($filters['date_from'], $timezone)->startOfDay()->utc(),
            CarbonImmutable::parse($filters['date_to'], $timezone)->startOfDay()->addDay()->utc()];
    }

    /** @param array<string, mixed> $filters
     * @return \Generator<int, array<string, mixed>> */
    public function rows(array $filters): \Generator
    {
        [$from, $to] = $this->bounds($filters);
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $reader = app(ReceivablesReadModel::class);
        $packages = $reader->query($filters)->with('renewals')->lazyById(75);
        $ledger = app(StudentLedgerService::class);
        foreach ($packages as $package) {
            $summary = $ledger->summary($package, true, $timezone);
            $paid = '0.00';
            $refunded = '0.00';
            foreach ($package->payments as $payment) {
                if ($payment->paid_at->greaterThanOrEqualTo($from) && $payment->paid_at->lessThan($to)) {
                    $paid = bcadd($paid, $payment->amount_paid, 2);
                }
            }
            foreach ($package->refunds as $refund) {
                if ($refund->refunded_at->greaterThanOrEqualTo($from) && $refund->refunded_at->lessThan($to)) {
                    $refunded = bcadd($refunded, $refund->amount_refunded, 2);
                }
            }
            $renewals = $package->renewals->filter(fn ($renewal): bool => $renewal->renewal_date->toDateString() >= $filters['date_from'] && $renewal->renewal_date->toDateString() <= $filters['date_to']);
            yield ['package' => $package, 'summary' => $summary, 'period_paid' => $paid, 'period_refunded' => $refunded,
                'period_net' => bcsub($paid, $refunded, 2), 'renewals' => $renewals,
                'in_cohort' => $package->created_at->lessThan($to),
                'expired' => $package->expiration_date !== null && $package->expiration_date->toDateString() < now($timezone)->toDateString()];
        }
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed> */
    public function totals(array $filters): array
    {
        $currencies = [];
        foreach ($this->rows($filters) as $row) {
            $currency = $row['package']->currency;
            $currencies[$currency] ??= ['paid' => '0.00', 'refunded' => '0.00', 'net' => '0.00', 'outstanding' => '0.00', 'overpaid' => '0.00',
                'cohort_purchases' => 0, 'renewed_sources' => 0, 'renewals' => 0, 'expired' => 0];
            foreach (['paid' => 'period_paid', 'refunded' => 'period_refunded', 'net' => 'period_net'] as $target => $source) {
                $currencies[$currency][$target] = bcadd($currencies[$currency][$target], $row[$source], 2);
            }
            foreach (['outstanding' => 'balance_due', 'overpaid' => 'overpaid'] as $target => $source) {
                $currencies[$currency][$target] = bcadd($currencies[$currency][$target], $row['summary'][$source], 2);
            }
            $currencies[$currency]['cohort_purchases'] += $row['in_cohort'] ? 1 : 0;
            $currencies[$currency]['renewed_sources'] += $row['in_cohort'] && $row['renewals']->isNotEmpty() ? 1 : 0;
            $currencies[$currency]['renewals'] += $row['renewals']->count();
            $currencies[$currency]['expired'] += $row['expired'] ? 1 : 0;
        }
        [$from, $to] = $this->bounds($filters);
        $lessons = Booking::query()->where('status', 'completed')->whereNotNull('student_id')
            ->where('start_at_utc', '>=', $from)->where('start_at_utc', '<', $to)
            ->when(! empty($filters['student_id']), fn ($query) => $query->where('student_id', $filters['student_id']))
            ->when(! empty($filters['currency']), fn ($query) => $query->whereIn('student_id', StudentPackage::query()->where('currency', $filters['currency'])->select('student_id')))
            ->select('student_id')->selectRaw('COUNT(*) AS lesson_count')->groupBy('student_id')->toBase();
        $attending = DB::query()->fromSub(clone $lessons, 'attending')->count();
        $repeat = DB::query()->fromSub($lessons, 'attending')->where('lesson_count', '>=', 2)->count();

        return ['currencies' => $currencies, 'attending_students' => $attending, 'repeat_students' => $repeat];
    }

    /** @param array<string, mixed> $row
     * @return array<int, mixed> */
    public function exportRow(array $row): array
    {
        $package = $row['package'];

        return [$package->id, $package->student_id, $package->package_name, $package->currency, $row['period_paid'], $row['period_refunded'], $row['period_net'],
            $row['summary']['balance_due'], $row['summary']['overpaid'], $row['summary']['balance_text'],
            $package->expiration_date?->toDateString(), $row['renewals']->map(fn ($renewal): string => '#'.$renewal->new_package_id.' on '.$renewal->renewal_date->toDateString())->implode('; ')];
    }
}
