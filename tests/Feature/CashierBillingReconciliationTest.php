<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\BillingReconciliationService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashierBillingReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected StudentLedgerService $ledger;

    protected BillingReconciliationService $reconciliation;

    protected Administrator $admin;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(StudentLedgerService::class);
        $this->reconciliation = app(BillingReconciliationService::class);

        $this->admin = Administrator::create([
            'email' => 'admin@boltlanding.test',
            'name' => 'Cashier Admin',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $this->student = Student::create([
            'first_name' => 'Layla',
            'last_name' => 'Hassan',
            'name_normalized' => 'layla hassan',
            'email' => 'layla@example.com',
            'email_normalized' => 'layla@example.com',
            'phone' => '+201099999999',
            'phone_normalized' => '+201099999999',
            'identity_status' => 'verified',
            'internal_notes' => 'Test Student',
        ]);
    }

    public function test_canonical_product_presets_match_exact_specifications(): void
    {
        $presets = StudentLedgerService::PRESETS;

        // Diagnostic & Roadmap
        $this->assertEquals('25.00', $presets['diagnostic_roadmap']['price']);
        $this->assertEquals(1, $presets['diagnostic_roadmap']['sessions']);
        $this->assertEquals(14, $presets['diagnostic_roadmap']['validity_days']);

        // Foundation Track
        $this->assertEquals('280.00', $presets['foundation_track']['price']);
        $this->assertEquals('255.00', $presets['foundation_track']['discounted_price']);
        $this->assertEquals(8, $presets['foundation_track']['sessions']);
        $this->assertEquals(75, $presets['foundation_track']['validity_days']);

        // Fluency Immersion Track
        $this->assertEquals('390.00', $presets['fluency_track']['price']);
        $this->assertEquals('365.00', $presets['fluency_track']['discounted_price']);
        $this->assertEquals(12, $presets['fluency_track']['sessions']);
        $this->assertEquals(100, $presets['fluency_track']['validity_days']);

        // Pay-As-You-Go Maintenance
        $this->assertEquals('48.00', $presets['payg_maintenance']['price']);
        $this->assertEquals(1, $presets['payg_maintenance']['sessions']);
        $this->assertEquals(30, $presets['payg_maintenance']['validity_days']);

        // Advanced Conversational
        $this->assertEquals('28.00', $presets['advanced_conversational']['price']);
        $this->assertEquals(1, $presets['advanced_conversational']['sessions']);
        $this->assertEquals(30, $presets['advanced_conversational']['validity_days']);
    }

    public function test_48_hour_diagnostic_credit_automation_logic(): void
    {
        // 1. Create and settle a Diagnostic Package for the student
        $diagPkg = $this->ledger->createPackage(
            $this->student,
            'Diagnostic & Roadmap',
            1,
            '25.00',
            '0.00',
            'USD',
            now()->addDays(14)->toDateString(),
            'idem_diag_pkg_1',
            $this->admin->id
        );

        $this->ledger->recordPayment(
            $diagPkg,
            '25.00',
            'idem_diag_pmt_1',
            $this->admin->id,
            'PayPal - Manual',
            'REF-DIAG-1'
        );
        $this->completeDiagnosticBooking();

        // Within 48 hours: Eligible for credit
        $eligibility = $this->ledger->checkDiagnosticCreditEligibility($this->student->id);
        $this->assertNotNull($eligibility);
        $this->assertTrue($eligibility['eligible']);
        $this->assertEquals('25.00', $eligibility['credit_amount']);

        // Enrolling in Foundation Track with $25 discount
        $foundationPkg = $this->ledger->createPackage(
            $this->student,
            'Foundation Coaching Track',
            8,
            '280.00',
            '25.00',
            'USD',
            $this->ledger->calculateExpirationDate(now('UTC'), 75),
            'idem_found_pkg_1',
            $this->admin->id
        );

        $this->assertEquals('280.00', $foundationPkg->original_price);
        $this->assertEquals('25.00', $foundationPkg->discount_amount);
        $this->assertEquals('255.00', $foundationPkg->final_price);

        // After claiming credit, second track is no longer eligible
        $secondEligibility = $this->ledger->checkDiagnosticCreditEligibility($this->student->id);
        $this->assertNull($secondEligibility);
    }

    public function test_cairo_midnight_expiration_calculation(): void
    {
        // 2026-09-26 18:00:00 UTC is 2026-09-26 21:00:00 Cairo
        $settlement = CarbonImmutable::parse('2026-09-26 18:00:00', 'UTC');
        $expDate = $this->ledger->calculateExpirationDate($settlement, 75);

        // Cairo midnight on Sep 26 + 75 days = Dec 10, 2026
        $expected = CarbonImmutable::parse('2026-09-26 00:00:00', 'Africa/Cairo')->addDays(75)->toDateString();
        $this->assertEquals($expected, $expDate);
    }

    public function test_preset_package_terms_and_expiry_are_server_authoritative_after_settlement(): void
    {
        $payload = [
            'preset_key' => 'foundation_track',
            'package_name' => 'Tampered name',
            'total_sessions_allocated' => 500,
            'original_price' => '1.00',
            'discount_amount' => '0.00',
            'currency' => 'EUR',
            'expiration_date' => now('Africa/Cairo')->addYear()->toDateString(),
            'package_idempotency_key' => (string) Str::uuid(),
        ];

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.students.packages.store', $this->student->id), $payload)
            ->assertRedirect();

        $package = StudentPackage::query()->firstOrFail();
        $this->assertSame('Foundation Coaching Track', $package->package_name);
        $this->assertSame(8, (int) $package->total_sessions_allocated);
        $this->assertSame('280.00', $package->original_price);
        $this->assertSame('0.00', $package->discount_amount);
        $this->assertSame('USD', $package->currency);
        $this->assertNull($package->expiration_date);
        $this->assertNull(DB::transaction(fn () => $this->ledger->selectAndLockEligiblePackageForBooking($this->student->id)));
        $this->get(route('admin.billing.cashier', ['student_id' => $this->student->id]))
            ->assertOk()->assertSeeText('Available Credits: 0');

        $this->ledger->recordPayment($package, '140.00', 'partial-payment-foundation', $this->admin->id);
        $this->assertNull($package->fresh()->expiration_date);
        $this->assertNull(DB::transaction(fn () => $this->ledger->selectAndLockEligiblePackageForBooking($this->student->id)));

        $this->ledger->recordPayment($package, '140.00', 'settlement-foundation', $this->admin->id);
        $this->assertSame(
            $this->ledger->calculateExpirationDate(now('UTC'), 75),
            $package->fresh()->expiration_date->toDateString(),
        );
        $this->assertSame($package->id, DB::transaction(fn () => $this->ledger->selectAndLockEligiblePackageForBooking($this->student->id))?->id);
        $this->get(route('admin.billing.cashier', ['student_id' => $this->student->id]))
            ->assertOk()->assertSeeText('Available Credits: 8');
    }

    public function test_diagnostic_credit_requires_a_completed_and_fully_settled_diagnostic(): void
    {
        $diagnostic = $this->ledger->createPackage(
            $this->student, 'Diagnostic & Roadmap', 1, '25.00', '0.00', 'USD',
            now('Africa/Cairo')->addDays(14)->toDateString(), 'diagnostic-completion-gate', $this->admin->id,
        );
        $this->ledger->recordPayment($diagnostic, '10.00', 'diagnostic-partial', $this->admin->id);
        $this->assertNull($this->ledger->checkDiagnosticCreditEligibility($this->student->id));

        $this->ledger->recordPayment($diagnostic, '15.00', 'diagnostic-settlement', $this->admin->id);
        $this->assertNull($this->ledger->checkDiagnosticCreditEligibility($this->student->id));

        $this->completeDiagnosticBooking();
        $this->assertNotNull($this->ledger->checkDiagnosticCreditEligibility($this->student->id));
    }

    public function test_eligible_track_enrollment_applies_diagnostic_credit_on_the_server(): void
    {
        $diagnostic = $this->ledger->createPackage(
            $this->student, 'Diagnostic & Roadmap', 1, '25.00', '0.00', 'USD',
            now('Africa/Cairo')->addDays(14)->toDateString(), 'diagnostic-auto-credit', $this->admin->id,
        );
        $this->ledger->recordPayment($diagnostic, '25.00', 'diagnostic-auto-payment', $this->admin->id);
        $this->completeDiagnosticBooking();

        $this->actingAs($this->admin, 'web')->post(route('admin.students.packages.store', $this->student->id), [
            'preset_key' => 'foundation_track',
            'package_name' => 'Tampered name',
            'total_sessions_allocated' => 1,
            'original_price' => '1.00',
            'discount_amount' => '0.00',
            'currency' => 'EUR',
            'package_idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $foundation = StudentPackage::query()->latest('id')->firstOrFail();
        $this->assertSame('Foundation Coaching Track', $foundation->package_name);
        $this->assertSame('280.00', $foundation->original_price);
        $this->assertSame('25.00', $foundation->discount_amount);
        $this->assertSame('255.00', $foundation->final_price);
        $this->assertSame(8, (int) $foundation->total_sessions_allocated);
        $this->assertNull($this->ledger->checkDiagnosticCreditEligibility($this->student->id));
    }

    private function completeDiagnosticBooking(): void
    {
        $sessionType = SessionType::create([
            'title' => 'Diagnostic & Roadmap',
            'slug' => 'diagnostic-credit-test',
            'duration_minutes' => 60,
            'price' => 25,
            'currency' => 'USD',
            'active' => true,
        ]);
        $contact = Contact::create([
            'first_name' => $this->student->first_name,
            'last_name' => $this->student->last_name,
            'email' => $this->student->email,
        ]);
        $startsAt = CarbonImmutable::now('UTC')->subDay();
        $snapshot = app(TimezoneService::class)->createBookingSnapshot(
            $startsAt, $startsAt->addHour(), 'Africa/Cairo', 'Africa/Cairo',
        );
        $booking = Booking::create(array_merge($snapshot, [
            'contact_id' => $contact->id,
            'student_id' => $this->student->id,
            'session_type_id' => $sessionType->id,
            'status' => 'completed',
            'completed_at' => now('UTC'),
            'confirmation_token' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
        ]));

        DB::transaction(fn () => $this->ledger->consumeForBooking($booking, (string) Str::uuid()));
    }

    public function test_exact_decimal_arithmetic_and_remaining_balance(): void
    {
        $pkg = $this->ledger->createPackage(
            $this->student,
            'Foundation Track',
            8,
            '280.00',
            '0.00',
            'USD',
            now()->addDays(75)->toDateString(),
            'idem_bal_pkg_1',
            $this->admin->id
        );

        $this->assertEquals('280.00', $this->ledger->calculateRemainingBalance($pkg));

        // Partial payment: $140.00
        $this->ledger->recordPayment(
            $pkg,
            '140.00',
            'idem_bal_pmt_1',
            $this->admin->id
        );

        $this->assertEquals('140.00', $this->ledger->calculateRemainingBalance($pkg));

        // Settle remaining $140.00
        $this->ledger->recordPayment(
            $pkg,
            '140.00',
            'idem_bal_pmt_2',
            $this->admin->id
        );

        $this->assertEquals('0.00', $this->ledger->calculateRemainingBalance($pkg));
    }

    public function test_refund_lock_and_credit_forfeiture(): void
    {
        $pkg = $this->ledger->createPackage(
            $this->student,
            'Pay As You Go',
            1,
            '48.00',
            '0.00',
            'USD',
            now()->addDays(30)->toDateString(),
            'idem_ref_pkg_1',
            $this->admin->id
        );

        $payment = $this->ledger->recordPayment(
            $pkg,
            '48.00',
            'idem_ref_pmt_1',
            $this->admin->id
        );

        // Refund cannot exceed payment
        $this->expectException(\InvalidArgumentException::class);
        $this->ledger->refund($payment, '50.00', 'idem_ref_over', $this->admin->id);
    }

    public function test_refund_with_credit_forfeiture_appends_ledger_entry(): void
    {
        $pkg = $this->ledger->createPackage(
            $this->student,
            'Pay As You Go',
            1,
            '48.00',
            '0.00',
            'USD',
            now()->addDays(30)->toDateString(),
            'idem_ref_pkg_2',
            $this->admin->id
        );

        $payment = $this->ledger->recordPayment(
            $pkg,
            '48.00',
            'idem_ref_pmt_2',
            $this->admin->id
        );

        $refund = $this->ledger->refund(
            $payment,
            '48.00',
            'idem_ref_ok_1',
            $this->admin->id,
            'Customer cancellation',
            1 // forfeit 1 credit
        );

        $this->assertDatabaseHas('payment_refunds', [
            'id' => $refund->id,
            'amount_refunded' => '48.00',
        ]);

        $this->assertDatabaseHas('session_ledger_entries', [
            'student_package_id' => $pkg->id,
            'entry_type' => 'expiration_forfeit',
            'credit_change' => -1,
        ]);
    }

    public function test_refund_cannot_forfeit_more_credits_than_remain(): void
    {
        $package = $this->ledger->createPackage($this->student, 'One lesson', 1, '48.00', '0.00', 'USD', null, 'grant-forfeit-bound');
        $payment = $this->ledger->recordPayment($package, '48.00', 'payment-forfeit-bound', $this->admin->id);

        try {
            $this->ledger->refund($payment, '48.00', 'refund-forfeit-bound', $this->admin->id, null, 2);
            $this->fail('The refund must reject forfeiture beyond the available package balance.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Cannot forfeit more than the package remaining credits.', $exception->getMessage());
        }

        $this->assertDatabaseCount('payment_refunds', 0);
        $this->assertSame(1, (int) DB::table('session_ledger_entries')->where('student_package_id', $package->id)->sum('credit_change'));
    }

    public function test_cashier_rejects_duplicate_client_uuids_with_http_409_without_duplicate_ledger_rows(): void
    {
        $key = (string) Str::uuid();
        $payload = [
            'package_name' => 'Custom package',
            'total_sessions_allocated' => 2,
            'original_price' => '80.00',
            'discount_amount' => '0.00',
            'currency' => 'USD',
            'package_idempotency_key' => $key,
        ];

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.students.packages.store', $this->student->id), $payload)
            ->assertRedirect();
        $this->post(route('admin.students.packages.store', $this->student->id), $payload)
            ->assertStatus(409);

        $this->assertDatabaseCount('student_packages', 1);
        $this->assertDatabaseCount('session_ledger_entries', 1);

        $packageId = (int) DB::table('student_packages')->value('id');
        $paymentKey = (string) Str::uuid();
        $paymentPayload = [
            'amount_paid' => '40.00',
            'payment_method' => 'Cash',
            'payment_idempotency_key' => $paymentKey,
        ];
        $paymentRoute = route('admin.students.payments.store', ['student' => $this->student->id, 'package' => $packageId]);
        $this->post($paymentRoute, $paymentPayload)->assertRedirect();
        $this->post($paymentRoute, $paymentPayload)->assertStatus(409);
        $this->assertDatabaseCount('payment_records', 1);

        $creditKey = (string) Str::uuid();
        $creditPayload = [
            'credit_change' => 1,
            'description' => 'Tutor makeup lesson',
            'credit_idempotency_key' => $creditKey,
        ];
        $creditRoute = route('admin.students.credits.adjust', ['student' => $this->student->id, 'package' => $packageId]);
        $this->post($creditRoute, $creditPayload)->assertRedirect();
        $this->post($creditRoute, $creditPayload)->assertStatus(409);
        $this->assertDatabaseCount('session_ledger_entries', 2);

        $paymentId = (int) DB::table('payment_records')->value('id');
        $refundKey = (string) Str::uuid();
        $refundPayload = [
            'amount_refunded' => '10.00',
            'refund_idempotency_key' => $refundKey,
        ];
        $refundRoute = route('admin.students.refunds.store', ['student' => $this->student->id, 'payment' => $paymentId]);
        $this->post($refundRoute, $refundPayload)->assertRedirect();
        $this->post($refundRoute, $refundPayload)->assertStatus(409);
        $this->assertDatabaseCount('payment_refunds', 1);
    }

    public function test_reconciliation_diagnostic_detects_discrepancies(): void
    {
        // 1. Initial clean state -> 0 discrepancies
        $initial = $this->reconciliation->reconcile();
        $this->assertFalse($initial['has_discrepancies']);
        $this->assertEquals(0, $initial['total_discrepancies_count']);

        // 2. Introduce a deliberate negative balance package via raw query (bypassing model guards)
        $badPkgId = DB::table('student_packages')->insertGetId([
            'student_id' => $this->student->id,
            'package_name' => 'Bad Overpaid Package',
            'original_price' => '100.00',
            'discount_amount' => '0.00',
            'final_price' => '100.00',
            'currency' => 'USD',
            'total_sessions_allocated' => 5,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_records')->insert([
            'student_package_id' => $badPkgId,
            'student_id' => $this->student->id,
            'idempotency_key' => 'idem_raw_overpaid',
            'amount_paid' => '150.00', // Paid 150 against final price 100 -> negative balance -50
            'currency' => 'USD',
            'payment_method' => 'Cash',
            'paid_at' => now(),
            'created_at' => now(),
        ]);

        $report = $this->reconciliation->reconcile();
        $this->assertTrue($report['has_discrepancies']);
        $this->assertGreaterThanOrEqual(1, $report['summary']['negative_balances_count']);
    }

    public function test_cashier_hub_and_reconciliation_http_endpoints(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get('/admin/billing/cashier');
        $response->assertOk();
        $response->assertSee('Cashier Operations Hub');
        $response->assertSee('Foundation Coaching Track');

        $diagResponse = $this->actingAs($this->admin, 'web')->get('/admin/billing/check-diagnostic-credit/'.$this->student->id);
        $diagResponse->assertOk();
        $diagResponse->assertJsonStructure(['eligible', 'details']);

        $reconcileResponse = $this->actingAs($this->admin, 'web')->get('/admin/billing/reconcile');
        $reconcileResponse->assertOk();
        $reconcileResponse->assertSee('Billing Reconciliation Diagnostic');

        $exportFinancials = $this->actingAs($this->admin, 'web')->get('/admin/billing/export?format=csv');
        $exportFinancials->assertOk();
        $this->assertStringContainsString('text/csv', $exportFinancials->headers->get('Content-Type'));

        $exportReconcile = $this->actingAs($this->admin, 'web')->get('/admin/billing/reconcile/export?format=csv');
        $exportReconcile->assertOk();
        $this->assertStringContainsString('text/csv', $exportReconcile->headers->get('Content-Type'));
    }
}
