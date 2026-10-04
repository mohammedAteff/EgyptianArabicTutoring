<?php

namespace Tests\Feature;

use App\Domains\Analytics\Services\EngagementCounterService;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Exceptions\BookingPolicyViolationException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\RescheduleService;
use App\Domains\Notifications\Services\TelegramReadService;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\BillingReconciliationService;
use App\Domains\Students\Services\CashierReportService;
use App\Domains\Students\Services\EntitlementMappingService;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class TypedEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    private function purchase(Student $student, string $code, int $units = 1, ?string $expiry = null): StudentPackage
    {
        return app(StudentLedgerService::class)->createPackage($student, 'Synthetic '.$code, $units, '40.00', '0.00', 'USD', $expiry, fake()->uuid(), entitlementCode: $code);
    }

    private function lesson(string $code, int $units = 1, int $minutes = 60): SessionType
    {
        return SessionType::query()->create(['title' => 'Synthetic '.$code, 'slug' => fake()->unique()->slug(), 'duration_minutes' => $minutes, 'price' => '40.00', 'currency' => 'USD', 'active' => true, 'funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::query()->where('code', $code)->value('id'), 'required_entitlement_units' => $units]);
    }

    private function debit(Student $student, SessionType $type): Booking
    {
        return DB::transaction(function () use ($student, $type): Booking {
            Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            $booking = Booking::factory()->create(['student_id' => $student->id, 'session_type_id' => $type->id]);
            app(StudentLedgerService::class)->consumeForBooking($booking, 'debit-'.$booking->id);

            return $booking;
        });
    }

    public function test_both_types_spend_only_their_own_allocation_and_snapshot(): void
    {
        $student = Student::factory()->verified()->create();
        $one = $this->purchase($student, 'one_hour', 2);
        $two = $this->purchase($student, 'two_hour', 4);
        foreach (['one_hour' => $one, 'two_hour' => $two] as $code => $package) {
            $booking = $this->debit($student, $this->lesson($code));
            $this->assertSame($code, $booking->entitlement_code);
            $this->assertSame($package->id, $booking->student_package_id);
            $entry = SessionLedgerEntry::findOrFail($booking->consumed_ledger_entry_id);
            $this->assertSame(-1, $entry->credit_change);
            $this->assertSame($booking->student_package_entitlement_id, $entry->student_package_entitlement_id);
        }
        $totals = app(EntitlementService::class)->forStudent($student->id);
        $this->assertSame(1, $totals['one_hour']['available']);
        $this->assertSame(3, $totals['two_hour']['available']);
        $this->assertCount(0, app(BillingReconciliationService::class)->checkTypedEntitlements());
    }

    public function test_two_one_hour_rights_cannot_pay_for_a_two_hour_lesson(): void
    {
        $student = Student::factory()->verified()->create();
        $this->purchase($student, 'one_hour', 2);
        try {
            $this->debit($student, $this->lesson('two_hour'));
            $this->fail('Conversion must never occur.');
        } catch (InvalidArgumentException) {
        }
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseMissing('session_ledger_entries', ['entry_type' => 'session_consumed']);
    }

    public function test_reconciliation_batches_booking_lookups_and_includes_deleted_booking_history(): void
    {
        $student = Student::factory()->verified()->create();
        $this->purchase($student, 'one_hour', 6);
        $type = $this->lesson('one_hour');
        $firstBooking = $this->debit($student, $type);

        DB::enableQueryLog();
        $this->assertSame([], app(BillingReconciliationService::class)->checkTypedEntitlements());
        $singleBookingQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        for ($bookingIndex = 0; $bookingIndex < 5; $bookingIndex++) {
            $this->debit($student, $type);
        }
        $firstBooking->delete();

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->assertSame([], app(BillingReconciliationService::class)->checkTypedEntitlements());
            $this->assertSame($singleBookingQueries, count(DB::getQueryLog()));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_minutes_are_explanatory_and_do_not_select_entitlements(): void
    {
        $student = Student::factory()->verified()->create();
        $this->purchase($student, 'two_hour');
        $booking = $this->debit($student, $this->lesson('two_hour', 1, 60));
        $this->assertSame('two_hour', $booking->entitlement_code);
    }

    public function test_selection_skips_incompatible_expired_cancelled_and_unclassified_history(): void
    {
        $student = Student::factory()->verified()->create();
        $this->purchase($student, 'two_hour', 20, now()->addDay()->toDateString());
        $this->purchase($student, 'one_hour', 1, now()->subDay()->toDateString());
        $cancelled = $this->purchase($student, 'one_hour', 1, now()->addDay()->toDateString());
        $cancelled->update(['status' => 'cancelled']);
        $eligible = $this->purchase($student, 'one_hour', 1, now()->addDays(2)->toDateString());
        $this->purchase($student, 'one_hour', 1, now()->addDays(3)->toDateString());
        $legacy = StudentPackage::factory()->create(['student_id' => $student->id]);
        SessionLedgerEntry::factory()->create(['student_id' => $student->id, 'student_package_id' => $legacy->id, 'entry_type' => 'package_grant', 'credit_change' => 999]);
        $this->assertSame($eligible->id, $this->debit($student, $this->lesson('one_hour'))->student_package_id);
    }

    public function test_required_units_are_exact_and_not_split_across_purchases(): void
    {
        $student = Student::factory()->verified()->create();
        $this->purchase($student, 'one_hour', 1);
        $this->purchase($student, 'one_hour', 1);
        $this->expectException(InvalidArgumentException::class);
        $this->debit($student, $this->lesson('one_hour', 2));
    }

    public function test_cancellation_restores_exact_multi_unit_original_type_after_configuration_changes(): void
    {
        $student = Student::factory()->verified()->create();
        $package = $this->purchase($student, 'one_hour', 3);
        $type = $this->lesson('one_hour', 2);
        $booking = $this->debit($student, $type);
        $type->update(['required_entitlement_type_id' => EntitlementType::query()->where('code', 'two_hour')->value('id'), 'required_entitlement_units' => 1]);
        $restore = DB::transaction(fn () => app(StudentLedgerService::class)->restoreCancellation($booking, 'restore-'.$booking->id));
        $replay = DB::transaction(fn () => app(StudentLedgerService::class)->restoreCancellation($booking, 'restore-'.$booking->id));
        $this->assertSame($restore->id, $replay->id);
        $this->assertSame(2, $restore->credit_change);
        $this->assertSame($booking->student_package_entitlement_id, $restore->student_package_entitlement_id);
        $this->assertSame(3, app(EntitlementService::class)->projection($package)[0]['remaining']);
    }

    public function test_legacy_debits_remain_reversible_without_classification(): void
    {
        $student = Student::factory()->verified()->create();
        $package = StudentPackage::factory()->create(['student_id' => $student->id]);
        $booking = Booking::factory()->create(['student_id' => $student->id]);
        SessionLedgerEntry::factory()->create(['student_id' => $student->id, 'student_package_id' => $package->id, 'booking_id' => $booking->id, 'entry_type' => 'session_consumed', 'credit_change' => -2]);
        $restore = DB::transaction(fn () => app(StudentLedgerService::class)->restoreCancellation($booking, 'legacy-restore'));
        $this->assertSame(2, $restore->credit_change);
        $this->assertNull($restore->entitlement_type_id);
    }

    public function test_purchase_identity_and_terms_survive_product_display_changes(): void
    {
        $student = Student::factory()->verified()->create();
        foreach (StudentLedgerService::PRESETS as $key => $preset) {
            $package = app(StudentLedgerService::class)->createPackage($student, 'Forged name', 999, '1.00', '0.00', 'USD', null, fake()->uuid(), presetKey: $key);
            $this->assertSame($key, $package->offering_key);
            $this->assertSame($preset['name'], $package->package_name);
            $this->assertSame($preset['entitlement_code'], $package->entitlements->first()->type->code);
            $this->assertSame($preset['sessions'], $package->entitlements->first()->granted_quantity);
        }
    }

    public function test_custom_purchase_requires_an_explicit_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(StudentLedgerService::class)->createPackage(Student::factory()->create(), 'Custom', 1, '1.00', '0.00', 'USD', null, 'untyped-grant');
    }

    public function test_purchase_replay_rejects_changed_payload_or_type(): void
    {
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Custom', 1, '1.00', '0.00', 'USD', null, 'same-key', entitlementCode: 'one_hour');
        $this->assertSame($package->id, $ledger->createPackage($student, 'Custom', 1, '1.00', '0.00', 'USD', null, 'same-key', entitlementCode: 'one_hour')->id);
        $this->expectException(InvalidArgumentException::class);
        $ledger->createPackage($student, 'Custom', 1, '1.00', '0.00', 'USD', null, 'same-key', entitlementCode: 'two_hour');
    }

    public function test_courtesy_adjustments_are_explicit_bounded_and_not_cross_package(): void
    {
        $student = Student::factory()->verified()->create();
        $one = $this->purchase($student, 'one_hour');
        $two = $this->purchase($student, 'two_hour');
        $ledger = app(StudentLedgerService::class);
        $allocation = $one->entitlements->first();
        $entry = $ledger->adjustCredits($one, 2, 'Courtesy', 'courtesy-key', allocationId: $allocation->id);
        $this->assertSame($entry->id, $ledger->adjustCredits($one, 2, 'Courtesy', 'courtesy-key', allocationId: $allocation->id)->id);
        $ledger->adjustCredits($one, -2, 'Correction', 'negative-key', allocationId: $allocation->id);
        $this->assertSame(1, app(EntitlementService::class)->projection($one->fresh())[0]['remaining']);
        $this->expectException(InvalidArgumentException::class);
        $ledger->adjustCredits($one, 1, 'Injection', 'bad-key', allocationId: $two->entitlements->first()->id);
    }

    public function test_negative_courtesy_cannot_spend_another_type(): void
    {
        $student = Student::factory()->verified()->create();
        $one = $this->purchase($student, 'one_hour');
        $this->purchase($student, 'two_hour', 50);
        $this->expectException(InvalidArgumentException::class);
        app(StudentLedgerService::class)->adjustCredits($one, -2, 'Too much', 'negative-too-much', allocationId: $one->entitlements->first()->id);
    }

    public function test_refund_forfeiture_is_typed_and_money_only_refunds_leave_rights_unchanged(): void
    {
        $student = Student::factory()->verified()->create();
        $package = $this->purchase($student, 'two_hour', 2);
        $ledger = app(StudentLedgerService::class);
        $payment = $ledger->recordPayment($package, '40.00', 'paid', null);
        $ledger->refund($payment, '5.00', 'money-only', null);
        $this->assertSame(2, app(EntitlementService::class)->projection($package->fresh())[0]['remaining']);
        $ledger->refund($payment, '5.00', 'typed-refund', null, 'Cancel one right', 1, $package->entitlements->first()->id);
        $entry = SessionLedgerEntry::query()->where('entry_type', 'expiration_forfeit')->firstOrFail();
        $this->assertSame(-1, $entry->credit_change);
        $this->assertSame($package->entitlements->first()->entitlement_type_id, $entry->entitlement_type_id);
    }

    public function test_offering_filter_matches_all_purchases_and_custom_history(): void
    {
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        foreach ([1, 2] as $number) {
            $package = $ledger->createPackage($student, 'Different display', 1, '1.00', '0.00', 'USD', null, 'offering-'.$number, presetKey: 'advanced_conversational');
            $ledger->recordPayment($package, '28.00', 'payment-'.$number, null);
        }
        $custom = $this->purchase($student, 'one_hour');
        $ledger->recordPayment($custom, '1.00', 'custom-payment', null);
        $report = app(CashierReportService::class);
        $filters = $report->filters(Request::create('/', 'GET', ['offering_key' => 'advanced_conversational']));
        $rows = iterator_to_array($report->rows($filters));
        $this->assertCount(2, $rows);
        $this->assertSame('advanced_conversational', $rows[0][13]);
        $this->assertCount(1, iterator_to_array($report->rows(['offering_key' => 'custom_unclassified'])));
    }

    public function test_reviewed_mapping_is_replayable_and_preserves_facts_and_signed_balance(): void
    {
        $student = Student::factory()->verified()->create();
        $package = StudentPackage::factory()->create(['student_id' => $student->id, 'total_sessions_allocated' => 2]);
        SessionLedgerEntry::factory()->create(['student_id' => $student->id, 'student_package_id' => $package->id, 'entry_type' => 'package_grant', 'credit_change' => 2]);
        $mapping = app(EntitlementMappingService::class);
        $before = $mapping->snapshot($package->id);
        $manifest = ['package_id' => $package->id, 'fingerprint' => $before['fingerprint'], 'offering_key' => 'custom', 'entitlement_code' => 'two_hour', 'reviewer' => 'Synthetic reviewer', 'evidence' => 'Explicit local approval fixture'];
        $this->assertSame('preview', $mapping->review($manifest)['state']);
        $this->assertSame('mapped', $mapping->review($manifest, true)['state']);
        $this->assertSame('replayed', $mapping->review($manifest, true)['state']);
        $after = $mapping->snapshot($package->id);
        $this->assertSame($before['balance'], $after['balance']);
        foreach (['original_price', 'final_price', 'discount_amount', 'currency', 'created_at', 'updated_at', 'student_id'] as $field) {
            $this->assertSame($before['facts']['package'][$field], $after['facts']['package'][$field]);
        }
        foreach (['credit_change', 'created_at', 'idempotency_key', 'booking_id'] as $field) {
            $this->assertSame($before['facts']['entries'][0][$field], $after['facts']['entries'][0][$field]);
        }
    }

    public function test_ambiguous_history_is_never_guessed_and_stale_mapping_is_rejected(): void
    {
        $package = StudentPackage::factory()->create(['package_name' => 'Foundation Coaching Track']);
        $mapping = app(EntitlementMappingService::class);
        $snapshot = $mapping->snapshot($package->id);
        $this->assertSame([], app(EntitlementService::class)->projection($package));
        $this->assertSame('legacy_unclassified', $package->fresh()->identity_state);
        $package->update(['status' => 'cancelled']);
        $this->expectException(InvalidArgumentException::class);
        $mapping->review(['package_id' => $package->id, 'fingerprint' => $snapshot['fingerprint'], 'offering_key' => 'foundation_track', 'entitlement_code' => 'two_hour', 'reviewer' => 'Fixture', 'evidence' => 'Stale fixture'], true);
    }

    public function test_telegram_labels_preserve_distinct_types_and_purchase_provenance(): void
    {
        $student = Student::factory()->verified()->create();
        $package = $this->purchase($student, 'two_hour', 4);
        $data = app(TelegramReadService::class)->package($package);
        $this->assertSame('2-hour sessions: 4', $data['remaining_credits']);
        $this->assertSame('custom', $data['offering_key']);
        $this->assertSame($package->id, $data['purchase_id']);
    }

    public function test_reschedule_keeps_original_debit_for_same_or_compatible_lesson_and_rejects_incompatible_type(): void
    {
        $student = Student::factory()->verified()->create();
        $this->purchase($student, 'one_hour', 3);
        $type = $this->lesson('one_hour');
        $compatible = $this->lesson('one_hour');
        $incompatible = $this->lesson('two_hour');
        $booking = $this->debit($student, $type);
        $target = CarbonImmutable::now('Africa/Cairo')->addDays(12)->setTime(12, 0);
        AvailabilityRule::query()->create(['weekday' => $target->dayOfWeek, 'start_time' => '00:00:00', 'end_time' => '23:59:00', 'session_duration_minutes' => 60, 'buffer_minutes' => 0, 'min_notice_hours' => 0, 'max_horizon_days' => 90, 'enabled' => true]);
        $service = app(RescheduleService::class);
        $changed = $service->reschedule($booking, $target->utc(), performedBy: 'admin', newSessionTypeId: $compatible->id);
        $this->assertSame($compatible->id, $changed->session_type_id);
        $this->assertSame($booking->consumed_ledger_entry_id, $changed->consumed_ledger_entry_id);
        $again = $service->reschedule($changed, $target->addHour()->utc(), performedBy: 'admin');
        $this->assertSame($booking->consumed_ledger_entry_id, $again->consumed_ledger_entry_id);
        try {
            $service->reschedule($again, $target->addHours(2)->utc(), performedBy: 'admin', newSessionTypeId: $incompatible->id);
            $this->fail('Conversion must be rejected.');
        } catch (BookingPolicyViolationException) {
        }
        $this->assertSame(1, SessionLedgerEntry::query()->where('booking_id', $booking->id)->where('entry_type', 'session_consumed')->count());
        $this->assertSame($compatible->id, $booking->fresh()->session_type_id);
    }

    public function test_database_rejects_cross_student_allocation_and_incomplete_booking_snapshot(): void
    {
        $owner = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $package = $this->purchase($owner, 'one_hour');
        $allocation = $package->entitlements->first();
        try {
            DB::table('session_ledger_entries')->insert(['student_id' => $other->id, 'student_package_id' => $package->id, 'student_package_entitlement_id' => $allocation->id, 'entitlement_type_id' => $allocation->entitlement_type_id, 'entry_type' => 'courtesy_adjustment', 'credit_change' => 1, 'idempotency_key' => 'cross-owner', 'created_at' => now()]);
            $this->fail('Ownership must be enforced by the database.');
        } catch (QueryException) {
        }
        $booking = Booking::factory()->create(['student_id' => $owner->id]);
        try {
            DB::table('bookings')->where('id', $booking->id)->update(['funding_mode' => 'package']);
            $this->fail('Partial typed snapshots must be rejected.');
        } catch (QueryException) {
        }
        $this->assertSame('direct', $booking->fresh()->funding_mode);
    }

    public function test_merge_preserves_typed_ownership_and_booking_provenance(): void
    {
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $package = $this->purchase($secondary, 'two_hour');
        $booking = $this->debit($secondary, $this->lesson('two_hour'));
        app(StudentMergeService::class)->merge($primary->id, $secondary->id);
        $this->assertSame($primary->id, $package->fresh()->student_id);
        $this->assertSame($primary->id, $package->entitlements->first()->fresh()->student_id);
        $this->assertSame($primary->id, $booking->fresh()->student_id);
        $this->assertSame($primary->id, SessionLedgerEntry::findOrFail($booking->consumed_ledger_entry_id)->student_id);
        $this->assertCount(0, app(BillingReconciliationService::class)->checkTypedEntitlements());
    }

    public function test_teaching_hours_use_actual_completed_utc_duration_after_typed_debit(): void
    {
        $student = Student::factory()->verified()->create();
        $this->purchase($student, 'two_hour', 2);
        $type = $this->lesson('two_hour', 1, 120);
        $booking = $this->debit($student, $type);
        $start = now('UTC')->subDay()->startOfHour();
        $booking->update(['start_at_utc' => $start, 'end_at_utc' => $start->copy()->addMinutes(75), 'status' => 'completed']);
        $other = $this->debit($student, $type);
        $other->update(['status' => 'no_show']);
        $this->assertSame(1.25, app(EngagementCounterService::class)->getCollectiveLearningActivity()['lesson_hours']);
        $this->assertSame(2, SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->count());
        $this->assertSame(0, app(EntitlementService::class)->forStudent($student->id)['two_hour']['available']);
    }

    public function test_portal_preserves_separate_balances_and_each_purchase_expiry(): void
    {
        $student = Student::factory()->verified()->create();
        $one = $this->purchase($student, 'one_hour', 2, '2027-01-15');
        $two = $this->purchase($student, 'two_hour', 4, '2027-03-31');
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id,
            'student_authenticated_at' => now('UTC')->toIso8601String(),
            'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
        $response = $this->get(route('student.dashboard'))->assertOk()
            ->assertSeeText('1-hour sessions: 2')->assertSeeText('2-hour sessions: 4')
            ->assertSeeText('2027-01-15')->assertSeeText('2027-03-31')
            ->assertSeeText('Purchase #'.$one->id)->assertSeeText('Purchase #'.$two->id);
        $this->assertSame(2, $response->viewData('entitlementBalances')['one_hour']['available']);
        $this->assertSame(4, $response->viewData('entitlementBalances')['two_hour']['available']);
    }

    public function test_offering_screen_csv_xlsx_have_identical_purchase_and_type_provenance(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $ids = [];
        foreach ([1, 2] as $number) {
            $package = $ledger->createPackage($student, 'Display', 1, '1', '0', 'USD', null, 'typed-export-'.$number, presetKey: 'advanced_conversational');
            $ids[] = $package->id;
            $ledger->recordPayment($package, '28', 'typed-payment-'.$number, null);
        }
        $other = $this->purchase($student, 'two_hour');
        $ledger->recordPayment($other, '40', 'other-typed-payment', null);
        $filters = ['offering_key' => 'advanced_conversational'];
        $screen = $this->actingAs($admin, 'web')->get(route('admin.billing.cashier', $filters))->assertOk();
        $this->assertCount(2, $screen->viewData('transactionRows'));
        $csv = $this->get(route('admin.billing.export', $filters))->assertOk()->streamedContent();
        $this->assertStringContainsString('advanced_conversational', $csv);
        $this->assertStringContainsString('1-hour sessions', $csv);
        $this->assertStringNotContainsString($other->package_name, $csv);
        $download = $this->get(route('admin.billing.export', $filters + ['format' => 'xlsx']))->assertOk()->baseResponse;
        $path = $download->getFile()->getPathname();
        try {
            $rows = IOFactory::load($path)->getActiveSheet()->toArray();
            $this->assertCount(3, $rows);
            $this->assertSame('advanced_conversational', $rows[1][13]);
            $this->assertEqualsCanonicalizing($ids, [$rows[1][14], $rows[2][14]]);
            $this->assertStringContainsString('1-hour sessions', $rows[1][15]);
        } finally {
            unlink($path);
        }
    }

    public function test_reviewed_consumption_and_restoration_preserve_original_event_facts(): void
    {
        $student = Student::factory()->verified()->create();
        $package = StudentPackage::factory()->create(['student_id' => $student->id, 'total_sessions_allocated' => 3]);
        $booking = Booking::factory()->create(['student_id' => $student->id]);
        foreach ([['package_grant', 3, null], ['session_consumed', -2, $booking->id], ['cancellation_restore', 2, $booking->id]] as [$kind, $units, $bookingId]) {
            SessionLedgerEntry::factory()->create(['student_id' => $student->id, 'student_package_id' => $package->id, 'booking_id' => $bookingId, 'entry_type' => $kind, 'credit_change' => $units]);
        }
        $mapping = app(EntitlementMappingService::class);
        $before = $mapping->snapshot($package->id);
        $mapping->review(['package_id' => $package->id, 'fingerprint' => $before['fingerprint'], 'offering_key' => 'custom', 'entitlement_code' => 'one_hour', 'reviewer' => 'Fixture reviewer', 'evidence' => 'Explicit synthetic history review'], true);
        $this->assertSame(2, $booking->fresh()->entitlement_units);
        $this->assertSame(3, app(EntitlementService::class)->projection($package->fresh())[0]['remaining']);
        $this->assertCount(0, app(BillingReconciliationService::class)->checkTypedEntitlements());
        $after = $mapping->snapshot($package->id);
        foreach ($before['facts']['entries'] as $index => $entry) {
            foreach (['credit_change', 'created_at', 'idempotency_key', 'booking_id'] as $field) {
                $this->assertSame($entry[$field], $after['facts']['entries'][$index][$field]);
            }
        }
    }

    public function test_mapping_rejects_unreconciled_grants_without_changing_history(): void
    {
        $package = StudentPackage::factory()->create(['total_sessions_allocated' => 3]);
        SessionLedgerEntry::factory()->create(['student_id' => $package->student_id, 'student_package_id' => $package->id, 'entry_type' => 'package_grant', 'credit_change' => 2]);
        $mapping = app(EntitlementMappingService::class);
        $before = $mapping->snapshot($package->id);
        try {
            $mapping->review(['package_id' => $package->id, 'fingerprint' => $before['fingerprint'], 'offering_key' => 'custom', 'entitlement_code' => 'one_hour', 'reviewer' => 'Fixture', 'evidence' => 'Unreconciled fixture'], true);
            $this->fail('Unreconciled history must not become spendable.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Reconcile historical', $exception->getMessage());
        }
        $this->assertSame($before, $mapping->snapshot($package->id));
        $this->assertDatabaseCount('student_package_entitlements', 0);
    }

    public function test_mapping_rejects_restoration_without_an_original_debit(): void
    {
        $package = StudentPackage::factory()->create(['total_sessions_allocated' => 2]);
        $booking = Booking::factory()->create(['student_id' => $package->student_id]);
        foreach ([['package_grant', 2, null], ['cancellation_restore', 1, $booking->id]] as [$kind, $units, $bookingId]) {
            SessionLedgerEntry::factory()->create(['student_id' => $package->student_id, 'student_package_id' => $package->id, 'booking_id' => $bookingId, 'entry_type' => $kind, 'credit_change' => $units]);
        }
        $mapping = app(EntitlementMappingService::class);
        $before = $mapping->snapshot($package->id);
        $this->expectException(InvalidArgumentException::class);
        $mapping->review(['package_id' => $package->id, 'fingerprint' => $before['fingerprint'], 'offering_key' => 'custom', 'entitlement_code' => 'one_hour', 'reviewer' => 'Fixture', 'evidence' => 'Orphan restoration fixture'], true);
    }

    public function test_historical_null_expiry_requires_reviewed_terms_before_canonical_mapping(): void
    {
        $package = StudentPackage::factory()->create(['total_sessions_allocated' => 8, 'expiration_date' => null]);
        SessionLedgerEntry::factory()->create(['student_id' => $package->student_id, 'student_package_id' => $package->id, 'entry_type' => 'package_grant', 'credit_change' => 8]);
        $mapping = app(EntitlementMappingService::class);
        $before = $mapping->snapshot($package->id);
        $manifest = ['package_id' => $package->id, 'fingerprint' => $before['fingerprint'], 'offering_key' => 'foundation_track', 'entitlement_code' => 'two_hour', 'reviewer' => 'Fixture', 'evidence' => 'Reviewed pending-settlement expiry terms'];
        try {
            $mapping->review($manifest, true);
            $this->fail('Missing expiry terms must not grant unlimited availability.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Review null expiry terms', $exception->getMessage());
        }
        $mapping->review($manifest + ['validity_days' => 75], true);
        $this->assertNull($package->fresh()->expiration_date);
        $this->assertSame(75, $package->fresh()->validity_days);
        $this->assertFalse(app(EntitlementService::class)->eligible($package->fresh()));
        $this->assertSame(8, app(EntitlementService::class)->projection($package->fresh())[0]['remaining']);
    }

    public function test_privacy_erasure_preserves_typed_financial_and_debit_provenance(): void
    {
        $student = Student::factory()->verified()->create();
        $package = $this->purchase($student, 'two_hour', 3);
        app(StudentLedgerService::class)->recordPayment($package, '40', 'typed-privacy-payment', null);
        $booking = $this->debit($student, $this->lesson('two_hour'));
        $fields = ['student_package_id', 'student_package_entitlement_id', 'consumed_ledger_entry_id', 'entitlement_type_id', 'entitlement_code', 'entitlement_units'];
        $before = $booking->only($fields);
        $entries = DB::table('session_ledger_entries')->where('student_package_id', $package->id)->orderBy('id')->get()->toArray();
        app(StudentPrivacyService::class)->anonymize($student->id);
        $this->assertSame($before, $booking->fresh()->only($fields));
        $this->assertEquals($entries, DB::table('session_ledger_entries')->where('student_package_id', $package->id)->orderBy('id')->get()->toArray());
        $this->assertSame('40.00', app(StudentLedgerService::class)->summary($package->fresh())['net_paid']);
        $this->assertSame(2, app(EntitlementService::class)->projection($package->fresh())[0]['remaining']);
    }

    public function test_booking_ui_does_not_pool_small_allocations_to_meet_a_multi_unit_requirement(): void
    {
        $student = Student::factory()->verified()->create();
        $this->purchase($student, 'one_hour', 1);
        $this->purchase($student, 'one_hour', 1);
        $lesson = $this->lesson('one_hour', 2);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id,
            'student_authenticated_at' => now('UTC')->toIso8601String(), 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
        $response = $this->get(route('student.bookings.create', ['session_type_id' => $lesson->id]))->assertOk()
            ->assertSeeText('There are no compatible entitlements available for this lesson.');
        $this->assertSame(0, $response->viewData('availableCredits'));
        $this->assertSame([], $response->viewData('slots'));
        $this->assertSame(2, $response->viewData('entitlementBalances')['one_hour']['available']);
    }
}
