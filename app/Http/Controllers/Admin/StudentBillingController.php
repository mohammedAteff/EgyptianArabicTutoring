<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\StudentLedgerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class StudentBillingController extends Controller
{
    public function storePackage(Request $request, int $student, StudentLedgerService $ledger, AuditLogService $auditLogs): RedirectResponse
    {
        $validated = $request->validate([
            'package_name' => ['required', 'string', 'max:160'],
            'total_sessions_allocated' => ['required', 'integer', 'min:1', 'max:500'],
            'original_price' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'discount_amount' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'expiration_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'package_idempotency_key' => ['required', 'string', 'min:16', 'max:80'],
        ]);
        $studentRecord = Student::query()->findOrFail($student);

        try {
            $package = $ledger->createPackage(
                $studentRecord,
                $validated['package_name'],
                (int) $validated['total_sessions_allocated'],
                $validated['original_price'],
                $validated['discount_amount'],
                strtoupper($validated['currency']),
                $validated['expiration_date'] ?? null,
                $validated['package_idempotency_key'],
                $request->user('web')?->id,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['package' => $exception->getMessage()]);
        }

        if ($package->wasRecentlyCreated) {
            $auditLogs->log('student_package_created', StudentPackage::class, $package->id, null, [
                'student_id' => $studentRecord->id,
                'student_package_id' => $package->id,
                'total_sessions_allocated' => $package->total_sessions_allocated,
                'final_price' => $package->final_price,
                'currency' => $package->currency,
            ], $request->user('web')?->id);
        }

        return back()->with('success', 'Session package saved.');
    }

    public function storePayment(Request $request, int $student, int $package, StudentLedgerService $ledger, AuditLogService $auditLogs): RedirectResponse
    {
        $idempotencyField = 'payment_idempotency_key_'.$package;
        $validated = $request->validate([
            'amount_paid' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'payment_method' => ['required', 'string', 'max:80'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
            $idempotencyField => ['required', 'string', 'min:16', 'max:80'],
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
        $validated = $request->validate([
            'amount_refunded' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'reason' => ['nullable', 'string', 'max:4000'],
            $idempotencyField => ['required', 'string', 'min:16', 'max:80'],
        ]);
        $paymentRecord = PaymentRecord::query()->where('student_id', $student)->findOrFail($payment);

        try {
            $refund = $ledger->refund(
                $paymentRecord,
                $validated['amount_refunded'],
                $validated[$idempotencyField],
                $request->user('web')?->id,
                $validated['reason'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['refund' => $exception->getMessage()]);
        }

        if ($refund->wasRecentlyCreated) {
            $auditLogs->log('student_payment_refunded', PaymentRecord::class, $paymentRecord->id, null, [
                'student_id' => $student,
                'student_package_id' => $refund->student_package_id,
                'payment_record_id' => $paymentRecord->id,
                'payment_refund_id' => $refund->id,
                'amount_refunded' => $refund->amount_refunded,
                'currency' => $refund->currency,
            ], $request->user('web')?->id);
        }

        return back()->with('success', 'Refund record saved. Credits were not changed.');
    }

    public function adjustCredits(Request $request, int $student, int $package, StudentLedgerService $ledger, AuditLogService $auditLogs): RedirectResponse
    {
        $idempotencyField = 'credit_idempotency_key_'.$package;
        $validated = $request->validate([
            'credit_change' => ['required', 'integer', 'between:-500,500', 'not_in:0'],
            'description' => ['required', 'string', 'max:255'],
            $idempotencyField => ['required', 'string', 'min:16', 'max:80'],
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
}
