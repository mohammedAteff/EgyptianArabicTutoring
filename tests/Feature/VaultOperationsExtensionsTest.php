<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Models\StaffTask;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingPolicyDecision;
use App\Domains\Booking\Models\MeetingRoom;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Forms\Services\FormCompletionReadModel;
use App\Domains\Reporting\Services\BusinessActivityReadModel;
use App\Domains\Reporting\Services\BusinessLifecycleReport;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Models\StudentPortalLogin;
use App\Domains\Students\Services\DataQualityReadModel;
use App\Domains\Students\Services\StudentCompletenessService;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaultOperationsExtensionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_dashboard_distinguishes_drafts_completed_versions_and_missing_forms_without_answer_text(): void
    {
        $actor = Administrator::factory()->create();
        $form = app(FormBuilderService::class)->create(['title' => 'Learning Profile', 'slug' => 'long-form', 'trigger' => 'none', 'is_mandatory' => true], [['question_key' => 'goal', 'label' => 'Your goal', 'question_type' => 'short_text']], $actor);
        $form = app(FormBuilderService::class)->publish($form->id, $form->active_version_id, $form->lock_version);
        $student = Student::factory()->verified()->create();
        Booking::factory()->create(['student_id' => $student->id]);
        $submission = FormSubmission::query()->create(['student_id' => $student->id, 'form_version_id' => $form->published_version_id, 'status' => 'draft']);
        $submission->answers()->create(['form_question_id' => $form->publishedVersion->questions->sole()->id, 'value_text' => 'PRIVATE DRAFT ANSWER']);
        $metadata = app(FormCompletionReadModel::class)->forStudent($student)->sole();
        $this->assertSame('draft', $metadata['status']);
        $this->assertSame(1, $metadata['answered']);
        $this->assertNotNull($metadata['saved_at']);
        $this->actingAs($actor, 'web')->get(route('admin.forms.completion', ['filter' => 'long_draft']))->assertOk()->assertSee('Learning Profile')->assertSee('1 / 1 questions answered')->assertDontSee('PRIVATE DRAFT ANSWER');
        $checks = app(StudentCompletenessService::class)->assess($student);
        $this->assertContains('Long Form Submission', $checks['missing']);
        $submission->update(['status' => 'submitted', 'submitted_at' => now('UTC')]);
        $this->get(route('admin.forms.completion', ['filter' => 'long_completed']))->assertOk()->assertSee($student->name);
        $this->assertContains('Long Form Submission', app(StudentCompletenessService::class)->assess($student)['completed']);
    }

    public function test_inactive_packages_and_drafts_do_not_satisfy_profile_completeness(): void
    {
        $student = Student::factory()->verified()->create();
        StudentPackage::factory()->create(['student_id' => $student->id, 'status' => 'cancelled']);
        $result = app(StudentCompletenessService::class)->assess($student);
        $this->assertContains('Package Enrolled', $result['missing']);
        $this->assertContains('Intake Form Submission', $result['missing']);
        $this->assertSame(7, $result['total_count']);
    }

    public function test_daily_summary_counts_actual_payment_dates_and_audit_actions_with_separate_currencies(): void
    {
        $actor = Administrator::factory()->create(['name' => 'Summary staff']);
        PaymentRecord::factory()->create(['amount_paid' => '12.37', 'paid_at' => '2026-09-15 10:00:00', 'created_at' => '2026-09-16 10:00:00']);
        PaymentRecord::factory()->create(['amount_paid' => '15.01', 'currency' => 'EUR', 'paid_at' => '2026-09-15 11:00:00']);
        $this->travelTo(CarbonImmutable::parse('2026-09-15 12:00:00', 'UTC'));
        app(AuditLogService::class)->log('student_updated', Student::class, null, null, null, $actor->id);
        app(AuditLogService::class)->log('staff_task_updated', StaffTask::class, null, ['status' => 'open'], ['status' => 'completed'], $actor->id);
        $this->travelBack();
        $data = app(BusinessActivityReadModel::class)->day('2026-09-15');
        $this->assertSame(['USD' => '12.37', 'EUR' => '15.01'], $data['payments']);
        $this->assertSame(2, $data['paymentsCount']);
        $this->assertSame(1, $data['staffSummaries'][$actor->id]['students_modified']);
        $this->assertSame(1, $data['staffSummaries'][$actor->id]['tasks_completed']);
        $this->actingAs($actor, 'web')->get(route('admin.reports.activity', ['date' => '2026-09-15']))->assertOk()->assertSee('Summary staff')->assertSee('USD 12.37');
        $this->artisan('tutor:daily-summary', ['--date' => '2026-09-15'])->expectsOutputToContain('Payments: USD 12.37')->assertSuccessful();
        $this->artisan('tutor:daily-summary', ['--date' => 'not-a-date'])->assertFailed();
        $this->artisan('tutor:daily-summary', ['--date' => '2026-02-31'])->assertFailed();
    }

    public function test_retention_and_late_cancellation_figures_use_observed_history(): void
    {
        $student = Student::factory()->verified()->create(['created_at' => '2026-01-01 00:00:00']);
        Booking::factory()->create(['student_id' => $student->id, 'status' => 'completed', 'start_at_utc' => '2026-08-15 12:00:00']);
        Booking::factory()->create(['student_id' => $student->id, 'status' => 'completed', 'start_at_utc' => '2026-09-15 12:00:00']);
        $cancelled = Booking::factory()->create(['student_id' => $student->id, 'status' => 'cancelled', 'start_at_utc' => '2026-09-16 12:00:00']);
        BookingPolicyDecision::factory()->create(['booking_id' => $cancelled->id, 'action' => 'cancellation', 'outcome' => 'retain', 'actor_type' => 'customer', 'policy_snapshot' => ['cancellation_cutoff_hours' => 24], 'created_at' => '2026-09-16 11:00:00']);
        $totals = app(BusinessLifecycleReport::class)->totals(['date_from' => '2026-09-01', 'date_to' => '2026-09-30']);
        $this->assertSame(1, $totals['cancellations']['late_cancellations']);
        $this->assertSame(1, $totals['retention']['returning_students']);
        $this->assertSame(1, $totals['retention']['active_students']);
        $this->assertSame(0, $totals['retention']['inactive_90_days']);
    }

    public function test_data_quality_checks_identify_future_room_and_suspension_issues_without_changes(): void
    {
        $student = Student::factory()->verified()->create(['suspended_at' => now('UTC')]);
        $booking = Booking::factory()->create(['student_id' => $student->id, 'meeting_room_id' => null]);
        $before = $booking->fresh()->getAttributes();
        $issues = app(DataQualityReadModel::class)->businessIssues();
        $this->assertSame(1, $issues['Upcoming sessions missing a meeting room']['count']);
        $this->assertSame(1, $issues['Suspended students with upcoming sessions']['count']);
        $this->assertSame($before, $booking->fresh()->getAttributes());
    }

    public function test_staff_activity_hides_owner_security_actions_from_ordinary_administrators(): void
    {
        $owner = Administrator::factory()->create(['role' => 'super_admin']);
        app(AuditLogService::class)->log('administrator_recovery_codes_rotated', Administrator::class, $owner->id, null, null, $owner->id);
        $this->actingAs(Administrator::factory()->create(['role' => 'admin']), 'web')->get(route('admin.reports.activity'))->assertOk()->assertDontSee('administrator recovery codes rotated');
        $this->actingAs($owner, 'web')->get(route('admin.reports.activity'))->assertOk()->assertSee('administrator recovery codes rotated');
    }

    public function test_successful_authentication_records_only_date_and_preferred_timezone(): void
    {
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'Asia/Tokyo']);
        $this->post(route('student.login.submit'), ['date_of_birth' => $student->date_of_birth->toDateString(), 'name' => $student->name, 'email' => $student->email])->assertRedirect(route('student.dashboard'));
        $login = StudentPortalLogin::query()->sole();
        $this->assertSame($student->id, $login->student_id);
        $this->assertSame('Asia/Tokyo', $login->timezone);
        $this->assertNotNull($login->authenticated_at);
        $this->assertArrayNotHasKey('ip_address', $login->getAttributes());
    }

    public function test_joinable_lesson_takes_priority_over_an_outstanding_form(): void
    {
        $student = Student::factory()->verified()->create();
        $room = MeetingRoom::factory()->create();
        $booking = Booking::factory()->create(['student_id' => $student->id, 'start_at_utc' => now('UTC')->addMinutes(5), 'end_at_utc' => now('UTC')->addMinutes(65), 'meeting_room_id' => $room->id, 'meeting_url_snapshot' => 'https://example.test/join']);
        $actor = Administrator::factory()->create();
        $form = app(FormBuilderService::class)->create(['title' => 'Required Questionnaire', 'slug' => 'required-questionnaire', 'trigger' => 'none', 'is_mandatory' => true], [['question_key' => 'goal', 'label' => 'Goal', 'question_type' => 'short_text']], $actor);
        app(FormBuilderService::class)->publish($form->id, $form->active_version_id, $form->lock_version);
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()])
            ->get(route('student.dashboard'))->assertOk()->assertViewHas('nextAction', fn (array $action): bool => $action['button'] === 'Join Lesson' && $action['url'] === 'https://example.test/join');
    }

    public function test_expiry_prompt_requires_compatible_available_credits(): void
    {
        $student = Student::factory()->verified()->create();
        $session = SessionType::query()->create(['title' => 'Pair lesson', 'slug' => 'pair-expiry', 'duration_minutes' => 60, 'price' => 40, 'active' => true, 'funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::where('code', 'one_hour')->value('id'), 'required_entitlement_units' => 2]);
        $ledger = app(StudentLedgerService::class);
        $ledger->createPackage($student, 'Expiring single credit', 1, '40', '0', 'USD', now('UTC')->addDays(7)->toDateString(), 'expiring-credit', entitlementCode: 'one_hour');
        $ledger->createPackage($student, 'Usable pair credits', 2, '40', '0', 'USD', null, 'pair-credits', entitlementCode: 'one_hour');
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()]);
        $this->get(route('student.dashboard'))->assertOk()->assertViewHas('nextAction', fn (array $action): bool => $action['label'] === 'Book Your Next Lesson');
        $session->update(['required_entitlement_units' => 1]);
        $this->get(route('student.dashboard'))->assertOk()->assertViewHas('nextAction', fn (array $action): bool => $action['label'] === 'Schedule Your Remaining Credits');
    }
}
