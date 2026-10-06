<?php

namespace App\Domains\Students\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\PaymentMethod;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Models\StudentPackageEntitlement;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StudentLedgerService
{
    public const PRESETS = [
        'diagnostic_roadmap' => [
            'key' => 'diagnostic_roadmap',
            'entitlement_code' => 'one_hour',
            'name' => 'Diagnostic & Roadmap',
            'sessions' => 1,
            'duration_minutes' => 60,
            'price' => '25.00',
            'validity_days' => 14,
            'audience' => 'Mandatory Entry',
        ],
        'foundation_track' => [
            'key' => 'foundation_track',
            'entitlement_code' => 'two_hour',
            'name' => 'Foundation Coaching Track',
            'sessions' => 8,
            'duration_minutes' => 120,
            'price' => '280.00',
            'discounted_price' => '255.00',
            'validity_days' => 75,
            'audience' => 'Core Track',
        ],
        'fluency_track' => [
            'key' => 'fluency_track',
            'entitlement_code' => 'two_hour',
            'name' => 'Fluency Immersion Track',
            'sessions' => 12,
            'duration_minutes' => 120,
            'price' => '390.00',
            'discounted_price' => '365.00',
            'validity_days' => 100,
            'audience' => 'Core Track (Priority)',
        ],
        'payg_maintenance' => [
            'key' => 'payg_maintenance',
            'entitlement_code' => 'two_hour',
            'name' => 'Pay-As-You-Go Maintenance',
            'sessions' => 1,
            'duration_minutes' => 120,
            'price' => '48.00',
            'validity_days' => 30,
            'audience' => 'Alumni Only (Unlisted)',
        ],
        'advanced_conversational' => [
            'key' => 'advanced_conversational',
            'entitlement_code' => 'one_hour',
            'name' => 'Advanced Conversational',
            'sessions' => 1,
            'duration_minutes' => 60,
            'price' => '28.00',
            'validity_days' => 30,
            'audience' => 'C1+ Debate (Unlisted)',
        ],
    ];

    public function __construct(private TimezoneService $timezones, private DatabaseCapability $database, private EntitlementService $entitlements) {}

    /**
     * Check if a student is eligible for the 48-Hour Diagnostic Credit (-$25.00).
     *
     * @return array{eligible: bool, credit_amount: string, payment_id: int, paid_at: string}|null
     */
    public function checkDiagnosticCreditEligibility(int $studentId): ?array
    {
        $cutoff = CarbonImmutable::now('UTC')->subHours(48);
        $diagnosticPackages = StudentPackage::query()
            ->where('student_id', $studentId)
            ->where('offering_key', 'diagnostic_roadmap')
            ->where('total_sessions_allocated', 1)
            ->where('currency', 'USD')
            ->whereHas('ledgerEntries', function ($query) use ($studentId): void {
                $query->where('entry_type', 'session_consumed')
                    ->whereIn('booking_id', Booking::query()
                        ->where('student_id', $studentId)
                        ->where('status', 'completed')
                        ->select('id'));
            })
            ->with(['payments', 'refunds'])
            ->orderByDesc('id')
            ->get();

        foreach ($diagnosticPackages as $package) {
            $transactions = [];
            foreach ($package->payments as $payment) {
                $transactions[] = ['at' => $payment->paid_at, 'amount' => (string) $payment->amount_paid, 'payment_id' => $payment->id, 'order' => 0];
            }
            foreach ($package->refunds as $refund) {
                $transactions[] = ['at' => $refund->refunded_at, 'amount' => '-'.(string) $refund->amount_refunded, 'payment_id' => null, 'order' => 1];
            }
            usort($transactions, fn (array $first, array $second): int => $first['at']->getTimestamp() <=> $second['at']->getTimestamp()
                ?: $first['order'] <=> $second['order']);

            $netPaid = '0.00';
            $settledAt = null;
            $settlingPaymentId = null;
            foreach ($transactions as $transaction) {
                $previousNet = $netPaid;
                $netPaid = bcadd($netPaid, $transaction['amount'], 2);
                if (bccomp($netPaid, (string) $package->final_price, 2) < 0) {
                    $settledAt = null;
                    $settlingPaymentId = null;
                } elseif (bccomp($previousNet, (string) $package->final_price, 2) < 0 && $transaction['payment_id']) {
                    $settledAt = $transaction['at'];
                    $settlingPaymentId = $transaction['payment_id'];
                }
            }

            if (! $settledAt || $settledAt->lessThan($cutoff) || bccomp($netPaid, '25.00', 2) < 0) {
                continue;
            }

            $alreadyClaimed = StudentPackage::query()
                ->where('student_id', $studentId)
                ->where('created_at', '>=', $package->created_at)
                ->whereIn('offering_key', ['foundation_track', 'fluency_track'])
                ->where('discount_amount', '25.00')
                ->exists();

            if (! $alreadyClaimed) {
                return [
                    'eligible' => true,
                    'credit_amount' => '25.00',
                    'payment_id' => $settlingPaymentId,
                    'paid_at' => $settledAt->toDateTimeString(),
                ];
            }
        }

        return null;
    }

    /**
     * Calculate package expiration date from settlement instant (evaluated at Cairo midnight + validity days).
     */
    public function calculateExpirationDate(?\DateTimeInterface $settlementDate, int $validityDays): string
    {
        $settlement = $settlementDate instanceof CarbonImmutable
            ? $settlementDate
            : CarbonImmutable::instance($settlementDate ?? now('UTC'));

        $cairoMidnight = $settlement->setTimezone($this->timezones->getBusinessTimezone())->startOfDay();

        return $cairoMidnight->addDays($validityDays)->toDateString();
    }

    /**
     * Calculate remaining monetary balance for a package using strict bcmath.
     */
    public function calculateRemainingBalance(StudentPackage $package): string
    {
        $paid = (string) (PaymentRecord::query()->where('student_package_id', $package->id)->sum('amount_paid') ?: '0.00');
        $refunded = (string) (PaymentRefund::query()->where('student_package_id', $package->id)->sum('amount_refunded') ?: '0.00');
        $netPaid = bcsub($paid, $refunded, 2);

        return bcsub((string) $package->final_price, $netPaid, 2);
    }

    public function createPackage(Student $student, string $name, int $sessions, string $originalPrice, string $discountAmount, string $currency, ?string $expirationDate, string $idempotencyKey, ?int $administratorId = null, ?string $presetKey = null, ?string $overrideNotes = null, ?string $entitlementCode = null): StudentPackage
    {
        if ($presetKey !== null && ! isset(self::PRESETS[$presetKey])) {
            throw new InvalidArgumentException('Unknown offering key.');
        }
        $entitlementCode = $presetKey !== null ? self::PRESETS[$presetKey]['entitlement_code'] : $entitlementCode;
        $type = EntitlementType::query()->where('code', $entitlementCode)->where('active', true)->first();
        if (! $type) {
            throw new InvalidArgumentException('Choose an explicit active entitlement type for this purchase.');
        }
        $fingerprint = hash('sha256', json_encode([$name, $sessions, $originalPrice, $discountAmount, strtoupper($currency), $expirationDate, $presetKey, $overrideNotes, $type->code], JSON_THROW_ON_ERROR));
        $originalCents = $this->toCents($originalPrice);
        $discountCents = $this->toCents($discountAmount);
        if ($sessions < 1 || $originalCents < 0 || $discountCents < 0 || $discountCents > $originalCents) {
            throw new InvalidArgumentException('Invalid package terms.');
        }

        return $this->database->transaction(function () use ($student, $name, $sessions, $originalCents, $discountCents, $currency, $expirationDate, $idempotencyKey, $administratorId, $presetKey, $overrideNotes, $type, $fingerprint): StudentPackage {
            $lockedStudent = Student::withTrashed()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            if ($lockedStudent->trashed() || $lockedStudent->identity_status === 'merged') {
                throw new InvalidArgumentException('Packages cannot be assigned to an inactive student record.');
            }
            $existing = SessionLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->entry_type !== 'package_grant' || $existing->student_id !== $student->id) {
                    throw new InvalidArgumentException('Idempotency key was already used.');
                }

                $replay = StudentPackage::findOrFail($existing->student_package_id);
                if ($replay->purchase_fingerprint !== $fingerprint) {
                    throw new InvalidArgumentException('Idempotency payload changed.');
                }

                return $replay;
            }

            $effectiveName = $name;
            $effectiveSessions = $sessions;
            $effectiveOriginalCents = $originalCents;
            $effectiveDiscountCents = $discountCents;
            $effectiveCurrency = strtoupper($currency);
            $effectiveExpirationDate = $expirationDate;
            if ($presetKey !== null && trim((string) $overrideNotes) === '') {
                $preset = self::PRESETS[$presetKey];
                $effectiveName = $preset['name'];
                $effectiveSessions = $preset['sessions'];
                $effectiveOriginalCents = $this->toCents($preset['price']);
                $diagnosticCredit = in_array($presetKey, ['foundation_track', 'fluency_track'], true) ? $this->checkDiagnosticCreditEligibility($student->id) : null;
                $effectiveDiscountCents = max($discountCents, $diagnosticCredit ? $this->toCents($diagnosticCredit['credit_amount']) : 0);
                $effectiveCurrency = 'USD';
                $effectiveExpirationDate = null;
            }

            if ($effectiveDiscountCents > $effectiveOriginalCents) {
                throw new InvalidArgumentException('Discount cannot exceed the package price.');
            }

            $package = StudentPackage::create([
                'offering_key' => $presetKey ?? 'custom',
                'identity_state' => $presetKey === null ? 'custom' : 'mapped',
                'validity_days' => $presetKey !== null && trim((string) $overrideNotes) === '' ? self::PRESETS[$presetKey]['validity_days'] : null,
                'purchase_fingerprint' => $fingerprint,
                'student_id' => $student->id,
                'package_name' => $effectiveName,
                'original_price' => $this->fromCents($effectiveOriginalCents),
                'discount_amount' => $this->fromCents($effectiveDiscountCents),
                'final_price' => $this->fromCents($effectiveOriginalCents - $effectiveDiscountCents),
                'currency' => $effectiveCurrency,
                'total_sessions_allocated' => $effectiveSessions,
                'expiration_date' => $effectiveExpirationDate,
                'status' => 'active',
            ]);
            $allocation = StudentPackageEntitlement::create(['student_package_id' => $package->id, 'student_id' => $student->id, 'entitlement_type_id' => $type->id, 'granted_quantity' => $effectiveSessions]);
            SessionLedgerEntry::create([
                'student_package_entitlement_id' => $allocation->id,
                'entitlement_type_id' => $type->id,
                'student_id' => $student->id,
                'student_package_id' => $package->id,
                'idempotency_key' => $idempotencyKey,
                'entry_type' => 'package_grant',
                'credit_change' => $effectiveSessions,
                'description' => 'Package credit grant',
                'created_by' => $administratorId,
                'created_at' => now('UTC'),
            ]);

            return $package;
        }, 5);
    }

    public function selectAndLockEligiblePackageForBooking(int $studentId, SessionType $session): ?StudentPackage
    {
        return $this->entitlements->selectAndLock($studentId, $session)?->package;
    }

    public function consumeForBooking(Booking $booking, string $idempotencyKey): SessionLedgerEntry
    {
        if (! $booking->student_id || DB::transactionLevel() < 1) {
            throw new InvalidArgumentException('A student booking transaction is required.');
        }
        $existing = SessionLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->orWhere(fn ($q) => $q->where('booking_id', $booking->id)->where('entry_type', 'session_consumed'))->first();
        if ($existing) {
            if ($existing->entry_type !== 'session_consumed' || (int) $existing->booking_id !== (int) $booking->id || $existing->idempotency_key !== $idempotencyKey || (int) $booking->consumed_ledger_entry_id !== (int) $existing->id) {
                throw new InvalidArgumentException('Idempotency key or booking provenance mismatch.');
            }

            return $existing;
        }
        $requirement = $this->entitlements->requirement($booking->sessionType);
        $allocation = $this->entitlements->selectAndLock($booking->student_id, $booking->sessionType);
        if (! $allocation) {
            throw new InvalidArgumentException('No available compatible session credits.');
        }
        $entry = SessionLedgerEntry::create([
            'student_id' => $booking->student_id, 'student_package_id' => $allocation->student_package_id,
            'student_package_entitlement_id' => $allocation->id, 'entitlement_type_id' => $allocation->entitlement_type_id,
            'booking_id' => $booking->id, 'idempotency_key' => $idempotencyKey,
            'entry_type' => 'session_consumed', 'credit_change' => -$requirement['units'],
            'description' => $requirement['type']->label.' used for booking', 'created_at' => now('UTC'),
        ]);
        $booking->update(['funding_mode' => 'package', 'entitlement_type_id' => $allocation->entitlement_type_id,
            'entitlement_code' => $requirement['type']->code, 'entitlement_units' => $requirement['units'],
            'student_package_id' => $allocation->student_package_id, 'student_package_entitlement_id' => $allocation->id,
            'consumed_ledger_entry_id' => $entry->id]);

        return $entry;
    }

    public function restoreCancellation(Booking $booking, string $idempotencyKey, string $description = 'Original entitlement restored after cancellation'): ?SessionLedgerEntry
    {
        if (! $booking->student_id || DB::transactionLevel() < 1) {
            return null;
        }

        $consumed = SessionLedgerEntry::query()->where('booking_id', $booking->id)->where('entry_type', 'session_consumed')->first();
        if (! $consumed) {
            return null;
        }

        $package = StudentPackage::query()->whereKey($consumed->student_package_id)->lockForUpdate()->firstOrFail();
        if ($consumed->credit_change >= 0 || (int) $package->student_id !== (int) $booking->student_id) {
            throw new InvalidArgumentException('Invalid historical debit ownership or quantity.');
        }
        if ($consumed->student_package_entitlement_id !== null) {
            $this->entitlements->lockAllocation($package, $consumed->student_package_entitlement_id);
        }
        $existing = SessionLedgerEntry::query()->where('booking_id', $booking->id)->where('entry_type', 'cancellation_restore')->first();
        if ($existing) {
            return $existing;
        }

        return SessionLedgerEntry::create([
            'student_id' => $booking->student_id,
            'student_package_id' => $package->id,
            'booking_id' => $booking->id,
            'idempotency_key' => $idempotencyKey,
            'entry_type' => 'cancellation_restore',
            'student_package_entitlement_id' => $consumed->student_package_entitlement_id,
            'entitlement_type_id' => $consumed->entitlement_type_id,
            'credit_change' => -$consumed->credit_change,
            'description' => $description,
            'created_at' => now('UTC'),
        ]);
    }

    public function recordPayment(StudentPackage $package, string $amount, string $idempotencyKey, ?int $administratorId, string $method = 'PayPal - Manual', ?string $reference = null, ?string $notes = null, ?int $paymentMethodId = null): PaymentRecord
    {
        $cents = $this->toCents($amount);
        if ($cents <= 0) {
            throw new InvalidArgumentException('Payment amount must be positive.');
        }

        return $this->database->transaction(function () use ($package, $cents, $idempotencyKey, $administratorId, $method, $reference, $notes, $paymentMethodId): PaymentRecord {
            $lockedStudent = Student::withTrashed()->whereKey($package->student_id)->lockForUpdate()->firstOrFail();
            $lockedPackage = StudentPackage::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            if ($lockedStudent->trashed() || $lockedStudent->identity_status === 'merged' || (int) $lockedPackage->student_id !== (int) $lockedStudent->id) {
                throw new InvalidArgumentException('This package is no longer assigned to an active student.');
            }
            $existing = PaymentRecord::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->student_package_id !== $package->id) {
                    throw new InvalidArgumentException('Idempotency key was already used.');
                }

                return $existing;
            }

            if ($paymentMethodId !== null) {
                $configuredMethod = PaymentMethod::query()->whereKey($paymentMethodId)->where('active', true)->lockForUpdate()->first();
                if (! $configuredMethod) {
                    throw new InvalidArgumentException('Choose an enabled payment method.');
                }
                $method = $configuredMethod->name;
            }

            $recordedPayment = PaymentRecord::create([
                'student_package_id' => $package->id,
                'student_id' => $package->student_id,
                'idempotency_key' => $idempotencyKey,
                'amount_paid' => $this->fromCents($cents),
                'currency' => $package->currency,
                'payment_method' => $method,
                'payment_method_id' => $paymentMethodId,
                'transaction_reference' => $reference,
                'paid_at' => now('UTC'),
                'recorded_by' => $administratorId,
                'notes' => $notes,
                'created_at' => now('UTC'),
            ]);

            if ($lockedPackage->validity_days !== null && $lockedPackage->expiration_date === null) {
                $totalPaid = (string) PaymentRecord::query()->where('student_package_id', $lockedPackage->id)->sum('amount_paid');
                $totalRefunded = (string) PaymentRefund::query()->where('student_package_id', $lockedPackage->id)->sum('amount_refunded');
                if (bccomp(bcsub($totalPaid, $totalRefunded, 2), (string) $lockedPackage->final_price, 2) >= 0) {
                    $lockedPackage->update([
                        'expiration_date' => $this->calculateExpirationDate($recordedPayment->paid_at, (int) $lockedPackage->validity_days),
                    ]);
                }
            }

            return $recordedPayment;
        }, 5);
    }

    public function refund(PaymentRecord $payment, string $amount, string $idempotencyKey, ?int $administratorId, ?string $reason = null, int $forfeitCredits = 0, ?int $allocationId = null): PaymentRefund
    {
        $cents = $this->toCents($amount);
        if ($cents <= 0 || $forfeitCredits < 0) {
            throw new InvalidArgumentException('Refund amount must be positive and forfeited credits cannot be negative.');
        }

        return $this->database->transaction(function () use ($payment, $cents, $idempotencyKey, $administratorId, $reason, $forfeitCredits, $allocationId): PaymentRefund {
            $lockedStudent = Student::withTrashed()->whereKey($payment->student_id)->lockForUpdate()->firstOrFail();
            $package = StudentPackage::query()->whereKey($payment->student_package_id)->lockForUpdate()->firstOrFail();
            $allocation = $forfeitCredits > 0 ? $this->entitlements->lockAllocation($package, $allocationId) : null;
            $lockedPayment = PaymentRecord::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($lockedStudent->trashed() || $lockedStudent->identity_status === 'merged'
                || (int) $package->student_id !== (int) $lockedStudent->id
                || (int) $lockedPayment->student_id !== (int) $lockedStudent->id
                || (int) $lockedPayment->student_package_id !== (int) $package->id) {
                throw new InvalidArgumentException('The payment ownership changed. Reload the student record and try again.');
            }
            $existing = PaymentRefund::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $forfeit = SessionLedgerEntry::query()->where('idempotency_key', 'forfeit_refund_'.$existing->id)->first();
                if ($existing->payment_record_id !== $payment->id || $this->toCents($existing->amount_refunded) !== $cents || $existing->reason !== $reason || -(int) ($forfeit ? $forfeit->credit_change : 0) !== $forfeitCredits || ($forfeit && (int) $forfeit->student_package_entitlement_id !== (int) $allocationId)) {
                    throw new InvalidArgumentException('Idempotency key was already used.');
                }

                return $existing;
            }

            $refunds = PaymentRefund::query()
                ->where('student_package_id', $package->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'payment_record_id', 'amount_refunded']);
            $paymentRefunded = $refunds
                ->where('payment_record_id', $lockedPayment->id)
                ->reduce(fn (int $total, PaymentRefund $refund): int => $total + $this->toCents((string) $refund->amount_refunded), 0);
            $packageRefunded = $refunds
                ->reduce(fn (int $total, PaymentRefund $refund): int => $total + $this->toCents((string) $refund->amount_refunded), 0);
            $packagePaid = $this->toCents((string) PaymentRecord::query()->where('student_package_id', $package->id)->sum('amount_paid'));
            if ($cents > $this->remainingRefundableCents($lockedPayment, $paymentRefunded, $packagePaid - $packageRefunded)) {
                throw new InvalidArgumentException('Refund exceeds the refundable payment or package amount.');
            }

            if ($forfeitCredits > 0) {
                $remainingCredits = $this->entitlements->lockedBalance($allocation);
                if ($forfeitCredits > $remainingCredits) {
                    throw new InvalidArgumentException('Cannot forfeit more than the allocation remaining entitlements.');
                }
            }

            $refund = PaymentRefund::create([
                'payment_record_id' => $lockedPayment->id,
                'student_package_id' => $package->id,
                'student_id' => $payment->student_id,
                'idempotency_key' => $idempotencyKey,
                'amount_refunded' => $this->fromCents($cents),
                'currency' => $lockedPayment->currency,
                'reason' => $reason,
                'refunded_at' => now('UTC'),
                'recorded_by' => $administratorId,
                'created_at' => now('UTC'),
            ]);

            if ($forfeitCredits > 0) {
                SessionLedgerEntry::create([
                    'student_id' => $lockedStudent->id,
                    'student_package_id' => $package->id,
                    'idempotency_key' => 'forfeit_refund_'.$refund->id,
                    'student_package_entitlement_id' => $allocation->id,
                    'entitlement_type_id' => $allocation->entitlement_type_id,
                    'entry_type' => 'expiration_forfeit',
                    'credit_change' => -$forfeitCredits,
                    'description' => "Credits forfeited upon refund #{$refund->id}",
                    'created_by' => $administratorId,
                    'created_at' => now('UTC'),
                ]);
            }

            return $refund;
        }, 5);
    }

    public function refundableAmount(PaymentRecord $payment, StudentPackage $package): string
    {
        $paymentRefunded = $package->refunds->where('payment_record_id', $payment->id)
            ->reduce(fn (int $total, PaymentRefund $refund): int => $total + $this->toCents((string) $refund->amount_refunded), 0);
        $packageRefunded = $package->refunds
            ->reduce(fn (int $total, PaymentRefund $refund): int => $total + $this->toCents((string) $refund->amount_refunded), 0);
        $packagePaid = $package->payments
            ->reduce(fn (int $total, PaymentRecord $record): int => $total + $this->toCents((string) $record->amount_paid), 0);

        return $this->fromCents($this->remainingRefundableCents($payment, $paymentRefunded, $packagePaid - $packageRefunded));
    }

    private function remainingRefundableCents(PaymentRecord $payment, int $paymentRefunded, int $packageNetPaid): int
    {
        return max(0, min($this->toCents((string) $payment->amount_paid) - $paymentRefunded, $packageNetPaid));
    }

    public function adjustCredits(StudentPackage $package, int $creditChange, string $description, string $idempotencyKey, ?int $administratorId = null, ?int $allocationId = null): SessionLedgerEntry
    {
        if ($creditChange === 0 || trim($description) === '') {
            throw new InvalidArgumentException('A non-zero credit adjustment and a reason are required.');
        }

        return $this->database->transaction(function () use ($package, $creditChange, $description, $idempotencyKey, $administratorId, $allocationId): SessionLedgerEntry {
            $lockedStudent = Student::withTrashed()->whereKey($package->student_id)->lockForUpdate()->firstOrFail();
            $lockedPackage = StudentPackage::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            if ($lockedStudent->trashed() || $lockedStudent->identity_status === 'merged' || (int) $lockedPackage->student_id !== (int) $lockedStudent->id) {
                throw new InvalidArgumentException('This package is no longer assigned to an active student.');
            }

            $allocation = $this->entitlements->lockAllocation($lockedPackage, $allocationId);
            $existing = SessionLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->entry_type !== 'courtesy_adjustment'
                    || (int) $existing->student_package_id !== (int) $lockedPackage->id
                    || (int) $existing->credit_change !== $creditChange
                    || (int) $existing->student_package_entitlement_id !== (int) $allocation->id
                    || $existing->description !== trim($description)) {
                    throw new InvalidArgumentException('Idempotency key was already used.');
                }

                return $existing;
            }

            if ($this->entitlements->lockedBalance($allocation) + $creditChange < 0) {
                throw new InvalidArgumentException('An adjustment cannot reduce the allocation below zero remaining entitlements.');
            }

            return SessionLedgerEntry::create([
                'student_id' => $lockedStudent->id,
                'student_package_id' => $lockedPackage->id,
                'idempotency_key' => $idempotencyKey,
                'student_package_entitlement_id' => $allocation->id,
                'entitlement_type_id' => $allocation->entitlement_type_id,
                'entry_type' => 'courtesy_adjustment',
                'credit_change' => $creditChange,
                'description' => trim($description),
                'created_by' => $administratorId,
                'created_at' => now('UTC'),
            ]);
        }, 5);
    }

    /** @return array<string, mixed> */
    public function summary(StudentPackage $package, bool $useLoadedSnapshot = false, ?string $businessTimezone = null): array
    {
        $paid = $this->toCents((string) ($useLoadedSnapshot && $package->relationLoaded('payments') ? $package->payments->sum('amount_paid') : PaymentRecord::query()->where('student_package_id', $package->id)->sum('amount_paid')));
        $refunded = $this->toCents((string) ($useLoadedSnapshot && $package->relationLoaded('refunds') ? $package->refunds->sum('amount_refunded') : PaymentRefund::query()->where('student_package_id', $package->id)->sum('amount_refunded')));
        $netPaid = $paid - $refunded;

        $entries = $useLoadedSnapshot && $package->relationLoaded('ledgerEntries') ? $package->ledgerEntries : SessionLedgerEntry::query()->where('student_package_id', $package->id)->get();

        $projectionPackage = clone $package;
        $projectionPackage->setRelation('ledgerEntries', $entries);
        $typed = $this->entitlements->projection($projectionPackage, $businessTimezone);
        $state = ucfirst($package->status);
        if ($package->identity_state === 'legacy_unclassified') {
            $state = 'Review required';
        } elseif ($package->status === 'active' && $package->expiration_date === null && $package->validity_days !== null) {
            $state = 'Awaiting settlement';
        } elseif ($package->status === 'active' && ! $this->entitlements->eligible($package, $businessTimezone)) {
            $state = 'Expired';
        } elseif ($package->status === 'active' && array_sum(array_column($typed, 'available')) <= 0) {
            $state = 'Unavailable';
        }

        return [
            'entitlements' => $typed,
            'entitlement_status' => $state,
            'balance_text' => implode('; ', array_map(fn (array $row): string => $row['label'].': '.$row['remaining'], $typed)) ?: 'No entitlements',
            'allocated_credits' => (int) $entries->where('entry_type', 'package_grant')->sum('credit_change'),
            'courtesy_credits' => (int) $entries->where('entry_type', 'courtesy_adjustment')->sum('credit_change'),
            'consumed_credits' => -(int) $entries->where('entry_type', 'session_consumed')->sum('credit_change'),
            'restored_credits' => (int) $entries->where('entry_type', 'cancellation_restore')->sum('credit_change'),
            'gross_paid' => $this->fromCents($paid),
            'gross_refunded' => $this->fromCents($refunded),
            'net_paid' => $this->fromCents($netPaid),
            'balance_due' => $this->fromCents(max(0, $this->toCents($package->final_price) - $netPaid)),
            'overpaid' => $this->fromCents(max(0, $netPaid - $this->toCents($package->final_price))),
            'remaining_credits' => array_sum(array_column($typed, 'available')),
        ];
    }

    private function toCents(string $amount): int
    {
        if (! preg_match('/^(-?)(\d{1,8})(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        $cents = ((int) $matches[2] * 100) + (int) str_pad($matches[3] ?? '0', 2, '0');

        return $matches[1] === '-' ? -$cents : $cents;
    }

    private function fromCents(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }
}
