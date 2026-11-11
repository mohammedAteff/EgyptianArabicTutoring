<?php

namespace Tests\Feature;

use App\Domains\Administration\Services\OperationsReadModel;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Notifications\Services\TelegramReportFormatter;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FirstPaymentValidityTest extends TestCase
{
    use RefreshDatabase;

    private function purchase(): StudentPackage
    {
        return app(StudentLedgerService::class)->createPackage(Student::factory()->verified()->create(), 'Foundation', 8, '280', '0', 'USD', null, fake()->uuid(), presetKey: 'foundation_track');
    }

    public function test_first_partial_payment_starts_expiry_and_makes_booking_credits_available(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00 UTC'));
        $package = $this->purchase();
        $ledger = app(StudentLedgerService::class);
        $this->assertFalse(app(EntitlementService::class)->eligible($package));
        $this->assertSame('Awaiting first payment', $ledger->summary($package)['entitlement_status']);
        $this->assertSame('Not started · awaiting first payment', app(TelegramReportFormatter::class)->expiry($package));
        $ledger->recordPayment($package, '100', fake()->uuid(), null, receivedAt: CarbonImmutable::parse('2026-10-18 10:00:00 UTC'));
        $package->refresh();
        $this->assertSame('2026-10-18 10:00:00', $package->validity_started_at->toDateTimeString());
        $this->assertSame('2027-01-01', $package->expiration_date->toDateString());
        $this->assertSame('1 Jan 2027, 13:00 · Africa/Cairo', app(TelegramReportFormatter::class)->expiry($package));
        $this->assertTrue(app(EntitlementService::class)->eligible($package));
        $this->assertNull($package->funding_activated_at);
        $this->assertSame('Active', $ledger->summary($package)['entitlement_status']);
        $this->assertSame(8, $ledger->summary($package)['remaining_credits']);
        $this->assertSame('180.00', $ledger->summary($package)['balance_due']);
        $ledger->recordPayment($package, '180', fake()->uuid(), null);
        $package->refresh();
        $this->assertSame('2026-10-18 10:00:00', $package->validity_started_at->toDateTimeString());
        $this->assertSame('2027-01-01', $package->expiration_date->toDateString());
        $this->assertTrue(app(EntitlementService::class)->eligible($package));
        $this->assertSame(8, $ledger->summary($package)['remaining_credits']);
    }

    public function test_finishing_payment_after_the_deadline_does_not_restart_expiry(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 10:00:00 UTC'));
        $package = $this->purchase();
        $ledger = app(StudentLedgerService::class);
        $ledger->recordPayment($package, '100', fake()->uuid(), null);
        $this->travelTo(CarbonImmutable::parse('2027-01-01 10:00:00 UTC'));
        $ledger->recordPayment($package, '180', fake()->uuid(), null);
        $package->refresh();
        $this->assertSame('2026-10-01 10:00:00', $package->validity_started_at->toDateTimeString());
        $this->assertSame('Expired', $ledger->summary($package)['entitlement_status']);
        $this->assertFalse(app(EntitlementService::class)->eligible($package));
    }

    public function test_student_can_book_with_the_first_installment_and_keeps_the_outstanding_balance(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00 UTC'));
        $package = $this->purchase();
        $student = $package->student;
        $type = SessionType::factory()->create(['funding_mode' => 'package', 'duration_minutes' => 120,
            'required_entitlement_type_id' => EntitlementType::query()->where('code', 'two_hour')->value('id'), 'required_entitlement_units' => 1]);
        AvailabilityRule::query()->create(['weekday' => 1, 'start_time' => '10:00', 'end_time' => '16:00', 'session_duration_minutes' => null, 'buffer_minutes' => 10, 'min_notice_hours' => 0, 'max_horizon_days' => 60, 'enabled' => true]);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
        $this->withCookie(config('session.cookie'), session()->getId());
        $parameters = ['session_type_id' => $type->id, 'date' => '2026-10-26', 'timezone' => 'Africa/Cairo'];
        $this->get(route('student.bookings.create', $parameters))->assertOk()->assertViewHas('availableCredits', 0)->assertViewHas('hasFundableFormat', false);

        $ledger = app(StudentLedgerService::class);
        $ledger->recordPayment($package, '100', fake()->uuid(), null);
        $response = $this->get(route('student.bookings.create', $parameters))->assertOk()->assertViewHas('availableCredits', 8)->assertViewHas('hasFundableFormat', true);
        $slot = $response->viewData('slots')['2026-10-26'][0];
        $payload = ['session_type_id' => $type->id, 'timezone' => 'Africa/Cairo', 'slot_id' => $slot['slot_id'], 'idempotency_key' => $response->viewData('idempotencyKey')];
        $this->post(route('student.bookings.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $booking = Booking::query()->sole();
        $this->assertSame($package->id, $booking->student_package_id);
        $this->assertDatabaseHas('session_ledger_entries', ['booking_id' => $booking->id, 'entry_type' => 'session_consumed', 'credit_change' => -1]);
        $this->assertSame(7, $ledger->summary($package->fresh())['remaining_credits']);
        $this->assertSame('180.00', $ledger->summary($package->fresh())['balance_due']);
        $this->post(route('student.bookings.store'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('student.bookings.show', $booking));
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_installment_packages_appear_in_credit_expiry_and_outstanding_payment_reports(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00 UTC'));
        $package = $this->purchase();
        $ledger = app(StudentLedgerService::class);
        $ledger->recordPayment($package, '100', fake()->uuid(), null);
        $ledger->adjustCredits($package, -7, 'Synthetic credit correction', fake()->uuid(), allocationId: $package->entitlements()->sole()->id);
        $this->travelTo(CarbonImmutable::parse('2026-12-30 12:00:00 UTC'));
        $report = app(OperationsReadModel::class)->forAdministrator(AdministratorFactory::new()->create(['role' => 'super_admin']));

        $this->assertSame(1, $report['counts']['lowCreditStudents']);
        $this->assertSame(1, $report['counts']['expiringPackages']);
        $this->assertSame(1, $report['counts']['paymentFollowUps']);
        $this->assertSame('180.00', $report['paymentBalances'][$package->id]);
        $this->assertSame(1, $report['studentCredits'][$package->student_id]['two_hour']['available']);
    }

    public function test_policy_backfill_is_previewable_audited_and_keeps_payments_and_ledger_unchanged(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00 UTC'));
        $package = $this->purchase();
        $ledger = app(StudentLedgerService::class);
        $ledger->recordPayment($package, '100', fake()->uuid(), null, receivedAt: CarbonImmutable::parse('2026-10-01 10:00:00 UTC'));
        DB::table('student_packages')->where('id', $package->id)->update(['validity_started_at' => null, 'expires_at' => null, 'expiration_date' => null]);
        $this->artisan('students:apply-first-payment-validity')->assertSuccessful();
        $this->assertNull($package->fresh()->expires_at);
        $actor = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $this->artisan('students:apply-first-payment-validity', ['--apply' => true, '--administrator' => $actor->id])->assertSuccessful();
        $this->assertSame('2026-10-01 10:00:00', $package->fresh()->validity_started_at->toDateTimeString());
        $this->assertDatabaseCount('payment_records', 1);
        $this->assertDatabaseCount('session_ledger_entries', 1);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $package->id, 'action' => 'package_first_payment_policy_applied']);
        $this->artisan('students:apply-first-payment-validity', ['--apply' => true, '--administrator' => $actor->id])->assertSuccessful();
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'package_first_payment_policy_applied')->count());
    }
}
