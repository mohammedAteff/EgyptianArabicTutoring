<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\StudentPackage;
use App\Domains\Timezone\Services\TimezoneService;

class FinancialStatementService
{
    /** @return array<string, mixed> */
    public function statement(StudentPackage $package, bool $studentView = false): array
    {
        $package->loadMissing(['student', 'payments', 'refunds', 'ledgerEntries', 'entitlements.type', 'installments']);
        $timezone = $studentView ? $this->studentTimezone($package) : app(TimezoneService::class)->getBusinessTimezone();
        $businessTimezone = app(TimezoneService::class)->getBusinessTimezone();
        $summary = app(StudentLedgerService::class)->summary($package, true, $businessTimezone);

        return ['package' => $package, 'summary' => $summary,
            'installments' => app(InstallmentScheduleService::class)->projection($package, $summary, $businessTimezone),
            'timezone' => $timezone,
            'references' => $studentView ? app(StudentFinancialReferences::class)->forStudent($package->student_id) : [],
        ];
    }

    public function studentTimezone(StudentPackage $package): string
    {
        $timezones = app(TimezoneService::class);
        foreach ([$package->statement_timezone, $package->student?->preferred_timezone] as $candidate) {
            if ($timezones->isValid($candidate)) {
                return $candidate;
            }
        }

        return $timezones->getBusinessTimezone();
    }

    /** @return \Generator<int, array<int, mixed>> */
    public function rows(StudentPackage $package, bool $studentView = false): \Generator
    {
        $statement = $this->statement($package, $studentView);
        $references = $statement['references'];
        $purchaseNumber = $studentView ? $references['purchases'][$package->id] : $package->id;
        yield ['Purchase', $purchaseNumber, $package->created_at->setTimezone($statement['timezone'])->toDateTimeString(), $package->final_price, $package->currency, $package->package_name, ''];
        foreach ($package->payments->sortBy('paid_at') as $payment) {
            yield ['Actual payment', $studentView ? $references['payments'][$payment->id] : $payment->id, $payment->paid_at->setTimezone($statement['timezone'])->toDateTimeString(), $payment->amount_paid,
                $package->currency, $payment->payment_method ?: 'Manual payment', $payment->transaction_reference ?? ''];
        }
        foreach ($package->refunds->sortBy('refunded_at') as $refund) {
            yield ['Actual refund', $studentView ? $references['refunds'][$refund->id] : $refund->id, $refund->refunded_at->setTimezone($statement['timezone'])->toDateTimeString(), $refund->amount_refunded, $package->currency, 'Original payment #'.($studentView ? $references['payments'][$refund->payment_record_id] : $refund->payment_record_id), ''];
        }
        yield ['Balance due now', $purchaseNumber, now($statement['timezone'])->toDateTimeString(), $statement['summary']['balance_due'], $package->currency, '', ''];
        yield ['Overpayment now', $purchaseNumber, now($statement['timezone'])->toDateTimeString(), $statement['summary']['overpaid'], $package->currency, '', ''];
    }
}
