<?php

namespace Tests\Feature;

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

    public function test_partial_payment_starts_expiry_without_unlocking_unsettled_booking_credits(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00 UTC'));
        $package = $this->purchase();
        $ledger = app(StudentLedgerService::class);
        $ledger->recordPayment($package, '100', fake()->uuid(), null, receivedAt: CarbonImmutable::parse('2026-10-18 10:00:00 UTC'));
        $package->refresh();
        $this->assertSame('2026-10-18 10:00:00', $package->validity_started_at->toDateTimeString());
        $this->assertSame('2027-01-01', $package->expiration_date->toDateString());
        $this->assertFalse(app(EntitlementService::class)->eligible($package));
        $this->assertSame('Awaiting settlement', $ledger->summary($package)['entitlement_status']);
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
