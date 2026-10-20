<?php

namespace Tests\Feature;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class FinancialTimingCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private string $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentMethod = (string) PaymentMethodFactory::new()->create()->id;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function freezeAt(string $time): void
    {
        Carbon::setTestNow(CarbonImmutable::parse($time, 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse($time, 'UTC'));
        Setting::set('business_timezone', 'Africa/Cairo', 'booking', true);
    }

    private function purchase(Student $student, string $offering = 'foundation_track'): StudentPackage
    {
        return app(StudentLedgerService::class)->createPackage($student, 'Synthetic purchase', 8, '280.00', '0.00', 'USD', null, (string) Str::uuid(), presetKey: $offering);
    }

    private function diagnostic(Student $student, string $paidAt, ?string $completedAt): array
    {
        $ledger = app(StudentLedgerService::class);
        $package = $this->purchase($student, 'diagnostic_roadmap');
        $payment = $ledger->recordPayment($package, '25.00', (string) Str::uuid(), null, receivedAt: CarbonImmutable::parse($paidAt, 'UTC'));
        $booking = DB::transaction(function () use ($student, $ledger): Booking {
            $booking = Booking::factory()->create(['student_id' => $student->id, 'session_type_id' => $this->oneHourPackageLesson()->id]);
            $ledger->consumeForBooking($booking, (string) Str::uuid());

            return $booking;
        });
        $booking->update(['status' => 'completed', 'completed_at' => $completedAt]);

        return [$package, $payment, $booking];
    }

    public function test_credit_uses_completion_instant_and_expires_at_the_exact_48_hour_boundary(): void
    {
        $this->freezeAt('2026-10-08 12:00:00');
        $student = Student::factory()->verified()->create();
        $this->diagnostic($student, '2026-10-01 12:00:00', '2026-10-07 12:00:00');
        $ledger = app(StudentLedgerService::class);
        $this->assertSame('2026-10-09 12:00:00', $ledger->checkDiagnosticCreditEligibility($student->id)['credit_expires_at']);
        $this->freezeAt('2026-10-09 11:59:59');
        $this->assertNotNull($ledger->checkDiagnosticCreditEligibility($student->id));
        $this->freezeAt('2026-10-09 12:00:00');
        $this->assertNull($ledger->checkDiagnosticCreditEligibility($student->id));
    }

    public function test_recent_payment_does_not_reopen_an_old_completion_window_and_future_completion_is_invalid(): void
    {
        $this->freezeAt('2026-10-08 12:00:00');
        $student = Student::factory()->verified()->create();
        [, , $booking] = $this->diagnostic($student, '2026-10-08 10:00:00', '2026-10-05 12:00:00');
        $ledger = app(StudentLedgerService::class);
        $this->assertNull($ledger->checkDiagnosticCreditEligibility($student->id));
        $booking->update(['completed_at' => '2026-10-08 12:01:00']);
        $this->assertNull($ledger->checkDiagnosticCreditEligibility($student->id));
    }

    public function test_missing_completion_requires_authentic_event_evidence(): void
    {
        $this->freezeAt('2026-10-08 12:00:00');
        $student = Student::factory()->verified()->create();
        [, , $booking] = $this->diagnostic($student, '2026-10-08 10:00:00', null);
        $ledger = app(StudentLedgerService::class);
        $this->assertNull($ledger->checkDiagnosticCreditEligibility($student->id));
        BookingEvent::create(['booking_id' => $booking->id, 'event_type' => 'completed', 'performed_by' => 'admin', 'new_data' => ['completed_at' => '2026-10-07 09:00:00'], 'created_at' => '2026-10-07 09:00:00']);
        $this->assertSame('2026-10-07 09:00:00', $ledger->checkDiagnosticCreditEligibility($student->id)['completed_at']);
    }

    public function test_awarded_credit_is_once_only_and_issued_terms_survive_later_settlement(): void
    {
        $this->freezeAt('2026-10-08 12:00:00');
        $student = Student::factory()->verified()->create();
        $this->diagnostic($student, '2026-10-07 09:00:00', '2026-10-07 10:00:00');
        $track = $this->purchase($student);
        $this->assertSame('25.00', $track->discount_amount);
        $this->assertSame('255.00', $track->final_price);
        $this->assertNull(app(StudentLedgerService::class)->checkDiagnosticCreditEligibility($student->id));
        $this->freezeAt('2026-10-12 12:00:00');
        app(StudentLedgerService::class)->recordPayment($track, '255.00', (string) Str::uuid(), null);
        $this->assertSame('255.00', $track->fresh()->final_price);
        $this->assertSame('25.00', $track->fresh()->discount_amount);
    }

    public function test_refunded_diagnostic_cannot_award_automatic_credit(): void
    {
        $this->freezeAt('2026-10-08 12:00:00');
        $student = Student::factory()->verified()->create();
        [, $payment] = $this->diagnostic($student, '2026-10-07 09:00:00', '2026-10-07 10:00:00');
        $ledger = app(StudentLedgerService::class);
        $ledger->refund($payment, '1.00', (string) Str::uuid(), null);
        $this->assertNull($ledger->checkDiagnosticCreditEligibility($student->id));
    }

    public function test_first_partial_receipt_starts_expiry_and_retains_cairo_wall_clock_across_daylight_saving(): void
    {
        $this->freezeAt('2026-02-20 12:00:00');
        $student = Student::factory()->verified()->create();
        $track = $this->purchase($student);
        $ledger = app(StudentLedgerService::class);
        $ledger->recordPayment($track, '140.00', (string) Str::uuid(), null, receivedAt: CarbonImmutable::parse('2026-02-14 10:00:00', 'UTC'));
        $this->assertNotNull($track->fresh()->validity_started_at);
        $ledger->recordPayment($track, '140.00', (string) Str::uuid(), null, receivedAt: CarbonImmutable::parse('2026-02-15 10:00:00', 'UTC'));
        $track->refresh();
        $this->assertSame('2026-02-14 10:00:00', $track->validity_started_at->toDateTimeString());
        $this->assertSame('2026-04-30 09:00:00', $track->expires_at->toDateTimeString());
        $this->assertSame('12:00', $track->expires_at->setTimezone('Africa/Cairo')->format('H:i'));
        $this->assertSame('2026-04-30', $track->expiration_date->toDateString());
    }

    public function test_backdated_receipt_uses_the_chronological_first_payment_receipt(): void
    {
        $this->freezeAt('2026-10-08 12:00:00');
        $track = $this->purchase(Student::factory()->verified()->create());
        $ledger = app(StudentLedgerService::class);
        $ledger->recordPayment($track, '150.00', (string) Str::uuid(), null, receivedAt: CarbonImmutable::parse('2026-10-06 15:20:30', 'UTC'));
        $ledger->recordPayment($track, '130.00', (string) Str::uuid(), null, receivedAt: CarbonImmutable::parse('2026-10-01 09:00:00', 'UTC'));
        $this->assertSame('2026-10-01 09:00:00', $track->fresh()->validity_started_at->toDateTimeString());
    }

    public function test_payment_replay_and_refund_do_not_restart_validity_or_mutate_entitlements(): void
    {
        $this->freezeAt('2026-10-08 12:00:00');
        $track = $this->purchase(Student::factory()->verified()->create());
        $ledger = app(StudentLedgerService::class);
        $key = (string) Str::uuid();
        $receipt = CarbonImmutable::parse('2026-10-01 09:00:00', 'UTC');
        $payment = $ledger->recordPayment($track, '280.00', $key, null, receivedAt: $receipt);
        $start = $track->fresh()->validity_started_at->toDateTimeString();
        $expiry = $track->fresh()->expires_at->toDateTimeString();
        $this->assertSame($payment->id, $ledger->recordPayment($track, '280.00', $key, null, receivedAt: $receipt)->id);
        $ledger->refund($payment, '25.00', (string) Str::uuid(), null);
        $ledger->recordPayment($track, '25.00', (string) Str::uuid(), null);
        $this->assertSame($start, $track->fresh()->validity_started_at->toDateTimeString());
        $this->assertSame($expiry, $track->fresh()->expires_at->toDateTimeString());
        $this->assertSame(8, (int) $track->ledgerEntries()->sum('credit_change'));
    }

    public function test_legacy_dated_packages_and_unlimited_custom_rights_remain_unchanged(): void
    {
        $this->freezeAt('2026-10-08 20:59:59');
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $legacy = $ledger->createPackage($student, 'Existing dated purchase', 2, '100.00', '10.00', 'USD', '2026-10-08', (string) Str::uuid(), entitlementCode: 'one_hour');
        $unlimited = $ledger->createPackage($student, 'Existing unlimited purchase', 2, '100.00', '0.00', 'USD', null, (string) Str::uuid(), entitlementCode: 'one_hour');
        $ledger->recordPayment($legacy, '90.00', (string) Str::uuid(), null);
        $ledger->recordPayment($unlimited, '100.00', (string) Str::uuid(), null);
        $this->assertNull($legacy->fresh()->expires_at);
        $this->assertNull($unlimited->fresh()->expires_at);
        $this->assertSame('2026-10-08', $legacy->fresh()->expiration_date->toDateString());
        $this->assertTrue(app(EntitlementService::class)->eligible($legacy));
        $this->freezeAt('2026-10-08 21:00:00');
        $this->assertFalse(app(EntitlementService::class)->eligible($legacy));
        $this->assertTrue(app(EntitlementService::class)->eligible($unlimited));
        $this->assertSame('90.00', $legacy->final_price);
        $this->assertSame(2, (int) $legacy->ledgerEntries()->sum('credit_change'));
    }

    public function test_exact_deadline_controls_both_loaded_eligibility_and_database_selection(): void
    {
        $this->freezeAt('2026-10-08 12:00:00');
        $student = Student::factory()->verified()->create();
        $package = app(StudentLedgerService::class)->createPackage($student, 'Clock fixture', 1, '25.00', '0.00', 'USD', null, (string) Str::uuid(), entitlementCode: 'one_hour');
        $package->update(['expires_at' => '2026-10-08 12:00:01', 'validity_started_at' => '2026-09-01 12:00:01', 'expiration_date' => '2026-10-08']);
        $service = app(EntitlementService::class);
        $this->assertTrue($service->eligible($package));
        $this->assertTrue($service->canFund($student->id, $this->oneHourPackageLesson()));
        $this->freezeAt('2026-10-08 12:00:01');
        $this->assertFalse($service->eligible($package));
        $this->assertFalse($service->canFund($student->id, $this->oneHourPackageLesson()));
        $this->assertSame('Expired', app(StudentLedgerService::class)->summary($package)['entitlement_status']);
        $this->assertSame(1, (int) $package->ledgerEntries()->sum('credit_change'));
    }

    public function test_staff_records_actual_receipt_and_can_only_extend_the_new_exact_deadline(): void
    {
        $this->freezeAt('2026-10-08 12:00:00');
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $track = $this->purchase(Student::factory()->verified()->create());
        $this->actingAs($admin)->post(route('admin.students.payments.store', [$track->student_id, $track->id]), [
            'amount_paid' => '280.00', 'payment_method' => $this->paymentMethod,
            'payment_idempotency_key' => (string) Str::uuid(), 'received_at' => '2026-10-06T18:20:30', 'received_at_timezone' => 'Africa/Cairo',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2026-10-06 15:20:30', $track->payments()->sole()->paid_at->toDateTimeString());
        $track->refresh();
        $originalStart = $track->validity_started_at->toDateTimeString();
        $oldExpiry = $track->expires_at->toDateTimeString();
        $data = ['previous_expiration_date' => $track->expiration_date->toDateString(), 'previous_expires_at' => $oldExpiry, 'reason' => 'Approved extension'];
        $this->post(route('admin.students.packages.validity', [$track->student_id, $track->id]), $data + ['expiration_date' => $track->expiration_date->subDay()->toDateString()])->assertSessionHasErrors('expiration_date');
        $this->post(route('admin.students.packages.validity', [$track->student_id, $track->id]), $data + ['expiration_date' => '2027-01-01'])->assertSessionHasNoErrors();
        $this->assertGreaterThan($oldExpiry, $track->fresh()->expires_at->toDateTimeString());
        $this->assertSame($originalStart, $track->fresh()->validity_started_at->toDateTimeString());
        $this->assertSame(8, (int) $track->ledgerEntries()->sum('credit_change'));
        $this->assertSame('2026-10-06 15:20:30', AuditLog::where('action', 'student_payment_recorded')->where('entity_type', PaymentRecord::class)->where('entity_id', $track->payments()->sole()->id)->sole()->new_data['paid_at']);
    }

    public function test_future_receipts_are_rejected_before_a_payment_is_written(): void
    {
        $this->freezeAt('2026-10-08 12:00:00');
        $track = $this->purchase(Student::factory()->verified()->create());
        try {
            app(StudentLedgerService::class)->recordPayment($track, '280.00', (string) Str::uuid(), null, receivedAt: CarbonImmutable::parse('2026-10-08 12:00:01', 'UTC'));
            $this->fail('A future payment must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('future', $exception->getMessage());
        }
        $this->assertDatabaseCount('payment_records', 0);
        $this->assertNull($track->fresh()->expires_at);
    }

    public function test_receipts_with_nonexistent_or_ambiguous_cairo_clocks_require_an_exact_utc_instant(): void
    {
        $this->freezeAt('2026-11-01 12:00:00');
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $track = $this->purchase(Student::factory()->verified()->create());
        $this->actingAs($admin);
        foreach (['2026-04-24T00:30:00', '2026-10-29T23:30:00'] as $clock) {
            $this->post(route('admin.students.payments.store', [$track->student_id, $track->id]), [
                'amount_paid' => '280.00', 'payment_method' => $this->paymentMethod,
                'payment_idempotency_key' => (string) Str::uuid(), 'received_at' => $clock, 'received_at_timezone' => 'Africa/Cairo',
            ])->assertSessionHasErrors('received_at');
        }
        $this->assertDatabaseCount('payment_records', 0);
        $this->post(route('admin.students.payments.store', [$track->student_id, $track->id]), [
            'amount_paid' => '280.00', 'payment_method' => $this->paymentMethod,
            'payment_idempotency_key' => (string) Str::uuid(), 'received_at' => '2026-10-29T20:30:00', 'received_at_timezone' => 'UTC',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-29 20:30:00', $track->fresh()->validity_started_at->toDateTimeString());
    }

    public function test_refunds_before_full_settlement_are_accounted_for_in_the_activation_clock(): void
    {
        $this->freezeAt('2026-10-02 12:00:00');
        $track = $this->purchase(Student::factory()->verified()->create());
        $ledger = app(StudentLedgerService::class);
        $first = $ledger->recordPayment($track, '140.00', (string) Str::uuid(), null, receivedAt: CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC'));
        $ledger->refund($first, '40.00', (string) Str::uuid(), null);
        $this->freezeAt('2026-10-04 12:00:00');
        $ledger->recordPayment($track, '140.00', (string) Str::uuid(), null, receivedAt: CarbonImmutable::parse('2026-10-03 12:00:00', 'UTC'));
        $this->assertNotNull($track->fresh()->expires_at);
        $this->assertNull($track->fresh()->funding_activated_at);
        $ledger->recordPayment($track, '40.00', (string) Str::uuid(), null);
        $this->assertSame('2026-10-01 12:00:00', $track->fresh()->validity_started_at->toDateTimeString());
        $this->assertSame(8, (int) $track->ledgerEntries()->sum('credit_change'));
    }

    public function test_historical_paid_undated_packages_use_the_original_receipt_under_the_first_payment_policy(): void
    {
        $this->freezeAt('2026-10-08 12:00:00');
        $track = $this->purchase(Student::factory()->verified()->create());
        PaymentRecord::factory()->create(['student_package_id' => $track->id, 'amount_paid' => '280.00', 'paid_at' => '2026-10-01 12:00:00']);
        app(StudentLedgerService::class)->recordPayment($track, '1.00', (string) Str::uuid(), null);
        $this->assertSame('2026-10-01 12:00:00', $track->fresh()->validity_started_at->toDateTimeString());
        $this->assertNotNull($track->fresh()->expires_at);
        $this->assertNotNull($track->fresh()->expiration_date);
        $this->assertSame('280.00', $track->fresh()->final_price);
        $this->assertSame(8, (int) $track->ledgerEntries()->sum('credit_change'));
    }
}
