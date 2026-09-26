<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\StudentPackage;
use Illuminate\Support\Facades\DB;

class BillingReconciliationService
{
    /**
     * Run all reconciliation checks without mutating historical data.
     *
     * @return array{
     *     has_discrepancies: bool,
     *     total_discrepancies_count: int,
     *     negative_balances: array<int, array<string, mixed>>,
     *     excess_refunds: array<int, array<string, mixed>>,
     *     credit_mismatches: array<int, array<string, mixed>>,
     *     ownership_inconsistencies: array<int, array<string, mixed>>,
     *     summary: array<string, int>
     * }
     */
    public function reconcile(): array
    {
        $negativeBalances = $this->checkNegativeBalances();
        $excessRefunds = $this->checkExcessRefunds();
        $creditMismatches = $this->checkCreditMismatches();
        $ownershipInconsistencies = $this->checkOwnershipInconsistencies();

        $totalCount = count($negativeBalances) + count($excessRefunds) + count($creditMismatches) + count($ownershipInconsistencies);

        return [
            'has_discrepancies' => $totalCount > 0,
            'total_discrepancies_count' => $totalCount,
            'negative_balances' => $negativeBalances,
            'excess_refunds' => $excessRefunds,
            'credit_mismatches' => $creditMismatches,
            'ownership_inconsistencies' => $ownershipInconsistencies,
            'summary' => [
                'negative_balances_count' => count($negativeBalances),
                'excess_refunds_count' => count($excessRefunds),
                'credit_mismatches_count' => count($creditMismatches),
                'ownership_inconsistencies_count' => count($ownershipInconsistencies),
            ],
        ];
    }

    /**
     * Check 1: Negative derived package balances (where net paid > final price).
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkNegativeBalances(): array
    {
        $discrepancies = [];

        $packages = StudentPackage::query()
            ->with(['student', 'payments', 'refunds'])
            ->cursor();

        foreach ($packages as $pkg) {
            $grossPaid = '0.00';
            foreach ($pkg->payments as $pmt) {
                $grossPaid = bcadd($grossPaid, (string) $pmt->amount_paid, 2);
            }

            $grossRefunded = '0.00';
            foreach ($pkg->refunds as $ref) {
                $grossRefunded = bcadd($grossRefunded, (string) $ref->amount_refunded, 2);
            }

            $netPaid = bcsub($grossPaid, $grossRefunded, 2);
            $remaining = bcsub((string) $pkg->final_price, $netPaid, 2);

            if (bccomp($remaining, '0.00', 2) < 0) {
                $discrepancies[] = [
                    'package_id' => $pkg->id,
                    'package_name' => $pkg->package_name,
                    'student_id' => $pkg->student_id,
                    'student_name' => $pkg->student?->name ?? 'Unknown',
                    'student_email' => $pkg->student?->email ?? 'Unknown',
                    'final_price' => $pkg->final_price,
                    'gross_paid' => $grossPaid,
                    'gross_refunded' => $grossRefunded,
                    'net_paid' => $netPaid,
                    'remaining_balance' => $remaining,
                    'issue' => "Overpaid package: remaining balance {$remaining} {$pkg->currency} is negative.",
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * Check 2: Payment refunds exceeding the payment amount.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkExcessRefunds(): array
    {
        $discrepancies = [];

        $payments = PaymentRecord::query()
            ->with('student')
            ->cursor();

        foreach ($payments as $pmt) {
            $totalRefunded = (string) (PaymentRefund::query()
                ->where('payment_record_id', $pmt->id)
                ->sum('amount_refunded') ?: '0.00');

            if (bccomp($totalRefunded, (string) $pmt->amount_paid, 2) > 0) {
                $discrepancies[] = [
                    'payment_id' => $pmt->id,
                    'student_id' => $pmt->student_id,
                    'student_name' => $pmt->student?->name ?? 'Unknown',
                    'student_email' => $pmt->student?->email ?? 'Unknown',
                    'amount_paid' => (string) $pmt->amount_paid,
                    'total_refunded' => $totalRefunded,
                    'excess_amount' => bcsub($totalRefunded, (string) $pmt->amount_paid, 2),
                    'currency' => $pmt->currency,
                    'issue' => "Total refunds ({$totalRefunded}) exceed original payment ({$pmt->amount_paid}).",
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * Check 3: Package allocated credits vs ledger grant mismatches.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkCreditMismatches(): array
    {
        $discrepancies = [];

        $packages = StudentPackage::query()
            ->with('student')
            ->cursor();

        foreach ($packages as $pkg) {
            $grantSum = (int) SessionLedgerEntry::query()
                ->where('student_package_id', $pkg->id)
                ->where('entry_type', 'package_grant')
                ->sum('credit_change');

            if ($grantSum !== (int) $pkg->total_sessions_allocated) {
                $discrepancies[] = [
                    'package_id' => $pkg->id,
                    'package_name' => $pkg->package_name,
                    'student_id' => $pkg->student_id,
                    'student_name' => $pkg->student?->name ?? 'Unknown',
                    'allocated_sessions' => (int) $pkg->total_sessions_allocated,
                    'granted_credits' => $grantSum,
                    'delta' => (int) $pkg->total_sessions_allocated - $grantSum,
                    'issue' => "Allocated sessions ({$pkg->total_sessions_allocated}) does not match package grant sum ({$grantSum}).",
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * Check 4: Orphaned or ownership-inconsistent records.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkOwnershipInconsistencies(): array
    {
        $discrepancies = [];

        // Check PaymentRecords mismatched with Package student_id
        $mismatchedPayments = DB::table('payment_records')
            ->join('student_packages', 'payment_records.student_package_id', '=', 'student_packages.id')
            ->whereColumn('payment_records.student_id', '!=', 'student_packages.student_id')
            ->select(
                'payment_records.id as payment_id',
                'payment_records.student_id as payment_student_id',
                'student_packages.id as package_id',
                'student_packages.student_id as package_student_id'
            )
            ->get();

        foreach ($mismatchedPayments as $mp) {
            $discrepancies[] = [
                'type' => 'PaymentRecord Mismatch',
                'record_id' => $mp->payment_id,
                'details' => "Payment #{$mp->payment_id} belongs to student #{$mp->payment_student_id} but package #{$mp->package_id} belongs to student #{$mp->package_student_id}.",
            ];
        }

        // Check PaymentRefunds mismatched with PaymentRecord
        $mismatchedRefunds = DB::table('payment_refunds')
            ->join('payment_records', 'payment_refunds.payment_record_id', '=', 'payment_records.id')
            ->where(function ($q) {
                $q->whereColumn('payment_refunds.student_id', '!=', 'payment_records.student_id')
                    ->orWhereColumn('payment_refunds.student_package_id', '!=', 'payment_records.student_package_id');
            })
            ->select(
                'payment_refunds.id as refund_id',
                'payment_refunds.student_id as refund_student_id',
                'payment_records.id as payment_id',
                'payment_records.student_id as payment_student_id'
            )
            ->get();

        foreach ($mismatchedRefunds as $mr) {
            $discrepancies[] = [
                'type' => 'PaymentRefund Mismatch',
                'record_id' => $mr->refund_id,
                'details' => "Refund #{$mr->refund_id} student/package does not match payment #{$mr->payment_id}.",
            ];
        }

        // Check SessionLedgerEntry mismatched with package
        $mismatchedLedger = DB::table('session_ledger_entries')
            ->join('student_packages', 'session_ledger_entries.student_package_id', '=', 'student_packages.id')
            ->whereColumn('session_ledger_entries.student_id', '!=', 'student_packages.student_id')
            ->select(
                'session_ledger_entries.id as entry_id',
                'session_ledger_entries.student_id as entry_student_id',
                'student_packages.id as package_id',
                'student_packages.student_id as package_student_id'
            )
            ->get();

        foreach ($mismatchedLedger as $ml) {
            $discrepancies[] = [
                'type' => 'SessionLedgerEntry Mismatch',
                'record_id' => $ml->entry_id,
                'details' => "Ledger entry #{$ml->entry_id} student #{$ml->entry_student_id} does not match package #{$ml->package_id} student #{$ml->package_student_id}.",
            ];
        }

        return $discrepancies;
    }
}
