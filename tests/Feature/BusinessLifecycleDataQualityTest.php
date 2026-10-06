<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\OperationsReadModel;
use App\Domains\Administration\Services\StaffSavedViewService;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\AuditLogPresentation;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\BookingPolicyDecision;
use App\Domains\Booking\Models\BookingWaitlist;
use App\Domains\Booking\Models\RecurringLessonPlan;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingPolicyService;
use App\Domains\Booking\Services\CancellationService;
use App\Domains\Booking\Services\NoShowService;
use App\Domains\Booking\Services\RecurringLessonService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Reporting\Services\BusinessLifecycleReport;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\PackageInstallment;
use App\Domains\Students\Models\PackageRenewal;
use App\Domains\Students\Models\PaymentMethod;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Models\StudentUnavailability;
use App\Domains\Students\Services\DataQualityReadModel;
use App\Domains\Students\Services\InstallmentScheduleService;
use App\Domains\Students\Services\PackageRenewalService;
use App\Domains\Students\Services\ReceivablesReadModel;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use App\Domains\Students\Services\StudentRecordsQuery;
use App\Domains\Students\Services\StudentSchedulingService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class BusinessLifecycleDataQualityTest extends TestCase
{
    use RefreshDatabase;

    private Administrator $administrator;

    private Student $student;

    private SessionType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2031-01-01 00:00:00', 'UTC'));
        Setting::set('business_timezone', 'UTC');
        $this->administrator = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->student = Student::factory()->verified()->create(['preferred_timezone' => 'UTC']);
        $this->type = SessionType::factory()->create(['funding_mode' => 'package', 'duration_minutes' => 60,
            'required_entitlement_type_id' => EntitlementType::query()->where('code', 'one_hour')->value('id'), 'required_entitlement_units' => 1]);
        for ($day = 0; $day < 7; $day++) {
            AvailabilityRule::query()->create(['weekday' => $day, 'start_time' => '08:00:00', 'end_time' => '20:00:00',
                'session_duration_minutes' => 60, 'buffer_minutes' => 0, 'min_notice_hours' => 0, 'max_horizon_days' => 90, 'enabled' => true]);
        }
    }

    private function purchase(int $credits = 2): StudentPackage
    {
        return app(StudentLedgerService::class)->createPackage($this->student, 'Test purchase', $credits, '80.00', '0.00', 'USD', null, fake()->uuid(), entitlementCode: 'one_hour');
    }

    private function plan(array $overrides = []): RecurringLessonPlan
    {
        return app(RecurringLessonService::class)->create($this->student, array_replace([
            'session_type_id' => $this->type->id, 'cadence' => 'weekly', 'start_date' => '2031-01-08', 'end_date' => null,
            'occurrence_count' => 2, 'preferred_time' => '09:00', 'timezone' => 'UTC', 'fold_policy' => 'reject', 'idempotency_key' => fake()->uuid(),
        ], $overrides), $this->administrator->id);
    }

    private function booking(): Booking
    {
        $this->purchase();
        $occurrences = app(RecurringLessonService::class)->generate($this->plan(), $this->administrator->id, 1, 1);
        $this->assertSame('booked', $occurrences[0]->status, $occurrences[0]->reason_code ?? '');

        return Booking::query()->findOrFail($occurrences[0]->booking_id);
    }

    private function studentLogin(?Student $student = null): void
    {
        $student ??= $this->student;
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id,
            'student_authenticated_at' => now('UTC')->toIso8601String(), 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String()]);
    }

    public function test_recurring_generation_is_bounded_per_booking_typed_and_replay_safe(): void
    {
        $package = $this->purchase();
        $plan = $this->plan();
        $this->assertDatabaseCount('bookings', 0);
        $first = app(RecurringLessonService::class)->generate($plan, $this->administrator->id);
        $again = app(RecurringLessonService::class)->generate($plan, $this->administrator->id);
        $this->assertSame(array_column($first, 'id'), array_column($again, 'id'));
        $this->assertDatabaseCount('bookings', 2);
        $this->assertSame(2, SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->count());
        $this->assertSame(0, app(StudentLedgerService::class)->summary($package)['remaining_credits']);
        $this->assertSame(['one_hour'], Booking::query()->distinct()->pluck('entitlement_code')->all());
        $this->assertSame(['staff_recurring', 'staff_recurring'], BookingEvent::query()->where('event_type', 'created')->get()->pluck('new_data.source')->all());
    }

    public function test_recurring_never_overdraws_the_last_compatible_credit(): void
    {
        $this->purchase(1);
        $results = app(RecurringLessonService::class)->generate($this->plan(), $this->administrator->id);
        $this->assertSame(['booked', 'blocked'], array_map(fn ($row) => $row->status, $results));
        $this->assertSame('compatible_credit_unavailable', $results[1]->reason_code);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_free_recurring_lessons_use_no_package_ledger(): void
    {
        $this->type->update(['funding_mode' => 'free', 'required_entitlement_type_id' => null, 'required_entitlement_units' => null]);
        app(RecurringLessonService::class)->generate($this->plan(), $this->administrator->id, 1, 1);
        $this->assertDatabaseHas('bookings', ['funding_mode' => 'free', 'source' => 'staff_recurring']);
        $this->assertDatabaseCount('session_ledger_entries', 0);
    }

    public function test_student_holidays_and_tutor_blocked_dates_have_separate_safe_reasons(): void
    {
        $this->purchase();
        app(StudentSchedulingService::class)->holiday($this->student, ['date_from' => '2031-01-08', 'date_to' => '2031-01-08', 'timezone' => 'UTC', 'reason_code' => 'holiday', 'idempotency_key' => fake()->uuid()]);
        app(AvailabilityService::class)->saveException(['date' => '2031-01-15', 'is_blocked' => true], $this->administrator->id);
        $results = app(RecurringLessonService::class)->generate($this->plan(), $this->administrator->id);
        $this->assertSame(['student_unavailable', 'blocked_date'], array_map(fn ($row) => $row->reason_code, $results));
        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame(0, SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->count());
    }

    public function test_paused_plans_and_archived_students_cannot_generate_new_recurring_bookings(): void
    {
        $this->purchase();
        $plan = $this->plan();
        app(RecurringLessonService::class)->status($plan, 'paused', $this->administrator->id);
        $this->assertSame([], app(RecurringLessonService::class)->generate($plan, $this->administrator->id));
        app(RecurringLessonService::class)->status($plan, 'active', $this->administrator->id);
        app(StudentSchedulingService::class)->operationalStatus($this->student, 'archived', 'taking_break', $this->administrator->id);
        $this->expectException(ValidationException::class);
        app(RecurringLessonService::class)->generate($plan, $this->administrator->id);
    }

    public function test_plan_idempotency_rejects_changed_terms(): void
    {
        $this->plan(['idempotency_key' => 'fixed-plan']);
        $this->expectException(ValidationException::class);
        $this->plan(['idempotency_key' => 'fixed-plan', 'preferred_time' => '10:00']);
    }

    public function test_waitlist_is_idempotent_and_never_creates_booking_or_credit(): void
    {
        $data = ['date_from' => '2031-01-08', 'date_to' => '2031-01-15', 'timezone' => 'UTC', 'session_type_id' => $this->type->id, 'idempotency_key' => 'interest'];
        $one = app(StudentSchedulingService::class)->waitlist($this->student, $data);
        $two = app(StudentSchedulingService::class)->waitlist($this->student, $data);
        $this->assertSame($one->id, $two->id);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('session_ledger_entries', 0);
        $this->actingAs($this->administrator)->patch(route('admin.waitlist.update', $one), ['status' => 'contacted'])->assertRedirect();
        $this->assertSame('contacted', $one->refresh()->status);
    }

    public function test_new_policy_cannot_rewrite_booking_snapshot_and_legacy_rows_keep_legacy_rules(): void
    {
        $booking = $this->booking();
        app(BookingPolicyService::class)->save(['cancellation_cutoff_hours' => 168, 'late_cancellation' => 'retain', 'staff_cancellation' => 'retain', 'no_show' => 'restore'], $this->administrator->id);
        $this->assertSame('retain', app(BookingPolicyService::class)->forBooking($booking)['no_show']);
        DB::table('bookings')->where('id', $booking->id)->update(['policy_snapshot' => null]);
        $this->assertSame('restore', app(BookingPolicyService::class)->forBooking($booking->refresh())['staff_cancellation']);
        $this->expectException(\RuntimeException::class);
        $booking->update(['policy_snapshot' => ['no_show' => 'restore']]);
    }

    public function test_cancellation_restores_once_and_records_separate_typed_policy_decision(): void
    {
        $booking = $this->booking();
        app(CancellationService::class)->cancel($booking, 'admin', $this->administrator->id, 'Changed', 'schedule_change');
        app(CancellationService::class)->cancel($booking, 'admin', $this->administrator->id, 'Replay', 'schedule_change');
        $this->assertSame(1, BookingPolicyDecision::query()->where('booking_id', $booking->id)->count());
        $restored = SessionLedgerEntry::query()->where('booking_id', $booking->id)->where('entry_type', 'cancellation_restore')->sole();
        $this->assertSame($booking->student_package_entitlement_id, $restored->student_package_entitlement_id);
        $this->assertSame(1, $restored->credit_change);
    }

    public function test_configured_no_show_restoration_is_append_only_without_a_second_debit(): void
    {
        app(BookingPolicyService::class)->save(['cancellation_cutoff_hours' => 4, 'late_cancellation' => 'deny', 'staff_cancellation' => 'restore', 'no_show' => 'restore'], $this->administrator->id);
        $booking = $this->booking();
        app(NoShowService::class)->mark($booking, $this->administrator->id, 'missed_lesson');
        $this->assertDatabaseHas('booking_policy_decisions', ['booking_id' => $booking->id, 'action' => 'no_show', 'outcome' => 'restore']);
        $this->assertSame(1, SessionLedgerEntry::query()->where('booking_id', $booking->id)->where('entry_type', 'session_consumed')->count());
        $this->assertSame(1, SessionLedgerEntry::query()->where('booking_id', $booking->id)->where('entry_type', 'cancellation_restore')->count());
        $this->assertDatabaseHas('session_ledger_entries', ['booking_id' => $booking->id, 'description' => 'Original entitlement restored under no-show policy']);
        $this->expectException(\DomainException::class);
        app(NoShowService::class)->mark($booking->refresh(), $this->administrator->id);
    }

    public function test_retaining_cancellation_keeps_only_the_original_credit_consumption(): void
    {
        app(BookingPolicyService::class)->save(['cancellation_cutoff_hours' => 4, 'late_cancellation' => 'deny', 'staff_cancellation' => 'retain', 'no_show' => 'retain'], $this->administrator->id);
        $booking = $this->booking();
        app(CancellationService::class)->cancel($booking, 'admin', $this->administrator->id, 'Changed');
        $this->assertDatabaseHas('booking_policy_decisions', ['booking_id' => $booking->id, 'outcome' => 'retain']);
        $this->assertSame(0, SessionLedgerEntry::query()->where('entry_type', 'cancellation_restore')->count());
        $this->assertSame(1, SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->count());
    }

    public function test_installment_forecasts_reconcile_partial_payment_refunds_and_overpayment(): void
    {
        $package = $this->purchase();
        $service = app(InstallmentScheduleService::class);
        $rows = [['expected_amount' => '40.00', 'due_date' => '2030-12-31'], ['expected_amount' => '40.00', 'due_date' => '2031-02-01']];
        $service->create($package, $rows, 'schedule', $this->administrator->id);
        $service->create($package, $rows, 'schedule', $this->administrator->id);
        $this->assertDatabaseCount('package_installments', 2);
        $this->assertDatabaseCount('payment_records', 0);
        $ledger = app(StudentLedgerService::class);
        $payment = $ledger->recordPayment($package, '35.00', 'pay1', null);
        $projection = $service->projection($package->fresh());
        $this->assertSame('5.00', $projection[0]['due']);
        $this->assertSame('overdue', $projection[0]['status']);
        $ledger->recordPayment($package, '55.00', 'pay2', null);
        $this->assertSame(['paid', 'paid'], array_column($service->projection($package->fresh()), 'status'));
        $this->assertSame('10.00', $ledger->summary($package)['overpaid']);
        $ledger->refund($payment, '20.00', 'refund1', null, reason: 'Test refund');
        $this->assertSame('10.00', $ledger->summary($package)['balance_due']);
        $projection = $service->projection($package->fresh());
        $this->assertSame('10.00', $projection[1]['due']);
        $this->assertSame('partially_paid', $projection[1]['status']);
    }

    public function test_installment_totals_and_history_cannot_be_rewritten(): void
    {
        $package = $this->purchase();
        try {
            app(InstallmentScheduleService::class)->create($package, [['expected_amount' => '79', 'due_date' => '2031-02-01']], 'invalid', null);
            $this->fail('Mismatched totals must fail.');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('package_installments', 0);
        app(InstallmentScheduleService::class)->create($package, [['expected_amount' => '80', 'due_date' => '2031-02-01']], 'valid', null);
        $this->expectException(\RuntimeException::class);
        PackageInstallment::query()->sole()->update(['expected_amount' => '10.00']);
    }

    public function test_renewal_creates_one_linked_purchase_and_preserves_previous_terms(): void
    {
        $previous = $this->purchase();
        $before = $previous->refresh()->getAttributes();
        $data = ['renewal_date' => '2031-01-01', 'reason_code' => 'continuing_study', 'notes' => 'Continue', 'idempotency_key' => 'renew'];
        $one = app(PackageRenewalService::class)->renew($previous, $data, $this->administrator->id);
        $two = app(PackageRenewalService::class)->renew($previous, $data, $this->administrator->id);
        $this->assertSame($one->id, $two->id);
        $this->assertNotSame($previous->id, $one->new_package_id);
        $this->assertSame($before, $previous->refresh()->getAttributes());
        $this->assertDatabaseCount('student_packages', 2);
        $this->assertDatabaseCount('payment_records', 0);
        $this->assertSame(1, PackageRenewal::query()->count());
    }

    public function test_financial_screen_export_and_receipt_are_actual_and_owner_scoped(): void
    {
        $package = $this->purchase();
        $payment = app(StudentLedgerService::class)->recordPayment($package, '20.00', 'payment', null, method: 'Cash');
        $this->studentLogin();
        $this->get(route('student.statements.show', $package))->assertOk()->assertSee('20.00')->assertSee('Not recorded')->assertSee('Cash');
        $receipt = $this->get(route('student.receipts.show', [$package, $payment]));
        $receipt->assertOk()->assertSee('Actual payment #'.$payment->id)->assertHeader('Cache-Control', 'no-store, private');
        $csv = $this->get(route('student.statements.export', $package))->assertOk()->streamedContent();
        $this->assertStringContainsString('Actual payment', $csv);
        $this->assertStringContainsString('60.00', $csv);
        $other = Student::factory()->verified()->create();
        $this->studentLogin($other);
        $this->get(route('student.statements.show', $package))->assertNotFound();
        $this->get(route('student.statements.export', $package))->assertNotFound();
        $this->get(route('student.receipts.show', [$package, $payment]))->assertNotFound();
    }

    public function test_student_cannot_withdraw_another_students_interest_or_holiday(): void
    {
        $other = Student::factory()->verified()->create();
        $interest = BookingWaitlist::factory()->create(['student_id' => $other->id]);
        $holiday = StudentUnavailability::factory()->create(['student_id' => $other->id]);
        $this->studentLogin();
        $this->post(route('student.waitlist.withdraw', $interest))->assertNotFound();
        $this->post(route('student.holidays.cancel', $holiday))->assertNotFound();
        $this->assertSame('open', $interest->refresh()->status);
        $this->assertSame('active', $holiday->refresh()->status);
    }

    public function test_receivables_and_business_report_reconcile_same_canonical_balance_and_dates(): void
    {
        $package = $this->purchase();
        app(StudentLedgerService::class)->recordPayment($package, '35.00', 'payment', null);
        $row = iterator_to_array(app(ReceivablesReadModel::class)->rows(['state' => 'partial']))[0];
        $this->assertSame('45.00', $row['summary']['balance_due']);
        $filters = ['date_from' => '2031-01-01', 'date_to' => '2031-01-01', 'student_id' => $this->student->id, 'currency' => 'USD'];
        $report = app(BusinessLifecycleReport::class);
        $this->assertSame('35.00', $report->totals($filters)['currencies']['USD']['net']);
        $this->assertSame('45.00', $report->totals($filters)['currencies']['USD']['outstanding']);
        $this->actingAs($this->administrator);
        $this->get(route('admin.reports.lifecycle', $filters))->assertOk()->assertSee('35.00')->assertSee('45.00');
        $csv = $this->get(route('admin.reports.lifecycle.export', $filters))->assertOk()->streamedContent();
        $this->assertStringContainsString('35.00', $csv);
        $this->assertStringContainsString('45.00', $csv);
    }

    public function test_archive_excludes_default_roster_and_followups_but_preserves_booked_lessons_and_finance(): void
    {
        $booking = $this->booking();
        $this->travelTo(CarbonImmutable::parse($booking->start_at_utc));
        app(StudentSchedulingService::class)->operationalStatus($this->student, 'archived', 'taking_break', $this->administrator->id);
        $this->assertSame(0, app(StudentRecordsQuery::class)->query([])->whereKey($this->student->id)->count());
        $this->assertSame(1, app(StudentRecordsQuery::class)->query(['operational_status' => 'all'])->whereKey($this->student->id)->count());
        $today = app(OperationsReadModel::class)->forAdministrator($this->administrator);
        $this->assertTrue($today['todayLessons']->contains('id', $booking->id));
        $this->assertFalse($today['paymentFollowUps']->contains('student_id', $this->student->id));
        $this->assertSame(1, StudentPackage::query()->where('student_id', $this->student->id)->count());
        $this->assertSame('confirmed', $booking->refresh()->status);
    }

    public function test_normalized_duplicate_candidates_are_conservative_and_read_only(): void
    {
        $one = Student::factory()->verified()->create(['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => ' SAME@example.test ']);
        $two = Student::factory()->verified()->create(['first_name' => 'JANE', 'last_name' => 'Smith', 'email' => 'same@example.test']);
        Student::factory()->verified()->create(['first_name' => 'Other', 'last_name' => 'Name', 'email' => 'same@example.test']);
        $pairs = app(DataQualityReadModel::class)->duplicates('student');
        $this->assertContains(['first' => $one->id, 'second' => $two->id, 'signal' => 'same normalized name and email'], $pairs);
        $this->assertCount(1, $pairs);
        $this->assertSame(4, Student::query()->count());
    }

    public function test_audit_viewer_never_renders_legacy_secrets_or_raw_ip_and_filters_operational_changes(): void
    {
        $admin = AdministratorFactory::new()->create();
        $log = AuditLog::query()->create(['administrator_id' => $admin->id, 'action' => 'student_operational_status_updated', 'entity_type' => Student::class, 'entity_id' => $this->student->id, 'previous_data' => ['operational_status' => 'active'], 'new_data' => ['operational_status' => 'archived'], 'created_at' => now()]);
        DB::table('audit_logs')->where('id', $log->id)->update(['ip_address' => '192.0.2.77', 'new_values' => json_encode(['operational_status' => 'archived', 'totp_secret' => 'PRIVATESECRET', 'password' => 'PRIVATEPASSWORD', 'totp_code' => '654321', 'token_id' => 123456])]);
        $response = $this->actingAs($admin)->get(route('admin.audit-logs', ['domain' => 'student', 'actor' => 'admin', 'actor_id' => $admin->id]));
        $response->assertOk()->assertSee('archived')->assertDontSee('192.0.2.77')->assertDontSee('PRIVATESECRET')->assertDontSee('PRIVATEPASSWORD')->assertDontSee('654321')->assertDontSee('123456');
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('192.0.2.77')->assertDontSee('PRIVATESECRET')->assertDontSee('IP Address');
        $this->actingAs($this->administrator)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Recent Security & Operations Audit Log');
        $this->assertCount(1, app(AuditLogPresentation::class)->diff($log->refresh()));
    }

    public function test_assistants_cannot_access_financial_configuration_or_data_quality_routes(): void
    {
        $assistant = AdministratorFactory::new()->create(['role' => 'assistant']);
        foreach (['admin.recurring.index', 'admin.booking-policy.index', 'admin.waitlist.index', 'admin.receivables.index', 'admin.data-quality.index', 'admin.reports.lifecycle'] as $route) {
            $this->actingAs($assistant)->get(route($route))->assertForbidden();
        }
    }

    public function test_calendar_exception_changes_preserve_bookings_and_unrelated_cache(): void
    {
        $booking = $this->booking();
        Cache::put('unrelated-rate-limit', 17);
        $this->actingAs($this->administrator)->post(route('admin.availability.exceptions.store'), ['date' => '2031-01-08', 'is_blocked' => 1])->assertRedirect();
        $this->assertSame(17, Cache::get('unrelated-rate-limit'));
        $this->assertSame('confirmed', $booking->refresh()->status);
        $this->assertDatabaseHas('availability_exceptions', ['date' => '2031-01-08', 'type' => 'blocked']);
    }

    public function test_merge_and_privacy_preserve_financial_links_and_redact_new_operational_notes(): void
    {
        $package = $this->purchase();
        $plan = $this->plan();
        $interest = BookingWaitlist::factory()->create(['student_id' => $this->student->id, 'notes' => 'Private interest']);
        $holiday = StudentUnavailability::factory()->create(['student_id' => $this->student->id, 'notes' => 'Private holiday']);
        $renewal = app(PackageRenewalService::class)->renew($package, ['renewal_date' => '2031-01-01', 'idempotency_key' => 'privacy-renewal', 'notes' => 'Private renewal'], $this->administrator->id);
        $primary = Student::factory()->verified()->create();
        app(StudentMergeService::class)->merge($primary->id, $this->student->id, $this->administrator->id);
        $this->assertSame($primary->id, $plan->refresh()->student_id);
        $this->assertSame($primary->id, $renewal->refresh()->student_id);
        app(StudentPrivacyService::class)->anonymize($primary->id, $this->administrator->id);
        $this->assertSame('paused', $plan->refresh()->status);
        $this->assertNull($interest->refresh()->notes);
        $this->assertNull($holiday->refresh()->notes);
        $this->assertNull($renewal->refresh()->notes);
        $this->assertSame($package->id, $renewal->previous_package_id);
        $this->assertDatabaseCount('student_packages', 2);
    }

    public function test_recurring_dst_gap_and_ambiguous_fold_require_an_explicit_valid_time(): void
    {
        $this->purchase();
        foreach ([['2031-03-09', '02:30'], ['2031-11-02', '01:30']] as [$date, $time]) {
            $results = app(RecurringLessonService::class)->generate($this->plan(['start_date' => $date, 'preferred_time' => $time, 'timezone' => 'America/New_York']), $this->administrator->id, 1, 1);
            $this->assertSame('blocked', $results[0]->status);
            $this->assertSame('daylight_saving_time', $results[0]->reason_code);
        }
        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame(0, SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->count());
    }

    public function test_recurring_safe_conflicts_distinguish_notice_collision_and_inactive_lesson(): void
    {
        $this->booking();
        $service = app(RecurringLessonService::class);
        $collision = $service->generate($this->plan(), $this->administrator->id, 1, 1);
        $this->assertSame('booking_conflict', $collision[0]->reason_code);
        AvailabilityRule::query()->update(['min_notice_hours' => 24]);
        $notice = $service->generate($this->plan(['start_date' => '2031-01-01']), $this->administrator->id, 1, 1);
        $this->assertSame('minimum_notice', $notice[0]->reason_code);
        $this->type->update(['active' => false]);
        $inactive = $service->generate($this->plan(['preferred_time' => '12:00']), $this->administrator->id, 1, 1);
        $this->assertSame('lesson_or_student_unavailable', $inactive[0]->reason_code);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_incompatible_and_expired_purchases_never_fund_recurring_lessons(): void
    {
        app(StudentLedgerService::class)->createPackage($this->student, 'Different entitlement', 3, '80.00', '0.00', 'USD', null, 'different', entitlementCode: 'two_hour');
        $expired = $this->purchase();
        $expired->update(['expiration_date' => '2030-12-31']);
        $results = app(RecurringLessonService::class)->generate($this->plan(), $this->administrator->id, 1, 1);
        $this->assertSame('compatible_credit_unavailable', $results[0]->reason_code);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame(0, SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->count());
    }

    public function test_cross_owner_interest_key_and_changed_renewal_terms_are_rejected(): void
    {
        $interest = ['date_from' => '2031-01-08', 'date_to' => '2031-01-15', 'timezone' => 'UTC', 'session_type_id' => $this->type->id, 'idempotency_key' => 'owned-interest'];
        app(StudentSchedulingService::class)->waitlist($this->student, $interest);
        try {
            app(StudentSchedulingService::class)->waitlist(Student::factory()->verified()->create(), $interest);
            $this->fail('Another owner cannot replay this request.');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('booking_waitlists', 1);
        $package = $this->purchase();
        app(PackageRenewalService::class)->renew($package, ['renewal_date' => '2031-01-01', 'idempotency_key' => 'fixed-renewal'], $this->administrator->id);
        try {
            app(PackageRenewalService::class)->renew($package, ['renewal_date' => '2031-01-02', 'idempotency_key' => 'fixed-renewal'], $this->administrator->id);
            $this->fail('Changed renewal terms cannot replay this request.');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('package_renewals', 1);
        $this->assertDatabaseCount('student_packages', 2);
    }

    public function test_archive_filter_is_saved_and_receivables_default_is_truthfully_due(): void
    {
        $this->assertSame(['operational_status' => 'archived'], app(StaffSavedViewService::class)->validateFilters('students', ['operational_status' => 'archived']));
        $filters = app(ReceivablesReadModel::class)->filters(Request::create('/'));
        $this->assertSame('due', $filters['state']);
        $this->actingAs($this->administrator)->get(route('admin.receivables.index'))->assertOk()->assertSee('value="due" selected', false);
    }

    public function test_invalid_stored_policy_is_reported_read_only_and_uses_safe_new_booking_defaults(): void
    {
        Setting::set('booking_lifecycle_policy', ['cancellation_cutoff_hours' => -5, 'no_show' => 'unknown']);
        $configuration = app(DataQualityReadModel::class)->overview()['configuration'];
        $this->assertContains('Cancellation / no-show policy requires review. New bookings use the documented defaults until corrected.', $configuration);
        $this->assertSame('retain', app(BookingPolicyService::class)->current()['no_show']);
        $this->assertSame(['cancellation_cutoff_hours' => -5, 'no_show' => 'unknown'], Setting::get('booking_lifecycle_policy'));
    }

    public function test_staff_holiday_audit_has_staff_actor_and_missing_midnight_is_a_validation_error(): void
    {
        app(StudentSchedulingService::class)->holiday($this->student, ['date_from' => '2031-01-08', 'date_to' => '2031-01-08', 'timezone' => 'UTC', 'idempotency_key' => 'staff-holiday'], $this->administrator->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_unavailability_created', 'actor_type' => 'admin', 'administrator_id' => $this->administrator->id]);
        $this->expectException(ValidationException::class);
        app(StudentSchedulingService::class)->holiday($this->student, ['date_from' => '2011-12-30', 'date_to' => '2011-12-30', 'timezone' => 'Pacific/Apia', 'idempotency_key' => 'missing-midnight']);
    }

    public function test_receivables_queries_stay_bounded_with_more_purchases_and_forecasts(): void
    {
        $package = $this->purchase();
        app(InstallmentScheduleService::class)->create($package, [['expected_amount' => '80.00', 'due_date' => '2030-12-31']], 'first-schedule', null);
        Setting::get('business_timezone');
        DB::enableQueryLog();
        iterator_to_array(app(ReceivablesReadModel::class)->rows(['state' => 'all']));
        $singleCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($index = 0; $index < 19; $index++) {
            $other = $this->purchase();
            app(InstallmentScheduleService::class)->create($other, [['expected_amount' => '80.00', 'due_date' => '2030-12-31']], 'other-schedule-'.$index, null);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $rows = iterator_to_array(app(ReceivablesReadModel::class)->rows(['state' => 'all']));
        $manyCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(20, $rows);
        $this->assertSame($singleCount, $manyCount, 'Eager-loaded financial projections must not add queries per purchase.');
        $this->assertLessThanOrEqual(12, $manyCount);
    }

    public function test_dashboard_student_time_uses_booking_timezone_for_next_and_today_lessons(): void
    {
        $booking = $this->booking();
        DB::table('bookings')->where('id', $booking->id)->update(['customer_timezone' => 'America/New_York']);
        $this->travelTo(CarbonImmutable::parse('2031-01-08 00:00:00', 'UTC'));
        $this->actingAs($this->administrator)->get(route('admin.dashboard'))->assertOk()->assertSee('04:00 (America/New_York)')->assertSee('09:00');
    }

    public function test_reporting_separates_currencies_and_refund_dates_without_inventing_revenue(): void
    {
        $ledger = app(StudentLedgerService::class);
        $usd = $this->purchase();
        $payment = $ledger->recordPayment($usd, '50.00', 'report-usd', null);
        $eur = $ledger->createPackage($this->student, 'Euro purchase', 2, '60.00', '0.00', 'EUR', null, 'report-eur', entitlementCode: 'one_hour');
        $ledger->recordPayment($eur, '20.00', 'report-eur-payment', null);
        $this->travelTo(CarbonImmutable::parse('2031-01-02 00:00:00', 'UTC'));
        $ledger->refund($payment, '10.00', 'report-refund', null, reason: 'Reported on its actual date');
        $report = app(BusinessLifecycleReport::class);
        $filters = ['date_from' => '2031-01-02', 'date_to' => '2031-01-02'];
        $totals = $report->totals($filters)['currencies'];
        $this->assertSame('0.00', $totals['USD']['paid']);
        $this->assertSame('10.00', $totals['USD']['refunded']);
        $this->assertSame('-10.00', $totals['USD']['net']);
        $this->assertSame('40.00', $totals['USD']['outstanding']);
        $this->assertSame('40.00', $totals['EUR']['outstanding']);
        $this->assertSame(['EUR'], array_keys($report->totals($filters + ['currency' => 'EUR'])['currencies']));
        $this->assertSame(0, $report->totals($filters)['attending_students']);
    }

    public function test_new_xlsx_exports_keep_financial_filters_and_actual_payment_rows(): void
    {
        $package = $this->purchase();
        $ledger = app(StudentLedgerService::class);
        $ledger->recordPayment($package, '20.00', 'xlsx-payment', null, method: 'Cash');
        $other = Student::factory()->verified()->create();
        $ledger->createPackage($other, 'Excluded purchase', 2, '80.00', '0.00', 'USD', null, 'excluded-xlsx', entitlementCode: 'one_hour');
        $this->studentLogin();
        $this->actingAs($this->administrator, 'web');
        $routes = [
            ['student.statements.export', ['package' => $package->id]],
            ['admin.statements.export', ['package' => $package->id]],
            ['admin.receivables.export', ['student_id' => $this->student->id, 'currency' => 'USD', 'state' => 'partial']],
            ['admin.reports.lifecycle.export', ['student_id' => $this->student->id, 'currency' => 'USD', 'date_from' => '2031-01-01', 'date_to' => '2031-01-01']],
        ];
        foreach ($routes as [$route, $parameters]) {
            $response = $this->get(route($route, $parameters + ['format' => 'xlsx']))->assertOk();
            $file = $response->baseResponse->getFile()->getPathname();
            $workbook = IOFactory::load($file);
            try {
                $contents = json_encode($workbook->getActiveSheet()->toArray(), JSON_THROW_ON_ERROR);
                $this->assertStringContainsString('60.00', $contents);
                $this->assertStringNotContainsString('Excluded purchase', $contents);
                if (str_contains($route, 'statements')) {
                    $this->assertStringContainsString('Actual payment', $contents);
                    $this->assertStringContainsString('Cash', $contents);
                    $response->assertHeader('Cache-Control', 'no-store, private');
                }
            } finally {
                $workbook->disconnectWorksheets();
                unlink($file);
            }
        }
    }

    public function test_statements_preserve_recorded_method_when_payment_configuration_is_renamed(): void
    {
        $package = $this->purchase();
        $method = PaymentMethod::factory()->create(['name' => 'Cash at recording']);
        $payment = app(StudentLedgerService::class)->recordPayment($package, '20.00', 'snapshot-method', null, paymentMethodId: $method->id);
        $method->update(['name' => 'Renamed configuration']);
        $this->studentLogin();
        $this->get(route('student.statements.show', $package))->assertOk()->assertSee('Cash at recording')->assertDontSee('Renamed configuration');
        $this->get(route('student.receipts.show', [$package, $payment]))->assertOk()->assertSee('Cash at recording');
        $csv = $this->get(route('student.statements.export', $package))->assertOk()->streamedContent();
        $this->assertStringContainsString('Cash at recording', $csv);
        $this->assertStringNotContainsString('Renamed configuration', $csv);
    }

    public function test_archived_waitlist_interest_leaves_daily_queue_but_remains_in_history(): void
    {
        BookingWaitlist::factory()->create(['student_id' => $this->student->id]);
        app(StudentSchedulingService::class)->operationalStatus($this->student, 'archived', 'taking_break', $this->administrator->id);
        $this->actingAs($this->administrator)->get(route('admin.waitlist.index'))->assertOk()->assertViewHas('interests', fn ($interests): bool => $interests->total() === 0);
        $this->get(route('admin.waitlist.index', ['status' => 'all']))->assertOk()->assertViewHas('interests', fn ($interests): bool => $interests->total() === 1);
        $this->assertDatabaseCount('booking_waitlists', 1);
    }
}
