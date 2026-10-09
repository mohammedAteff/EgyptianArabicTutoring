<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\StudentPackage;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;

class PackageValidityService
{
    public function __construct(private TimezoneService $timezones) {}

    public function firstFullReceipt(StudentPackage $package): ?CarbonImmutable
    {
        $transactions = [];
        foreach ($package->payments()->get() as $payment) {
            $transactions[] = ['at' => CarbonImmutable::instance($payment->paid_at), 'amount' => (string) $payment->amount_paid, 'order' => 0, 'id' => $payment->id];
        }
        foreach ($package->refunds()->get() as $refund) {
            $transactions[] = ['at' => CarbonImmutable::instance($refund->refunded_at), 'amount' => '-'.(string) $refund->amount_refunded, 'order' => 1, 'id' => $refund->id];
        }
        usort($transactions, fn (array $first, array $second): int => $first['at']->getTimestamp() <=> $second['at']->getTimestamp() ?: $first['order'] <=> $second['order'] ?: $first['id'] <=> $second['id']);
        $net = '0.00';
        foreach ($transactions as $transaction) {
            $net = bcadd($net, $transaction['amount'], 2);
            if (bccomp($net, (string) $package->final_price, 2) >= 0 && $transaction['order'] === 0) {
                return $transaction['at']->utc();
            }
        }

        return null;
    }

    public function activate(StudentPackage $package, bool $hadPreviousSettlement): void
    {
        if ($package->validity_days === null || $package->expiration_date !== null || $package->validity_started_at !== null || $package->expires_at !== null || $hadPreviousSettlement) {
            return;
        }
        $paid = (string) $package->payments()->sum('amount_paid');
        $refunded = (string) $package->refunds()->sum('amount_refunded');
        if (bccomp(bcsub($paid, $refunded, 2), (string) $package->final_price, 2) < 0) {
            return;
        }
        $start = $this->firstFullReceipt($package);
        if (! $start) {
            return;
        }
        $expiry = $start->setTimezone($this->timezones->getBusinessTimezone())->addDays((int) $package->validity_days);
        $package->update([
            'validity_started_at' => $start, 'expires_at' => $expiry->utc(),
            'expiration_date' => $expiry->toDateString(),
        ]);
    }
}
