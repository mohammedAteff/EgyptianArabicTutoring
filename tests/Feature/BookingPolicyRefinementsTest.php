<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Models\AdminNotification;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\CashierReportService;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Livewire\BookingWizard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingPolicyRefinementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_numbers_sort_descending_even_with_backdated_or_equal_dates(): void
    {
        $admin = Administrator::factory()->create(['name' => 'Sarah Admin']);
        $first = AdminNotification::create(['type' => 'booking_created', 'level' => 'info', 'title' => 'First record', 'message' => 'First', 'link' => 'https://old-root.test/admin/bookings/1', 'data' => ['booking_id' => 1]]);
        $second = AdminNotification::create(['type' => 'booking_created', 'level' => 'info', 'title' => 'Second record', 'message' => 'Second']);
        $third = AdminNotification::create(['type' => 'booking_created', 'level' => 'info', 'title' => 'Third record', 'message' => 'Third']);
        DB::table('admin_notifications')->whereIn('id', [$second->id, $third->id])->update(['created_at' => now()->subDay()]);
        $this->actingAs($admin)->get(route('admin.notifications.index'))->assertSeeInOrder(['Third record', 'Second record', 'First record'])->assertSee('Sarah Admin')->assertSee(route('admin.bookings.show', 1))->assertDontSee('old-root.test');
    }

    public function test_cashier_orders_payments_by_record_number_with_historical_receipt_dates(): void
    {
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Custom', 3, '100.00', '0.00', 'USD', null, (string) Str::uuid(), entitlementCode: 'one_hour');
        $first = $ledger->recordPayment($package, '10.00', (string) Str::uuid(), null);
        $second = $ledger->recordPayment($package, '10.00', (string) Str::uuid(), null, receivedAt: CarbonImmutable::now('UTC')->subDay());
        $rows = iterator_to_array(app(CashierReportService::class)->rows(['transaction_type' => 'payment']));
        $this->assertSame(['PAY-'.$second->id, 'PAY-'.$first->id], array_column($rows, 9));
    }

    public static function unlimitedOfferings(): array
    {
        return [['diagnostic_roadmap'], ['payg_maintenance'], ['advanced_conversational']];
    }

    #[DataProvider('unlimitedOfferings')]
    public function test_diagnostic_and_single_session_credits_have_no_expiry(string $key): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20', 'UTC'));
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Ignored', 1, '25.00', '0.00', 'USD', '2026-10-21', (string) Str::uuid(), presetKey: $key);
        $ledger->recordPayment($package, $package->final_price, (string) Str::uuid(), null);
        $this->travelTo(CarbonImmutable::parse('2028-10-20', 'UTC'));
        $package->refresh();
        $this->assertNull($package->validity_days);
        $this->assertNull($package->expires_at);
        $this->assertNull($package->expiration_date);
        $this->assertTrue(app(EntitlementService::class)->eligible($package));
    }

    public function test_policy_correction_preserves_financial_facts_and_is_idempotent(): void
    {
        $admin = Administrator::factory()->create();
        foreach (['egyptian-arabic-session' => 120, 'diagnostic-session' => 60, 'advanced-conversational' => 60] as $slug => $duration) {
            SessionType::factory()->create(['slug' => $slug, 'title' => 'Old '.$slug, 'duration_minutes' => $duration, 'price' => '40.00']);
        }
        $student = Student::factory()->verified()->create();
        $package = app(StudentLedgerService::class)->createPackage($student, 'Diagnostic', 1, '25.00', '0.00', 'USD', null, (string) Str::uuid(), presetKey: 'diagnostic_roadmap');
        DB::table('student_packages')->where('id', $package->id)->update(['validity_days' => 14, 'expiration_date' => '2026-11-20']);
        $ledgerBefore = DB::table('session_ledger_entries')->get()->toJson();
        $this->artisan('booking:apply-policy-corrections')->assertSuccessful();
        $this->assertSame(14, $package->fresh()->validity_days);
        $this->artisan('booking:apply-policy-corrections', ['--apply' => true, '--administrator' => $admin->id])->assertSuccessful();
        $this->assertNull($package->fresh()->validity_days);
        $this->assertSame($ledgerBefore, DB::table('session_ledger_entries')->get()->toJson());
        $this->assertDatabaseHas('session_types', ['slug' => 'egyptian-arabic-session', 'title' => 'Two-Hour Single Session', 'price' => '48.00']);
        $audits = AuditLog::count();
        $this->artisan('booking:apply-policy-corrections', ['--apply' => true, '--administrator' => $admin->id])->assertSuccessful();
        $this->assertSame($audits, AuditLog::count());
    }

    public function test_public_session_choices_put_the_diagnostic_first(): void
    {
        SessionType::factory()->create(['slug' => 'two-hour', 'title' => 'Two-Hour Single Session', 'duration_minutes' => 120]);
        $diagnostic = SessionType::factory()->create(['slug' => 'diagnostic-session', 'title' => 'Diagnostic & Learning Roadmap', 'duration_minutes' => 60]);
        SessionType::factory()->create(['slug' => 'one-hour', 'title' => 'One-Hour Single Session', 'duration_minutes' => 60]);
        Livewire::test(BookingWizard::class)->assertSet('selectedSessionTypeId', $diagnostic->id)
            ->assertSeeInOrder(['Diagnostic & Learning Roadmap', 'One-Hour Single Session', 'Two-Hour Single Session']);
    }
}
