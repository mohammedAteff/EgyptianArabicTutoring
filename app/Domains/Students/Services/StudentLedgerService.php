<?php

namespace App\Domains\Students\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StudentLedgerService
{
    public function __construct(private TimezoneService $timezones, private DatabaseCapability $database) {}

    public function createPackage(Student $student, string $name, int $sessions, string $originalPrice, string $discountAmount, string $currency, ?string $expirationDate, string $idempotencyKey, ?int $administratorId = null): StudentPackage
    {
        $originalCents = $this->toCents($originalPrice);
        $discountCents = $this->toCents($discountAmount);
        if ($sessions < 1 || $originalCents < 0 || $discountCents < 0 || $discountCents > $originalCents) {
            throw new InvalidArgumentException('Invalid package terms.');
        }

        return $this->database->transaction(function () use ($student, $name, $sessions, $originalCents, $discountCents, $currency, $expirationDate, $idempotencyKey, $administratorId): StudentPackage {
            $lockedStudent = Student::withTrashed()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            if ($lockedStudent->trashed() || $lockedStudent->identity_status === 'merged') {
                throw new InvalidArgumentException('Packages cannot be assigned to an inactive student record.');
            }
            $existing = SessionLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->entry_type !== 'package_grant' || $existing->student_id !== $student->id) {
                    throw new InvalidArgumentException('Idempotency key was already used.');
                }

                return StudentPackage::findOrFail($existing->student_package_id);
            }

            $package = StudentPackage::create([
                'student_id' => $student->id,
                'package_name' => $name,
                'original_price' => $this->fromCents($originalCents),
                'discount_amount' => $this->fromCents($discountCents),
                'final_price' => $this->fromCents($originalCents - $discountCents),
                'currency' => strtoupper($currency),
                'total_sessions_allocated' => $sessions,
                'expiration_date' => $expirationDate,
                'status' => 'active',
            ]);
            SessionLedgerEntry::create([
                'student_id' => $student->id,
                'student_package_id' => $package->id,
                'idempotency_key' => $idempotencyKey,
                'entry_type' => 'package_grant',
                'credit_change' => $sessions,
                'description' => 'Package credit grant',
                'created_by' => $administratorId,
                'created_at' => now('UTC'),
            ]);

            return $package;
        }, 5);
    }

    public function selectAndLockEligiblePackageForBooking(int $studentId): ?StudentPackage
    {
        if (DB::transactionLevel() < 1) {
            throw new InvalidArgumentException('Package allocation requires an active transaction.');
        }

        $packages = StudentPackage::query()
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $packagesByFifo = $packages->sort(function (StudentPackage $first, StudentPackage $second): int {
            if (($first->expiration_date === null) !== ($second->expiration_date === null)) {
                return $first->expiration_date === null ? 1 : -1;
            }

            return strcmp((string) $first->expiration_date?->toDateString(), (string) $second->expiration_date?->toDateString())
                ?: strcmp((string) $first->created_at?->toDateTimeString(), (string) $second->created_at?->toDateTimeString())
                ?: ($first->id <=> $second->id);
        })->values();

        $ledgerEntries = SessionLedgerEntry::query()
            ->whereIn('student_package_id', $packages->modelKeys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'student_package_id', 'credit_change']);
        $balances = $ledgerEntries->groupBy('student_package_id')->map(fn ($entries): int => (int) $entries->sum('credit_change'));

        foreach ($packagesByFifo as $package) {
            if (! $this->isEligible($package)) {
                continue;
            }

            if (($balances->get($package->id, 0)) > 0) {
                return $package;
            }
        }

        return null;
    }

    public function consumeForBooking(Booking $booking, string $idempotencyKey): SessionLedgerEntry
    {
        if (! $booking->student_id || DB::transactionLevel() < 1) {
            throw new InvalidArgumentException('A student booking transaction is required.');
        }

        $existing = SessionLedgerEntry::query()->where('booking_id', $booking->id)->where('entry_type', 'session_consumed')->first();
        if ($existing) {
            return $existing;
        }

        $package = $this->selectAndLockEligiblePackageForBooking($booking->student_id);
        if (! $package) {
            throw new InvalidArgumentException('No available session credits.');
        }

        return SessionLedgerEntry::create([
            'student_id' => $booking->student_id,
            'student_package_id' => $package->id,
            'booking_id' => $booking->id,
            'idempotency_key' => $idempotencyKey,
            'entry_type' => 'session_consumed',
            'credit_change' => -1,
            'description' => 'Session credit used for booking',
            'created_at' => now('UTC'),
        ]);
    }

    public function restoreCancellation(Booking $booking, string $idempotencyKey): ?SessionLedgerEntry
    {
        if (! $booking->student_id || DB::transactionLevel() < 1) {
            return null;
        }

        $consumed = SessionLedgerEntry::query()->where('booking_id', $booking->id)->where('entry_type', 'session_consumed')->first();
        if (! $consumed) {
            return null;
        }

        $package = StudentPackage::query()->whereKey($consumed->student_package_id)->lockForUpdate()->firstOrFail();
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
            'credit_change' => 1,
            'description' => 'Credit restored after cancellation',
            'created_at' => now('UTC'),
        ]);
    }

    public function recordPayment(StudentPackage $package, string $amount, string $idempotencyKey, ?int $administratorId, string $method = 'PayPal - Manual', ?string $reference = null, ?string $notes = null): PaymentRecord
    {
        $cents = $this->toCents($amount);
        if ($cents <= 0) {
            throw new InvalidArgumentException('Payment amount must be positive.');
        }

        return $this->database->transaction(function () use ($package, $cents, $idempotencyKey, $administratorId, $method, $reference, $notes): PaymentRecord {
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

            return PaymentRecord::create([
                'student_package_id' => $package->id,
                'student_id' => $package->student_id,
                'idempotency_key' => $idempotencyKey,
                'amount_paid' => $this->fromCents($cents),
                'currency' => $package->currency,
                'payment_method' => $method,
                'transaction_reference' => $reference,
                'paid_at' => now('UTC'),
                'recorded_by' => $administratorId,
                'notes' => $notes,
                'created_at' => now('UTC'),
            ]);
        }, 5);
    }

    public function refund(PaymentRecord $payment, string $amount, string $idempotencyKey, ?int $administratorId, ?string $reason = null): PaymentRefund
    {
        $cents = $this->toCents($amount);
        if ($cents <= 0) {
            throw new InvalidArgumentException('Refund amount must be positive.');
        }

        return $this->database->transaction(function () use ($payment, $cents, $idempotencyKey, $administratorId, $reason): PaymentRefund {
            $lockedStudent = Student::withTrashed()->whereKey($payment->student_id)->lockForUpdate()->firstOrFail();
            $package = StudentPackage::query()->whereKey($payment->student_package_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = PaymentRecord::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($lockedStudent->trashed() || $lockedStudent->identity_status === 'merged'
                || (int) $package->student_id !== (int) $lockedStudent->id
                || (int) $lockedPayment->student_id !== (int) $lockedStudent->id
                || (int) $lockedPayment->student_package_id !== (int) $package->id) {
                throw new InvalidArgumentException('The payment ownership changed. Reload the student record and try again.');
            }
            $existing = PaymentRefund::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->payment_record_id !== $payment->id) {
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
            if ($cents > $this->toCents($lockedPayment->amount_paid) - $paymentRefunded || $cents > $packagePaid - $packageRefunded) {
                throw new InvalidArgumentException('Refund exceeds the refundable payment or package amount.');
            }

            return PaymentRefund::create([
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
        }, 5);
    }

    public function adjustCredits(StudentPackage $package, int $creditChange, string $description, string $idempotencyKey, ?int $administratorId = null): SessionLedgerEntry
    {
        if ($creditChange === 0 || trim($description) === '') {
            throw new InvalidArgumentException('A non-zero credit adjustment and a reason are required.');
        }

        return $this->database->transaction(function () use ($package, $creditChange, $description, $idempotencyKey, $administratorId): SessionLedgerEntry {
            $lockedStudent = Student::withTrashed()->whereKey($package->student_id)->lockForUpdate()->firstOrFail();
            $lockedPackage = StudentPackage::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            if ($lockedStudent->trashed() || $lockedStudent->identity_status === 'merged' || (int) $lockedPackage->student_id !== (int) $lockedStudent->id) {
                throw new InvalidArgumentException('This package is no longer assigned to an active student.');
            }

            $existing = SessionLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->entry_type !== 'courtesy_adjustment'
                    || (int) $existing->student_package_id !== (int) $lockedPackage->id
                    || (int) $existing->credit_change !== $creditChange) {
                    throw new InvalidArgumentException('Idempotency key was already used.');
                }

                return $existing;
            }

            $entries = SessionLedgerEntry::query()
                ->where('student_package_id', $lockedPackage->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'credit_change']);
            if ((int) $entries->sum('credit_change') + $creditChange < 0) {
                throw new InvalidArgumentException('An adjustment cannot reduce the package below zero remaining credits.');
            }

            return SessionLedgerEntry::create([
                'student_id' => $lockedStudent->id,
                'student_package_id' => $lockedPackage->id,
                'idempotency_key' => $idempotencyKey,
                'entry_type' => 'courtesy_adjustment',
                'credit_change' => $creditChange,
                'description' => trim($description),
                'created_by' => $administratorId,
                'created_at' => now('UTC'),
            ]);
        }, 5);
    }

    /** @return array{gross_paid: string, gross_refunded: string, net_paid: string, balance_due: string, remaining_credits: int} */
    public function summary(StudentPackage $package): array
    {
        $paid = $this->toCents((string) PaymentRecord::query()->where('student_package_id', $package->id)->sum('amount_paid'));
        $refunded = $this->toCents((string) PaymentRefund::query()->where('student_package_id', $package->id)->sum('amount_refunded'));
        $netPaid = $paid - $refunded;

        return [
            'gross_paid' => $this->fromCents($paid),
            'gross_refunded' => $this->fromCents($refunded),
            'net_paid' => $this->fromCents($netPaid),
            'balance_due' => $this->fromCents($this->toCents($package->final_price) - $netPaid),
            'remaining_credits' => $this->isEligible($package)
                ? (int) SessionLedgerEntry::query()->where('student_package_id', $package->id)->sum('credit_change') : 0,
        ];
    }

    private function isEligible(StudentPackage $package): bool
    {
        if ($package->status !== 'active') {
            return false;
        }
        if (! $package->expiration_date) {
            return true;
        }

        $expiresAtUtc = CarbonImmutable::parse($package->expiration_date->toDateString(), $this->timezones->getBusinessTimezone())
            ->endOfDay()->setTimezone('UTC');

        return now('UTC')->lessThanOrEqualTo($expiresAtUtc);
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
