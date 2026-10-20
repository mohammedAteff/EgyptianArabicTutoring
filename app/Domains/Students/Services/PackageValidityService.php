<?php

namespace App\Domains\Students\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

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

    /** @return array<string, mixed> */
    public function firstPaymentTerms(StudentPackage $package): array
    {
        if ($package->validity_days === null) {
            return [];
        }
        $first = $package->payments()->where('amount_paid', '>', 0)->orderBy('paid_at')->orderBy('id')->first();
        if (! $first) {
            return [];
        }
        $start = CarbonImmutable::instance($first->paid_at)->utc();
        if ($package->validity_started_at && $start->greaterThanOrEqualTo($package->validity_started_at)) {
            return [];
        }
        $timezone = $this->timezones->getBusinessTimezone();
        $extensionSeconds = 0;
        if ($package->validity_started_at && $package->expires_at) {
            $originalDeadline = $package->validity_started_at->setTimezone($timezone)->addDays((int) $package->validity_days)->utc();
            $extensionSeconds = max(0, $package->expires_at->timestamp - $originalDeadline->timestamp);
        }
        $expiry = $start->setTimezone($timezone)->addDays((int) $package->validity_days)->addSeconds($extensionSeconds);

        return ['validity_started_at' => $start->toDateTimeString(), 'expires_at' => $expiry->utc()->toDateTimeString(), 'expiration_date' => $expiry->toDateString()];
    }

    public function activate(StudentPackage $package): void
    {
        if ($package->validity_days === null) {
            return;
        }
        $changes = $this->firstPaymentTerms($package);
        if ($package->funding_activated_at === null && ($settled = $this->firstFullReceipt($package))) {
            $changes['funding_activated_at'] = $settled->toDateTimeString();
        }
        if ($changes !== []) {
            $before = $package->only(array_keys($changes));
            DB::table('student_packages')->where('id', $package->id)->update($changes);
            app(AuditLogService::class)->log('package_first_payment_validity', StudentPackage::class, $package->id, $before, $changes);
            $package->refresh();
        }
    }
}
