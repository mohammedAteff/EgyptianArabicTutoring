<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Services\OperationalReasonCatalog;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Reporting\Services\ExportService;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\PaymentMethod;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\BillingReconciliationService;
use App\Domains\Students\Services\CashierReportService;
use App\Domains\Students\Services\ReconciliationReport;
use App\Domains\Students\Services\StudentIdentityService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
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
    public function cashier(Request $request, StudentLedgerService $ledger, CashierReportService $report): View
    {
        $filters = $report->filters($request);
        $search = trim((string) $request->query('q', ''));
        $selectedStudentId = (int) $request->query('student_id', 0);

        $studentsQuery = Student::query()
            ->where(fn ($q) => $q->where('identity_status', '!=', 'merged')->orWhereNull('identity_status'))
            ->with([
                'packages' => fn ($q) => $q->with(['payments', 'refunds', 'ledgerEntries.type', 'entitlements.type'])->orderByDesc('id'),
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
                $summary = $ledger->summary($pkg, true);
                $pkg->remaining_balance = $summary['balance_due'];
                $pkg->setAttribute('overpaid', $summary['overpaid']);
                $pkg->setAttribute('net_paid', $summary['net_paid']);
                $pkg->available_credits = $summary['remaining_credits'];
                $pkg->setAttribute('credit_summary', $summary);
                $pkg->is_paid_in_full = bccomp($pkg->remaining_balance, '0.00', 2) <= 0;
            }

            return $student;
        });

        $selectedStudent = $selectedStudentId > 0
            ? Student::with(['packages.payments', 'packages.refunds', 'packages.ledgerEntries.type', 'packages.entitlements.type'])->find($selectedStudentId)
            : null;

        $diagnosticEligibility = null;
        if ($selectedStudent) {
            $diagnosticEligibility = $ledger->checkDiagnosticCreditEligibility($selectedStudent->id);
            foreach ($selectedStudent->packages as $pkg) {
                $summary = $ledger->summary($pkg, true);
                $pkg->remaining_balance = $summary['balance_due'];
                $pkg->setAttribute('overpaid', $summary['overpaid']);
                $pkg->setAttribute('net_paid', $summary['net_paid']);
                $pkg->available_credits = $summary['remaining_credits'];
                $pkg->setAttribute('credit_summary', $summary);
                $pkg->is_paid_in_full = bccomp($pkg->remaining_balance, '0.00', 2) <= 0;
                foreach ($pkg->payments as $payment) {
                    $payment->setAttribute('refundable_amount', $ledger->refundableAmount($payment, $pkg));
                }
            }
        }

        return view('admin.billing.cashier', [
            'transactionHeaders' => $report->headers(),
            'transactionRows' => LazyCollection::make(fn () => $report->rows($filters))->take(100)->collect(),
            'filters' => $filters,
            'studentOptions' => Student::query()->where('identity_status', '!=', 'merged')->orderBy('name_normalized')->limit(500)->get(['id', 'first_name', 'last_name'])->mapWithKeys(fn ($student) => [$student->id => $student->first_name.' '.$student->last_name])->all(),
            'packageOptions' => collect(StudentLedgerService::PRESETS)->mapWithKeys(fn (array $preset): array => [$preset['key'] => $preset['name']])->all() + ['custom_unclassified' => 'Custom / Unclassified'],
            'entitlementTypes' => EntitlementType::query()->where('active', true)->get(),
            'methodOptions' => PaymentRecord::query()->whereNotNull('payment_method')->distinct()->orderBy('payment_method')->limit(100)->pluck('payment_method', 'payment_method')->all(),
            'businessTz' => app(TimezoneService::class)->getBusinessTimezone(),
            'creditPackages' => $selectedStudent?->packages->map(fn ($package) => ['package' => $package, 'summary' => $ledger->summary($package, true)]) ?? collect(),
            'students' => $students,
            'search' => $search,
            'selectedStudent' => $selectedStudent,
            'diagnosticEligibility' => $diagnosticEligibility,
            'presets' => StudentLedgerService::PRESETS,
            'paymentMethods' => PaymentMethod::available()->get(),
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
            'entitlement_code' => ['required_without:preset_key', 'nullable', 'exists:entitlement_types,code'],
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
        if (! $expirationDate && $preset) {
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
                $validated['entitlement_code'] ?? null,
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
            'received_at' => ['nullable', 'date_format:Y-m-d\\TH:i:s,Y-m-d\\TH:i'],
            'received_at_timezone' => ['nullable', Rule::in([app(TimezoneService::class)->getBusinessTimezone(), 'UTC'])],
            'payment_method' => ['required', 'string', 'max:80'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
            $idempotencyField => ['required', 'uuid'],
        ]);
        $method = PaymentMethod::query()->where('active', true)->where(function ($query) use ($validated): void {
            $query->where('name', $validated['payment_method']);
            if (ctype_digit($validated['payment_method'])) {
                $query->orWhere('id', (int) $validated['payment_method']);
            }
        })->first();
        if (! $method) {
            throw ValidationException::withMessages(['payment_method' => 'Choose an enabled payment method.']);
        }
        $packageRecord = StudentPackage::query()->where('student_id', $student)->findOrFail($package);

        $receiptTimezone = $validated['received_at_timezone'] ?? app(TimezoneService::class)->getBusinessTimezone();
        $receiptFormat = strlen((string) ($validated['received_at'] ?? '')) === 16 ? '!Y-m-d\\TH:i' : '!Y-m-d\\TH:i:s';
        $receipt = $request->date('received_at', $receiptFormat, $receiptTimezone);
        if ($receipt && $receipt->format(ltrim($receiptFormat, '!')) !== $validated['received_at']) {
            throw ValidationException::withMessages(['received_at' => 'This local clock time does not exist. Choose the actual receipt time in UTC.']);
        }
        if ($receipt && $receiptTimezone !== 'UTC') {
            $wallTime = CarbonImmutable::createFromFormat($receiptFormat, $validated['received_at'], 'UTC')->getTimestamp();
            $transitions = (new \DateTimeZone($receiptTimezone))->getTransitions($receipt->getTimestamp() - 86400, $receipt->getTimestamp() + 86400);
            $previousOffset = $transitions[0]['offset'] ?? 0;
            foreach (array_slice($transitions ?: [], 1) as $transition) {
                if ($transition['offset'] < $previousOffset && $wallTime >= $transition['ts'] + $transition['offset'] && $wallTime < $transition['ts'] + $previousOffset) {
                    throw ValidationException::withMessages(['received_at' => 'This local time occurs twice when clocks change. Select UTC and enter the actual receipt instant.']);
                }
                $previousOffset = $transition['offset'];
            }
        }
        try {
            $payment = $ledger->recordPayment(
                $packageRecord,
                $validated['amount_paid'],
                $validated[$idempotencyField],
                $request->user('web')?->id,
                $validated['payment_method'],
                $validated['transaction_reference'] ?? null,
                $validated['notes'] ?? null,
                $method->id,
                $receipt?->utc(),
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
                'paid_at' => $payment->paid_at->utc()->toDateTimeString(),
                'validity_started_at' => $packageRecord->fresh()->validity_started_at?->toDateTimeString(),
                'expires_at' => $packageRecord->fresh()->expires_at?->toDateTimeString(),
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
            'reason' => ['nullable', 'string', 'max:4000'], 'reason_code' => ['nullable', 'string', 'max:40'],
            'allocation_id' => ['required_if:forfeit_credits,1', 'nullable', 'integer'],
            'forfeit_credits' => ['nullable', 'integer', 'min:0', 'max:500'],
            $idempotencyField => ['required', 'uuid'],
        ]);

        return app(DatabaseCapability::class)->transaction(function () use ($request, $student, $payment, $ledger, $auditLogs, $validated, $idempotencyField): RedirectResponse {
            OperationalReasonCatalog::validate($validated['reason_code'] ?? null, 'refund');
            $paymentRecord = PaymentRecord::query()->where('student_id', $student)->findOrFail($payment);

            try {
                $refund = $ledger->refund(
                    $paymentRecord,
                    $validated['amount_refunded'],
                    $validated[$idempotencyField],
                    $request->user('web')?->id,
                    $validated['reason'] ?? null,
                    (int) ($validated['forfeit_credits'] ?? 0),
                    isset($validated['allocation_id']) ? (int) $validated['allocation_id'] : null,
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
                    'reason_code' => $validated['reason_code'] ?? null, 'reason' => $validated['reason'] ?? null,
                    'amount_refunded' => $refund->amount_refunded,
                    'currency' => $refund->currency,
                    'forfeited_credits' => (int) ($validated['forfeit_credits'] ?? 0),
                ], $request->user('web')?->id);
            }

            $forfeitMsg = ((int) ($validated['forfeit_credits'] ?? 0)) > 0
                ? ' Refund saved and '.(int) $validated['forfeit_credits'].' credits forfeited.'
                : ' Refund record saved. Credits were not changed.';

            return back()->with('success', $forfeitMsg);
        }, 5);
    }

    public function extendValidity(Request $request, int $student, int $package, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['expiration_date' => ['required', 'date_format:Y-m-d'], 'previous_expiration_date' => ['nullable', 'date_format:Y-m-d'], 'previous_expires_at' => ['nullable', 'date_format:Y-m-d H:i:s'], 'reason' => ['required', 'string', 'max:1000'], 'reason_code' => ['nullable', 'string', 'max:40']]);
        OperationalReasonCatalog::validate($data['reason_code'] ?? null, 'validity_extension');
        DB::transaction(function () use ($request, $student, $package, $data, $audit): void {
            Student::query()->whereKey($student)->lockForUpdate()->firstOrFail();
            $record = StudentPackage::query()->where('student_id', $student)->whereKey($package)->lockForUpdate()->firstOrFail();
            $oldDate = $record->expiration_date?->toDateString();
            if ($oldDate !== ($data['previous_expiration_date'] ?? null)) {
                throw ValidationException::withMessages(['expiration_date' => 'The validity date changed. Reload before extending it.']);
            }
            if (! $oldDate || $data['expiration_date'] <= $oldDate) {
                throw ValidationException::withMessages(['expiration_date' => 'Choose a date later than the current expiration.']);
            }
            $oldExpiry = $record->expires_at?->toDateTimeString();
            $newData = ['expiration_date' => $data['expiration_date']];
            if ($oldExpiry !== null) {
                if ($oldExpiry !== ($data['previous_expires_at'] ?? null)) {
                    throw ValidationException::withMessages(['expiration_date' => 'The exact expiry changed. Reload before extending it.']);
                }
                $newExpiry = CarbonImmutable::parse($data['expiration_date'], app(TimezoneService::class)->getBusinessTimezone())->endOfDay()->utc();
                if ($newExpiry->lessThanOrEqualTo($record->expires_at)) {
                    throw ValidationException::withMessages(['expiration_date' => 'An extension must move the deadline later.']);
                }
                $newData['expires_at'] = $newExpiry->toDateTimeString();
            }
            $record->update($newData);
            $audit->log('package_validity_extended', StudentPackage::class, $record->id, ['expiration_date' => $oldDate, 'expires_at' => $oldExpiry], $newData + ['reason' => $data['reason'], 'reason_code' => $data['reason_code'] ?? null], $request->user('web')->id);
        }, 5);

        return back()->with('success', 'Package validity extended. Credit ledger entries were preserved.');
    }

    public function adjustCredits(Request $request, int $student, int $package, StudentLedgerService $ledger, AuditLogService $auditLogs): RedirectResponse
    {
        $idempotencyField = 'credit_idempotency_key_'.$package;
        if (! $request->has($idempotencyField) && $request->has('credit_idempotency_key')) {
            $idempotencyField = 'credit_idempotency_key';
        }

        $packageRecord = StudentPackage::query()->where('student_id', $student)->findOrFail($package);
        $validated = $request->validate([
            'allocation_id' => ['required', 'integer'],
            'credit_change' => ['required', 'integer', 'between:-500,500', 'not_in:0'],
            'description' => ['required', 'string', 'max:255'], 'reason_code' => ['nullable', 'string', 'max:40'],
            $idempotencyField => ['required', 'uuid'],
        ]);

        return app(DatabaseCapability::class)->transaction(function () use ($request, $student, $packageRecord, $ledger, $auditLogs, $validated, $idempotencyField): RedirectResponse {
            OperationalReasonCatalog::validate($validated['reason_code'] ?? null, 'courtesy_credit');
            try {
                $entry = $ledger->adjustCredits(
                    $packageRecord,
                    (int) $validated['credit_change'],
                    $validated['description'],
                    $validated[$idempotencyField],
                    $request->user('web')?->id,
                    (int) $validated['allocation_id'],
                );
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['credit_change' => $exception->getMessage()]);
            }

            abort_unless($entry->wasRecentlyCreated, 409, 'This credit adjustment was already processed.');

            if ($entry->wasRecentlyCreated) {
                $auditLogs->log('student_credit_adjusted', StudentPackage::class, $packageRecord->id, null, [
                    'student_id' => $student,
                    'student_package_id' => $packageRecord->id,
                    'ledger_entry_id' => $entry->id, 'reason_code' => $validated['reason_code'] ?? null, 'description' => $validated['description'],
                    'credit_change' => $entry->credit_change,
                ], $request->user('web')?->id);
            }

            return back()->with('success', 'Credit adjustment recorded in the append-only ledger.');
        }, 5);
    }

    /**
     * Read-Only Reconciliation Diagnostic View.
     */
    public function reconcile(Request $request, BillingReconciliationService $reconciliation): View
    {
        $report = $reconciliation->reconcile();
        $scope = app(ReconciliationReport::class);
        $filters = $scope->filters($request);
        $rows = $scope->rows($report, $filters);

        return view('admin.billing.reconcile', [
            'report' => $report, 'filters' => $filters, 'rows' => $rows, 'headers' => $scope->headers(),
        ]);
    }

    /**
     * Filtered financial ledger export: streamed CSV or a bounded in-memory XLSX workbook.
     */
    public function exportFinancials(Request $request, ExportService $exportService): StreamedResponse|BinaryFileResponse
    {
        $report = app(CashierReportService::class);
        $filters = $report->filters($request);

        return $exportService->export(
            'financial_ledger_'.now(app(TimezoneService::class)->getBusinessTimezone())->toDateString(),
            $report->headers(), $report->rows($filters), $filters['format'] ?? 'csv', 'Financial Ledger',
        );
    }

    /**
     * Filtered reconciliation export: streamed CSV or a bounded in-memory XLSX workbook.
     */
    public function exportReconciliation(Request $request, BillingReconciliationService $reconciliation, ExportService $exportService): StreamedResponse|BinaryFileResponse
    {
        $scope = app(ReconciliationReport::class);
        $filters = $scope->filters($request);

        return $exportService->export('billing_reconciliation_'.now()->toDateString(), $scope->headers(), $scope->rows($reconciliation->reconcile(), $filters), $filters['format'] ?? 'csv', 'Reconciliation Report');
    }
}
