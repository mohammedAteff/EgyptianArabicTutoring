<?php

namespace App\Domains\Students\Services;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Timezone\Services\TimezoneService;
use Illuminate\Support\Collection;

class StudentPackagePresentation
{
    public function __construct(private StudentLedgerService $ledger, private TimezoneService $timezones) {}

    /** @return array{packages: Collection, selected: array|null, history: Collection} */
    public function forStudent(int $studentId, ?int $packageId, string $historyType): array
    {

        $packages = StudentPackage::query()->where('student_id', $studentId)

            ->with(['payments', 'refunds', 'ledgerEntries', 'entitlements.type'])

            ->orderByDesc('created_at')->orderByDesc('id')->get();

        $selected = $packageId === null ? $packages->first() : $packages->firstWhere('id', $packageId);

        abort_if($packageId !== null && $selected === null, 404);

        $businessTimezone = $this->timezones->getBusinessTimezone();

        $summaries = $packages->map(function (StudentPackage $package) use ($businessTimezone): array {

            $summary = $this->ledger->summary($package, true, $businessTimezone);

            $status = $summary['entitlement_status'];

            return ['package' => $package, 'summary' => $summary, 'status' => $status];

        });

        if ($selected === null) {

            return ['packages' => $summaries, 'selected' => null, 'history' => collect()];

        }

        $payments = $selected->payments->sortByDesc(fn (PaymentRecord $payment): string => $payment->paid_at->format('Y-m-d H:i:s').sprintf('%020d', $payment->id))->values()

            ->map(function (PaymentRecord $payment) use ($selected): array {

                $refundable = $this->ledger->refundableAmount($payment, $selected);

                return ['payment' => $payment, 'refundable' => $refundable, 'canRefund' => bccomp($refundable, '0.00', 2) > 0,

                    'refundStatus' => bccomp($refundable, $payment->amount_paid, 2) === 0 ? 'Not refunded' : (bccomp($refundable, '0.00', 2) === 0 ? 'Fully refunded' : 'Partially refunded')];

            });

        $validityHistory = AuditLog::query()->where('entity_type', StudentPackage::class)->where('entity_id', $selected->id)

            ->where('action', 'package_validity_extended')->orderByDesc('id')->get();

        $selectedSummary = $summaries->firstWhere('package.id', $selected->id);

        return ['packages' => $summaries, 'selected' => array_merge($selectedSummary, ['payments' => $payments, 'validityHistory' => $validityHistory]),

            'history' => $this->history($selected, $validityHistory)->when($historyType !== 'all', fn (Collection $rows): Collection => $rows->where('type', $historyType))->values()];

    }

    /** @return Collection<int, array{type: string, reference: string, at: mixed, summary: string, details: array<string, mixed>}> */
    private function history(StudentPackage $package, Collection $validityHistory): Collection
    {

        $rows = collect();

        foreach ($package->payments as $payment) {

            $rows->push(['type' => 'payments', 'reference' => 'PAY-'.$payment->id, 'at' => $payment->paid_at,

                'summary' => 'Payment · '.$payment->currency.' '.$payment->amount_paid,

                'details' => ['Method' => $payment->payment_method, 'External reference' => $payment->transaction_reference, 'Private staff note' => $payment->notes, 'Staff' => $payment->recorded_by]]);

        }

        foreach ($package->refunds as $refund) {

            $payment = $package->payments->firstWhere('id', $refund->payment_record_id);

            $rows->push(['type' => 'refunds', 'reference' => 'REF-'.$refund->id, 'at' => $refund->refunded_at,

                'summary' => 'Refund · '.$refund->currency.' '.$refund->amount_refunded,

                'details' => ['Original payment' => 'PAY-'.$refund->payment_record_id.' · '.$payment?->currency.' '.$payment?->amount_paid.' · '.$payment?->payment_method, 'External reference' => $payment?->transaction_reference, 'Reason' => $refund->reason, 'Staff' => $refund->recorded_by]]);

        }

        foreach ($package->ledgerEntries as $entry) {

            $rows->push(['type' => 'credits', 'reference' => 'CRD-'.$entry->id, 'at' => $entry->created_at,

                'summary' => ($package->entitlements->firstWhere('id', $entry->student_package_entitlement_id)?->type->label ?? 'Unclassified historical units').' · '.($entry->credit_change > 0 ? '+' : '').$entry->credit_change.' · '.str_replace('_', ' ', $entry->entry_type),

                'details' => ['Allocation' => $entry->student_package_entitlement_id, 'Offering' => $package->offering_key ?? 'Unclassified', 'Reason' => $entry->description, 'Booking' => $entry->booking_id, 'Staff' => $entry->created_by]]);

        }

        foreach ($validityHistory as $change) {

            $rows->push(['type' => 'expiry', 'reference' => 'AUD-'.$change->id, 'at' => $change->created_at,

                'summary' => 'Validity extended · '.($change->previous_data['expiration_date'] ?? 'None').' → '.($change->new_data['expiration_date'] ?? 'None'),

                'details' => ['Reason' => $change->new_data['reason'] ?? '', 'Staff' => $change->administrator_id]]);

        }

        return $rows->sortByDesc(fn (array $row): string => $row['at']->format('Y-m-d H:i:s').$row['reference'])->values();

    }
}
