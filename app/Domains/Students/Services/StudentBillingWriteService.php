<?php

namespace App\Domains\Students\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use DateTimeInterface;

class StudentBillingWriteService
{
    public function __construct(private StudentLedgerService $ledger, private AuditLogService $audit, private DatabaseCapability $database) {}

    /** @param array<string, mixed> $data */
    public function createPackage(Student $student, array $data, ?int $administratorId): StudentPackage
    {
        return $this->database->transaction(function () use ($student, $data, $administratorId): StudentPackage {
            $expiration = $data['expiration_date'] ?? null;
            $preset = StudentLedgerService::PRESETS[$data['preset_key'] ?? ''] ?? null;
            if ($expiration === null && $preset && $preset['validity_days'] !== null && trim($data['override_notes'] ?? '') !== '') {
                $expiration = $this->ledger->calculateExpirationDate(now('UTC'), $preset['validity_days']);
            }
            $package = $this->ledger->createPackage(
                student: $student, name: $data['package_name'], sessions: (int) $data['total_sessions_allocated'],
                originalPrice: $data['original_price'], discountAmount: $data['discount_amount'], currency: strtoupper($data['currency']),
                expirationDate: $expiration, idempotencyKey: $data['package_idempotency_key'], administratorId: $administratorId,
                presetKey: $data['preset_key'] ?? null, overrideNotes: $data['override_notes'] ?? null,
                entitlementCode: $data['entitlement_code'] ?? null, diagnosticBookingId: isset($data['diagnostic_booking_id']) ? (int) $data['diagnostic_booking_id'] : null,
            );
            if ($package->wasRecentlyCreated) {
                $this->audit->log('student_package_created', StudentPackage::class, $package->id, null, [
                    'student_id' => $student->id, 'student_package_id' => $package->id,
                    'total_sessions_allocated' => $package->total_sessions_allocated, 'original_price' => $package->original_price,
                    'discount_amount' => $package->discount_amount, 'final_price' => $package->final_price, 'currency' => $package->currency,
                    'expiration_date' => $package->expiration_date?->toDateString(), 'override_notes' => $data['override_notes'] ?? null,
                ], $administratorId);
            }

            return $package;
        }, 5);
    }

    /** @param array<string, mixed> $data */
    public function recordPayment(StudentPackage $package, array $data, string $idempotencyKey, int $methodId, ?DateTimeInterface $receipt, ?int $administratorId): PaymentRecord
    {
        return $this->database->transaction(function () use ($package, $data, $idempotencyKey, $methodId, $receipt, $administratorId): PaymentRecord {
            $payment = $this->ledger->recordPayment(
                package: $package, amount: $data['amount_paid'], idempotencyKey: $idempotencyKey, administratorId: $administratorId,
                method: $data['payment_method'], reference: $data['transaction_reference'] ?? null,
                notes: $data['notes'] ?? null, paymentMethodId: $methodId, receivedAt: $receipt,
            );
            if ($payment->wasRecentlyCreated) {
                $currentPackage = $package->fresh();
                $this->audit->log('student_payment_recorded', PaymentRecord::class, $payment->id, null, [
                    'student_id' => $payment->student_id, 'student_package_id' => $payment->student_package_id,
                    'payment_record_id' => $payment->id, 'amount_paid' => $payment->amount_paid,
                    'paid_at' => $payment->paid_at->utc()->toDateTimeString(),
                    'validity_started_at' => $currentPackage->validity_started_at?->toDateTimeString(),
                    'expires_at' => $currentPackage->expires_at?->toDateTimeString(), 'currency' => $payment->currency,
                ], $administratorId);
            }

            return $payment;
        }, 5);
    }
}
