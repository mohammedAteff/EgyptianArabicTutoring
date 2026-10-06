<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\StudentPackage;
use App\Domains\Timezone\Services\TimezoneService;

class FinancialStatementService
{
    /** @return array<string, mixed> */
    public function statement(StudentPackage $package): array
    {
        $package->loadMissing(['student', 'payments', 'refunds', 'ledgerEntries', 'entitlements.type', 'installments']);
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $summary = app(StudentLedgerService::class)->summary($package, true, $timezone);

        return ['package' => $package, 'summary' => $summary,
            'installments' => app(InstallmentScheduleService::class)->projection($package, $summary, $timezone),
            'timezone' => $timezone];
    }

    /** @return \Generator<int, array<int, mixed>> */
    public function rows(StudentPackage $package): \Generator
    {
        $statement = $this->statement($package);
        yield ['Purchase', $package->id, $package->created_at->setTimezone($statement['timezone'])->toDateTimeString(), $package->final_price, $package->currency, $package->package_name, ''];
        foreach ($package->payments->sortBy('paid_at') as $payment) {
            yield ['Actual payment', $payment->id, $payment->paid_at->setTimezone($statement['timezone'])->toDateTimeString(), $payment->amount_paid,
                $package->currency, $payment->payment_method ?: 'Manual payment', $payment->transaction_reference ?? ''];
        }
        foreach ($package->refunds->sortBy('refunded_at') as $refund) {
            yield ['Actual refund', $refund->id, $refund->refunded_at->setTimezone($statement['timezone'])->toDateTimeString(), $refund->amount_refunded, $package->currency, 'Original payment #'.$refund->payment_record_id, ''];
        }
        yield ['Balance due now', $package->id, now($statement['timezone'])->toDateTimeString(), $statement['summary']['balance_due'], $package->currency, '', ''];
        yield ['Overpayment now', $package->id, now($statement['timezone'])->toDateTimeString(), $statement['summary']['overpaid'], $package->currency, '', ''];
    }
}
