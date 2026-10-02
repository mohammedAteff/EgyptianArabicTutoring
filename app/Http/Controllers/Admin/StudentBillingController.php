<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Reporting\Services\ExportService;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\BillingReconciliationService;
use App\Domains\Students\Services\StudentIdentityService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentBillingController extends Controller
{
    /**
     * Cashier Hub: Operations desk for manual accounting, payments, installments, and packages.
     */
    public function cashier(Request $request, StudentLedgerService $ledger): View
    {
        $search = trim((string) $request->query('q', ''));
        $selectedStudentId = (int) $request->query('student_id', 0);

        $studentsQuery = Student::query()
            ->where(fn ($q) => $q->where('identity_status', '!=', 'merged')->orWhereNull('identity_status'))
            ->with([
                'packages' => fn ($q) => $q->with(['payments', 'refunds', 'ledgerEntries'])->orderByDesc('id'),
            ]);

        if ($search !== '') {
            $identityService = app(StudentIdentityService::class);
            $normName = $identityService->normalizeName($search);
            $normEmail = $identityService->normalizeEmail($search);
            $normPhone = null;
            try {
                $normPhone = $identityService->normalizePhone($search);
            } catch (\Throwable) {
                $normPhone = mb_strtolower(trim($search), 'UTF-8');
            }

            $studentsQuery->where(function ($q) use ($search, $normName, $normEmail, $normPhone) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('name_normalized', 'like', "%{$normName}%")
                    ->orWhere('email', 'like', "%{$search}%");
                if ($normEmail) {
                    $q->orWhere('email_normalized', 'like', "%{$normEmail}%");
                }
                $q->orWhere('phone', 'like', "%{$search}%");
                if ($normPhone) {
                    $q->orWhere('phone_normalized', 'like', "%{$normPhone}%");
                }
            });
        }

        $students = $studentsQuery->orderBy('first_name')->orderBy('last_name')->paginate(15)->withQueryString();

        // Calculate exact remaining balance via bcmath for every package
        $students->getCollection()->transform(function (Student $student) use ($ledger) {
            foreach ($student->packages as $pkg) {
                $summary = $ledger->summary($pkg);
                $pkg->remaining_balance = $summary['balance_due'];
                $pkg->setAttribute('overpaid', $summary['overpaid']);
                $pkg->setAttribute('net_paid', $summary['net_paid']);
                $pkg->available_credits = $summary['remaining_credits'];
                $pkg->is_paid_in_full = bccomp($pkg->remaining_balance, '0.00', 2) <= 0;
            }

            return $student;
        });

        $selectedStudent = $selectedStudentId > 0
            ? Student::with(['packages.payments', 'packages.refunds', 'packages.ledgerEntries'])->find($selectedStudentId)
            : null;

        $diagnosticEligibility = null;
        if ($selectedStudent) {
            $diagnosticEligibility = $ledger->checkDiagnosticCreditEligibility($selectedStudent->id);
            foreach ($selectedStudent->packages as $pkg) {
                $summary = $ledger->summary($pkg);
                $pkg->remaining_balance = $summary['balance_due'];
                $pkg->setAttribute('overpaid', $summary['overpaid']);
                $pkg->setAttribute('net_paid', $summary['net_paid']);
                $pkg->available_credits = $summary['remaining_credits'];
                $pkg->is_paid_in_full = bccomp($pkg->remaining_balance, '0.00', 2) <= 0;
            }
        }

        return view('admin.billing.cashier', [
            'students' => $students,
            'search' => $search,
            'selectedStudent' => $selectedStudent,
            'diagnosticEligibility' => $diagnosticEligibility,
            'presets' => StudentLedgerService::PRESETS,
        ]);
    }

    /**
     * Dynamic check for 48-Hour Diagnostic Credit eligibility.
     */
    public function checkDiagnosticCredit(int $student, StudentLedgerService $ledger): JsonResponse
    {
        $eligibility = $ledger->checkDiagnosticCreditEligibility($student);

        return response()->json([
            'eligible' => $eligibility !== null,
            'details' => $eligibility,
        ]);
    }

    public function storePackage(Request $request, int $student, StudentLedgerService $ledger, AuditLogService $auditLogs): RedirectResponse
    {
        $presetKey = $request->input('preset_key');
        $preset = $presetKey && isset(StudentLedgerService::PRESETS[$presetKey])
            ? StudentLedgerService::PRESETS[$presetKey]
            : null;

        $validated = $request->validate([
            'preset_key' => ['nullable', Rule::in(array_keys(StudentLedgerService::PRESETS))],
            'package_name' => ['required', 'string', 'max:160'],
            'total_sessions_allocated' => ['required', 'integer', 'min:1', 'max:500'],
            'original_price' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'discount_amount' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'expiration_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'package_idempotency_key' => ['required', 'uuid'],
            'override_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $studentRecord = Student::query()->findOrFail($student);

        // Auto-calculate expiration date using Cairo midnight if not provided but preset is known
        $expirationDate = $validated['expiration_date'] ?? null;
        if (! $expirationDate && $preset && isset($preset['validity_days'])) {
            $expirationDate = $ledger->calculateExpirationDate(CarbonImmutable::now('UTC'), $preset['validity_days']);
        }

        try {
            $package = $ledger->createPackage(
                $studentRecord,
                $validated['package_name'],
                (int) $validated['total_sessions_allocated'],
                $validated['original_price'],
                $validated['discount_amount'],
                strtoupper($validated['currency']),
                $expirationDate,
                $validated['package_idempotency_key'],
                $request->user('web')?->id,
                $validated['preset_key'] ?? null,
                $validated['override_notes'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['package' => $exception->getMessage()]);
        }

        abort_unless($package->wasRecentlyCreated, 409, 'This package request was already processed.');

        if ($package->wasRecentlyCreated) {
            $auditLogs->log('student_package_created', StudentPackage::class, $package->id, null, [
                'student_id' => $studentRecord->id,
                'student_package_id' => $package->id,
                'total_sessions_allocated' => $package->total_sessions_allocated,
                'original_price' => $package->original_price,
                'discount_amount' => $package->discount_amount,
                'final_price' => $package->final_price,
                'currency' => $package->currency,
                'expiration_date' => $package->expiration_date?->toDateString(),
                'override_notes' => $validated['override_notes'] ?? null,
            ], $request->user('web')?->id);
        }

        return back()->with('success', 'Session package saved.');
    }

    public function storePayment(Request $request, int $student, int $package, StudentLedgerService $ledger, AuditLogService $auditLogs): RedirectResponse
    {
        $idempotencyField = 'payment_idempotency_key_'.$package;
        if (! $request->has($idempotencyField) && $request->has('payment_idempotency_key')) {
            $idempotencyField = 'payment_idempotency_key';
        }

        $validated = $request->validate([
            'amount_paid' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'payment_method' => ['required', 'string', 'max:80'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
            $idempotencyField => ['required', 'uuid'],
        ]);
        $packageRecord = StudentPackage::query()->where('student_id', $student)->findOrFail($package);

        try {
            $payment = $ledger->recordPayment(
                $packageRecord,
                $validated['amount_paid'],
                $validated[$idempotencyField],
                $request->user('web')?->id,
                $validated['payment_method'],
                $validated['transaction_reference'] ?? null,
                $validated['notes'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }

        abort_unless($payment->wasRecentlyCreated, 409, 'This payment request was already processed.');

        if ($payment->wasRecentlyCreated) {
            $auditLogs->log('student_payment_recorded', PaymentRecord::class, $payment->id, null, [
                'student_id' => $student,
                'student_package_id' => $packageRecord->id,
                'payment_record_id' => $payment->id,
                'amount_paid' => $payment->amount_paid,
                'currency' => $payment->currency,
            ], $request->user('web')?->id);
        }

        return back()->with('success', 'Payment record saved.');
    }

    public function storeRefund(Request $request, int $student, int $payment, StudentLedgerService $ledger, AuditLogService $auditLogs): RedirectResponse
    {
        $idempotencyField = 'refund_idempotency_key_'.$payment;
        if (! $request->has($idempotencyField) && $request->has('refund_idempotency_key')) {
            $idempotencyField = 'refund_idempotency_key';
        }

        $validated = $request->validate([
            'amount_refunded' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'reason' => ['nullable', 'string', 'max:4000'],
            'forfeit_credits' => ['nullable', 'integer', 'min:0', 'max:500'],
            $idempotencyField => ['required', 'uuid'],
        ]);
        $paymentRecord = PaymentRecord::query()->where('student_id', $student)->findOrFail($payment);

        try {
            $refund = $ledger->refund(
                $paymentRecord,
                $validated['amount_refunded'],
                $validated[$idempotencyField],
                $request->user('web')?->id,
                $validated['reason'] ?? null,
                (int) ($validated['forfeit_credits'] ?? 0),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['refund' => $exception->getMessage()]);
        }

        abort_unless($refund->wasRecentlyCreated, 409, 'This refund request was already processed.');

        if ($refund->wasRecentlyCreated) {
            $auditLogs->log('student_payment_refunded', PaymentRecord::class, $paymentRecord->id, null, [
                'student_id' => $student,
                'student_package_id' => $refund->student_package_id,
                'payment_record_id' => $paymentRecord->id,
                'payment_refund_id' => $refund->id,
                'amount_refunded' => $refund->amount_refunded,
                'currency' => $refund->currency,
                'forfeited_credits' => (int) ($validated['forfeit_credits'] ?? 0),
            ], $request->user('web')?->id);
        }

        $forfeitMsg = ((int) ($validated['forfeit_credits'] ?? 0)) > 0
            ? ' Refund saved and '.(int) $validated['forfeit_credits'].' credits forfeited.'
            : ' Refund record saved. Credits were not changed.';

        return back()->with('success', $forfeitMsg);
    }

    public function adjustCredits(Request $request, int $student, int $package, StudentLedgerService $ledger, AuditLogService $auditLogs): RedirectResponse
    {
        $idempotencyField = 'credit_idempotency_key_'.$package;
        if (! $request->has($idempotencyField) && $request->has('credit_idempotency_key')) {
            $idempotencyField = 'credit_idempotency_key';
        }

        $validated = $request->validate([
            'credit_change' => ['required', 'integer', 'between:-500,500', 'not_in:0'],
            'description' => ['required', 'string', 'max:255'],
            $idempotencyField => ['required', 'uuid'],
        ]);
        $packageRecord = StudentPackage::query()->where('student_id', $student)->findOrFail($package);

        try {
            $entry = $ledger->adjustCredits(
                $packageRecord,
                (int) $validated['credit_change'],
                $validated['description'],
                $validated[$idempotencyField],
                $request->user('web')?->id,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['credit_change' => $exception->getMessage()]);
        }

        abort_unless($entry->wasRecentlyCreated, 409, 'This credit adjustment was already processed.');

        if ($entry->wasRecentlyCreated) {
            $auditLogs->log('student_credit_adjusted', StudentPackage::class, $packageRecord->id, null, [
                'student_id' => $student,
                'student_package_id' => $packageRecord->id,
                'ledger_entry_id' => $entry->id,
                'credit_change' => $entry->credit_change,
            ], $request->user('web')?->id);
        }

        return back()->with('success', 'Credit adjustment recorded in the append-only ledger.');
    }

    /**
     * Read-Only Reconciliation Diagnostic View.
     */
    public function reconcile(BillingReconciliationService $reconciliation): View
    {
        $report = $reconciliation->reconcile();

        return view('admin.billing.reconcile', [
            'report' => $report,
        ]);
    }

    /**
     * Dual-format streaming financial ledger export (CSV & XLSX).
     */
    public function exportFinancials(Request $request, ExportService $exportService): StreamedResponse|BinaryFileResponse
    {
        $format = $request->query('format', 'csv');
        $headers = [
            'Date',
            'Student Name',
            'Student Email',
            'Transaction Type',
            'Package Name',
            'Amount Paid',
            'Currency',
            'Payment Method',
            'Reference ID',
            'Admin Logged',
        ];

        $rowsGenerator = function () {
            // 1. Payments
            $payments = PaymentRecord::query()
                ->with(['student', 'package'])
                ->orderByDesc('paid_at');

            foreach ($payments->lazy(500) as $pmt) {
                yield [
                    $pmt->paid_at?->toIso8601String() ?? '',
                    $pmt->student?->name ?? 'Unknown',
                    $pmt->student?->email ?? 'Unknown',
                    'Payment',
                    $pmt->package?->package_name ?? 'N/A',
                    (string) $pmt->amount_paid,
                    $pmt->currency,
                    $pmt->payment_method,
                    $pmt->transaction_reference ?? 'N/A',
                    (string) ($pmt->recorded_by ?? 'System'),
                ];
            }

            // 2. Refunds
            $refunds = PaymentRefund::query()
                ->with(['student', 'package'])
                ->orderByDesc('refunded_at');

            foreach ($refunds->lazy(500) as $ref) {
                yield [
                    $ref->refunded_at?->toIso8601String() ?? '',
                    $ref->student?->name ?? 'Unknown',
                    $ref->student?->email ?? 'Unknown',
                    'Refund',
                    $ref->package?->package_name ?? 'N/A',
                    '-'.(string) $ref->amount_refunded,
                    $ref->currency,
                    'Refund',
                    'Refund #'.$ref->id.' (Payment #'.$ref->payment_record_id.')',
                    (string) ($ref->recorded_by ?? 'System'),
                ];
            }

            // 3. Courtesy Adjustments
            $adjustments = SessionLedgerEntry::query()
                ->where('entry_type', 'courtesy_adjustment')
                ->with(['student', 'package'])
                ->orderByDesc('created_at');

            foreach ($adjustments->lazy(500) as $adj) {
                yield [
                    $adj->created_at?->toIso8601String() ?? '',
                    $adj->student?->name ?? 'Unknown',
                    $adj->student?->email ?? 'Unknown',
                    'Courtesy Credit',
                    $adj->package?->package_name ?? 'N/A',
                    ($adj->credit_change > 0 ? '+' : '').$adj->credit_change.' credits',
                    $adj->package?->currency ?? 'USD',
                    'Courtesy Grant',
                    $adj->idempotency_key,
                    (string) ($adj->created_by ?? 'System'),
                ];
            }
        };

        return $exportService->export(
            'financial_ledger_'.now('Africa/Cairo')->toDateString(),
            $headers,
            $rowsGenerator(),
            $format,
            'Financial Ledger'
        );
    }

    /**
     * Dual-format streaming export for reconciliation diagnostics.
     */
    public function exportReconciliation(Request $request, BillingReconciliationService $reconciliation, ExportService $exportService): StreamedResponse|BinaryFileResponse
    {
        $format = $request->query('format', 'csv');
        $report = $reconciliation->reconcile();

        $headers = ['Discrepancy Category', 'Record ID', 'Student ID', 'Student Name', 'Details', 'Issue Description'];

        $rowsGenerator = function () use ($report) {
            foreach ($report['negative_balances'] as $item) {
                yield [
                    'Negative Derived Balance',
                    (string) $item['package_id'],
                    (string) $item['student_id'],
                    $item['student_name'],
                    "Package: {$item['package_name']} | Final: {$item['final_price']} | Net Paid: {$item['net_paid']} | Remaining: {$item['remaining_balance']}",
                    $item['issue'],
                ];
            }

            foreach ($report['excess_refunds'] as $item) {
                yield [
                    'Excess Refund',
                    (string) $item['payment_id'],
                    (string) $item['student_id'],
                    $item['student_name'],
                    "Paid: {$item['amount_paid']} | Total Refunded: {$item['total_refunded']} | Excess: {$item['excess_amount']}",
                    $item['issue'],
                ];
            }

            foreach ($report['credit_mismatches'] as $item) {
                yield [
                    'Credit Allocation Mismatch',
                    (string) $item['package_id'],
                    (string) $item['student_id'],
                    $item['student_name'],
                    "Package: {$item['package_name']} | Allocated: {$item['allocated_sessions']} | Grants Sum: {$item['granted_credits']}",
                    $item['issue'],
                ];
            }

            foreach ($report['ownership_inconsistencies'] as $item) {
                yield [
                    'Ownership Inconsistency',
                    (string) $item['record_id'],
                    'N/A',
                    'N/A',
                    $item['details'],
                    $item['type'],
                ];
            }
        };

        return $exportService->export(
            'billing_reconciliation_'.now('Africa/Cairo')->toDateString(),
            $headers,
            $rowsGenerator(),
            $format,
            'Reconciliation Report'
        );
    }
}
