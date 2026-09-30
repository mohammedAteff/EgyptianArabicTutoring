<?php

namespace App\Domains\Students\Services;

use App\Domains\Booking\Models\Booking;
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
        $totalRecords = StudentPackage::count() + PaymentRecord::count() + PaymentRefund::count() + SessionLedgerEntry::count();
        if ($totalRecords === 0) {
            return [
                'status' => 'UNINITIALIZED_DATASET',
                'status_label' => 'No Transactions Recorded',
                'has_discrepancies' => false,
                'total_discrepancies_count' => 0,
                'negative_balances' => [],
                'excess_refunds' => [],
                'credit_mismatches' => [],
                'ownership_inconsistencies' => [],
                'unreconciled_bookings' => [],
                'negative_credit_balance_bookings' => [],
                'summary' => [
                    'negative_balances_count' => 0,
                    'excess_refunds_count' => 0,
                    'credit_mismatches_count' => 0,
                    'ownership_inconsistencies_count' => 0,
                    'unreconciled_bookings_count' => 0,
                    'negative_credit_balance_bookings_count' => 0,
                ],
            ];
        }

        $negativeBalances = $this->checkNegativeBalances();
        $excessRefunds = $this->checkExcessRefunds();
        $creditMismatches = $this->checkCreditMismatches();
        $ownershipInconsistencies = $this->checkOwnershipInconsistencies();
        $unreconciledBookings = $this->checkUnreconciledBookings();
        $negativeCreditBalanceBookings = $this->checkNegativeCreditBalanceBookings();

        $totalCount = count($negativeBalances) + count($excessRefunds) + count($creditMismatches) + count($ownershipInconsistencies) + count($unreconciledBookings) + count($negativeCreditBalanceBookings);

        return [
            'status' => $totalCount > 0 ? 'DISCREPANCIES_DETECTED' : 'BALANCED',
            'status_label' => $totalCount > 0 ? 'Discrepancies Detected' : 'All Accounts Balanced',
            'has_discrepancies' => $totalCount > 0,
            'total_discrepancies_count' => $totalCount,
            'negative_balances' => $negativeBalances,
            'excess_refunds' => $excessRefunds,
            'credit_mismatches' => $creditMismatches,
            'ownership_inconsistencies' => $ownershipInconsistencies,
            'unreconciled_bookings' => $unreconciledBookings,
            'negative_credit_balance_bookings' => $negativeCreditBalanceBookings,
            'summary' => [
                'negative_balances_count' => count($negativeBalances),
                'excess_refunds_count' => count($excessRefunds),
                'credit_mismatches_count' => count($creditMismatches),
                'ownership_inconsistencies_count' => count($ownershipInconsistencies),
                'unreconciled_bookings_count' => count($unreconciledBookings),
                'negative_credit_balance_bookings_count' => count($negativeCreditBalanceBookings),
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

    /**
     * Check 5: Flag completed bookings with package linkage lacking session_consumed entries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkUnreconciledBookings(): array
    {
        $discrepancies = [];

        // Flag completed bookings ONLY when authoritative domain evidence demonstrates that a package credit was consumed
        // (source = 'student_portal' indicating booking via StudentBookingService with package ledger linkage)
        // but no corresponding session_consumed ledger entry exists for that booking_id.
        $studentPortalBookings = Booking::query()
            ->where('source', 'student_portal')
            ->where('status', 'completed')
            ->cursor();

        foreach ($studentPortalBookings as $booking) {
            $hasConsumedEntry = SessionLedgerEntry::query()
                ->where('booking_id', $booking->id)
                ->where('entry_type', 'session_consumed')
                ->exists();

            if (! $hasConsumedEntry) {
                $discrepancies[] = [
                    'booking_id' => $booking->id,
                    'student_id' => $booking->student_id,
                    'status' => $booking->status,
                    'source' => $booking->source,
                    'issue' => "Booking #{$booking->id} was created via student portal package linkage but lacks a session_consumed ledger entry.",
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * Check 6: Flag bookings linked to packages whose students have negative derived credit balances.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkNegativeCreditBalanceBookings(): array
    {
        $discrepancies = [];

        $studentsWithNegativeBalance = SessionLedgerEntry::query()
            ->select('student_id', DB::raw('SUM(credit_change) as total_credits'))
            ->groupBy('student_id')
            ->having('total_credits', '<', 0)
            ->pluck('total_credits', 'student_id')
            ->all();

        if (! empty($studentsWithNegativeBalance)) {
            $studentIds = array_keys($studentsWithNegativeBalance);
            $bookings = Booking::query()
                ->whereIn('student_id', $studentIds)
                ->cursor();

            foreach ($bookings as $booking) {
                $hasPackageLink = SessionLedgerEntry::query()
                    ->where('booking_id', $booking->id)
                    ->whereNotNull('student_package_id')
                    ->exists();

                if ($hasPackageLink) {
                    $discrepancies[] = [
                        'booking_id' => $booking->id,
                        'student_id' => $booking->student_id,
                        'derived_credit_balance' => (int) ($studentsWithNegativeBalance[$booking->student_id] ?? 0),
                        'issue' => "Booking #{$booking->id} is linked to a package for student #{$booking->student_id} who has a negative derived credit balance ({$studentsWithNegativeBalance[$booking->student_id]}).",
                    ];
                }
            }
        }

        return $discrepancies;
    }
}
