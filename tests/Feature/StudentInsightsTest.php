<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsLearningDefinition;
use App\Domains\Reporting\Services\ExportService;
use App\Domains\Reporting\Services\StudentInsightsContext;
use App\Domains\Reporting\Services\StudentInsightsReadModel;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\HomeworkSubmission;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentIntendedDestination;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentInsightsTest extends TestCase
{
    use RefreshDatabase;

    private function prepare(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-03 12:00:00', 'UTC'));
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();
    }

    private function signIn(Student $student): static
    {
        return $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()]);
    }

    private function context(?Student $student, array $filters = []): StudentInsightsContext
    {
        return StudentInsightsContext::fromRequest(request()->duplicate(array_replace(['range' => 'custom', 'start_date' => '2026-11-01', 'end_date' => '2026-11-03', 'timezone' => 'UTC'], $filters)), $student, $student === null);
    }

    private function booking(Student $student, string $start, string $end, string $status = 'completed'): Booking
    {
        return Booking::factory()->create(['student_id' => $student->id, 'status' => $status, 'start_at_utc' => $start, 'end_at_utc' => $end]);
    }

    private function lesson(Student $student, bool $required = true): array
    {
        $course = Course::factory()->published()->create();
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id, 'learning_rules' => ['required' => $required, 'methods' => ['manual'], 'drip_mode' => 'immediate']]);
        app(LmsAccessOperations::class)->grant(Administrator::factory()->create(), $student, $course, [], 'insights-'.Str::uuid());

        return [$course, $lesson];
    }

    private function evidence(Student $student, Lesson $lesson, string $kind, string $status, string $time): int
    {
        $block = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => $kind, 'status' => 'placeholder']);
        $row = ['student_id' => $student->id, 'block_id' => $block->id, 'definition_hash' => str_repeat('a', 64), 'request_key' => (string) Str::uuid(), 'number' => 1, 'original_number' => 1, 'definition' => json_encode(['secret-answer-key' => 'NEVER-EXPOSE']), 'status' => $status, 'feedback' => 'PRIVATE-GRADING', 'created_at' => $time, 'updated_at' => $time];
        $row += $kind === 'quiz' ? ['submitted_at' => $time, 'answers' => json_encode(['PRIVATE-ANSWER'])] : ['kind' => 'text', 'body' => 'PRIVATE-SUBMISSION'];

        return DB::table($kind === 'quiz' ? 'lms_quiz_attempts' : 'lms_assignment_submissions')->insertGetId($row);
    }

    public function test_student_and_admin_share_metrics_without_learning_writes_or_private_content(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $this->booking($student, '2026-11-01 23:30:00', '2026-11-02 00:30:00');
        $this->booking($student, '2026-11-02 10:00:00', '2026-11-02 11:00:00', 'no_show');
        $homework = Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'assigned_date' => '2026-11-01', 'student_response' => 'PRIVATE-HOMEWORK']);
        foreach (['2026-11-01 09:00:00', '2026-11-02 10:00:00'] as $time) {
            HomeworkSubmission::create(['homework_id' => $homework->id, 'body' => 'PRIVATE-HISTORY', 'submission_key' => (string) Str::uuid(), 'submitted_at' => $time]);
        }
        [$course, $lesson] = $this->lesson($student);
        $this->evidence($student, $lesson, 'assignment', 'approved', '2026-11-02 11:00:00');
        $this->evidence($student, $lesson, 'quiz', 'graded', '2026-11-03 11:00:00');
        $filters = ['range' => 'custom', 'start_date' => '2026-11-01', 'end_date' => '2026-11-03', 'timezone' => 'UTC'];

        $response = $this->signIn($student)->get(route('student.insights.index', $filters))->assertOk();

        $metrics = $response->viewData('metrics');
        $this->assertSame(1, $metrics['sessions_completed']['value']);
        $this->assertSame(3600, $metrics['session_duration_seconds']['value']);
        $this->assertSame(2, $metrics['homework_submission_records']['value']);
        $this->assertSame(1, $metrics['homework_items_submitted']['value']);
        $this->assertSame(3, $metrics['recorded_learning_days']['value']);
        $response->assertDontSee('PRIVATE-')->assertDontSee('NEVER-EXPOSE');
        $this->assertDatabaseCount('lms_lesson_progress', 0);
        $this->assertDatabaseCount('lms_learning_visits', 0);
        $this->actingAs(Administrator::factory()->create(), 'web')->get(route('admin.student-insights.index', $filters + ['student_id' => $student->id]))->assertOk()->assertViewHas('metrics', $metrics);
    }

    public function test_population_includes_zero_activity_suspended_and_unverified_but_excludes_merged_and_deleted(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        Student::factory()->create(['suspended_at' => now()]);
        Student::factory()->create();
        Student::factory()->create(['identity_status' => 'merged', 'merged_into_student_id' => $student->id]);
        Student::factory()->create()->delete();

        $response = $this->actingAs(Administrator::factory()->create(), 'web')->get(route('admin.student-insights.index'))->assertOk();

        $this->assertSame(3, $response->viewData('population'));
        $this->assertSame(3, $response->viewData('students')->total());
        $this->assertSame(0, $response->viewData('metrics')['students_with_recorded_activity']['value']);
    }

    public function test_first_completion_uses_earliest_generation_before_period_filter_and_current_canonical_progress(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        [$course, $lesson] = $this->lesson($student);
        $hash = app(LmsLearningDefinition::class)->requirementHash($lesson);
        foreach ([['old', '2026-10-25 12:00:00'], [$hash, '2026-11-02 12:00:00']] as [$generation, $completed]) {
            DB::table('lms_lesson_progress')->insert(['student_id' => $student->id, 'lesson_id' => $lesson->id, 'requirement_hash' => str_pad($generation, 64, 'a'), 'started_at' => '2026-10-01 00:00:00', 'completed_at' => $completed, 'created_at' => $completed, 'updated_at' => $completed]);
        }

        $data = app(StudentInsightsReadModel::class)->page($this->context($student), $student);

        $this->assertSame(0, $data['metrics']['lessons_first_completed']['value']);
        $this->assertSame(100, $data['current']['courses'][0]['percent']);
        $this->assertSame(1, app(StudentInsightsReadModel::class)->metrics($this->context($student, ['start_date' => '2026-10-01']))['lessons_first_completed']['value']);
    }

    public function test_zero_required_curriculum_is_not_applicable_and_current_state_ignores_period(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $this->lesson($student, false);
        Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'assigned_date' => '2026-10-01', 'status' => 'submitted']);

        $data = app(StudentInsightsReadModel::class)->page($this->context($student), $student);

        $this->assertNull($data['current']['courses'][0]['percent']);
        $this->assertSame(1, $data['current']['states']['homework_submitted']);
        $this->assertSame(0, $data['metrics']['homework_assigned']['value']);
    }

    public function test_homework_shared_visibility_and_booking_ownership_are_enforced(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $booking = $this->booking($other, '2026-11-01 10:00:00', '2026-11-01 11:00:00');
        foreach ([[true, null], [false, null], [true, $booking->id]] as [$visible, $bookingId]) {
            $homework = Homework::factory()->create(['student_id' => $student->id, 'student_visible' => $visible, 'booking_id' => $bookingId, 'assigned_date' => '2026-11-01']);
            HomeworkSubmission::create(['homework_id' => $homework->id, 'body' => 'private', 'submission_key' => (string) Str::uuid(), 'submitted_at' => '2026-11-01 12:00:00']);
        }

        $metrics = app(StudentInsightsReadModel::class)->metrics($this->context($student));

        $this->assertSame(1, $metrics['homework_assigned']['value']);
        $this->assertSame(1, $metrics['homework_submission_records']['value']);
    }

    public function test_old_definitions_and_repeat_submissions_survive_access_loss_but_started_and_erased_do_not_count(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        [$course, $lesson] = $this->lesson($student);
        $id = $this->evidence($student, $lesson, 'assignment', 'needs_revision', '2026-11-02 10:00:00');
        $repeat = (array) DB::table('lms_assignment_submissions')->find($id);
        unset($repeat['id']);
        $repeat['request_key'] = (string) Str::uuid();
        $repeat['definition_hash'] = str_repeat('b', 64);
        DB::table('lms_assignment_submissions')->insert($repeat);
        $this->evidence($student, $lesson, 'assignment', 'erased', '2026-11-02 10:00:00');
        $this->evidence($student, $lesson, 'quiz', 'started', '2026-11-02 10:00:00');
        $this->evidence($student, $lesson, 'quiz', 'erased', '2026-11-02 10:00:00');
        $this->evidence($student, $lesson, 'quiz', 'pending_review', '2026-11-02 10:00:00');
        DB::table('lms_access_grants')->where('student_id', $student->id)->update(['status' => 'revoked', 'revoked_at' => now()]);

        $data = app(StudentInsightsReadModel::class)->page($this->context($student), $student);

        $this->assertSame(2, $data['metrics']['lms_assignment_submissions']['value']);
        $this->assertSame(1, $data['metrics']['lms_assignment_activities_submitted']['value']);
        $this->assertSame(1, $data['metrics']['quiz_attempts_submitted']['value']);
        $this->assertSame([], $data['current']['courses']);
    }

    public function test_dst_half_open_bounds_and_full_cross_midnight_duration(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $this->booking($student, '2026-10-24 22:00:00', '2026-10-25 01:00:00');
        $this->booking($student, '2026-10-25 22:59:59', '2026-10-26 00:00:00');
        $this->booking($student, '2026-10-25 23:00:00', '2026-10-26 00:00:00');
        $context = $this->context($student, ['start_date' => '2026-10-25', 'end_date' => '2026-10-25', 'timezone' => 'Europe/Berlin']);

        $data = app(StudentInsightsReadModel::class)->page($context, $student);

        $this->assertSame('2026-10-24 22:00:00', $context->from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-25 23:00:00', $context->until->format('Y-m-d H:i:s'));
        $this->assertSame(2, $data['metrics']['sessions_completed']['value']);
        $this->assertSame(14401, $data['metrics']['session_duration_seconds']['value']);
        $this->assertSame(1, $data['metrics']['recorded_learning_days']['value']);
        $this->assertSame(2, $data['trends'][0]['records']);
    }

    public function test_future_events_are_excluded_and_homework_dates_are_not_shifted_or_active_days(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $this->booking($student, '2026-11-03 12:00:00', '2026-11-03 13:00:00');
        Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'assigned_date' => '2026-11-01']);

        $metrics = app(StudentInsightsReadModel::class)->metrics($this->context($student, ['end_date' => '2026-11-01', 'timezone' => 'America/Los_Angeles']));

        $this->assertSame(1, $metrics['homework_assigned']['value']);
        $this->assertSame(0, $metrics['recorded_learning_days']['value']);
        $this->assertSame(0, app(StudentInsightsReadModel::class)->metrics($this->context($student))['sessions_completed']['value']);
    }

    public static function invalidFilters(): array
    {
        return ['timezone' => [['timezone' => 'Not/AZone'], 'timezone'], 'reversed' => [['range' => 'custom', 'start_date' => '2026-11-03', 'end_date' => '2026-11-01'], 'end_date'], 'future' => [['range' => 'custom', 'start_date' => '2026-12-01', 'end_date' => '2026-12-02'], 'start_date'], 'excessive' => [['range' => 'custom', 'start_date' => '2020-01-01', 'end_date' => '2026-11-01'], 'end_date'], 'malformed' => [['range' => 'custom', 'start_date' => '2026-02-31', 'end_date' => '2026-11-01'], 'start_date'], 'missing end' => [['range' => 'custom', 'start_date' => '2026-11-01'], 'end_date'], 'category' => [['category' => 'video'], 'category'], 'month' => [['month' => '2025-01'], 'month']];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected(array $filters, string $key): void
    {
        $this->prepare();
        $this->signIn(Student::factory()->verified()->create())->getJson(route('student.insights.index', $filters))->assertUnprocessable()->assertJsonValidationErrors($key);
    }

    public function test_student_cannot_choose_another_identity_or_persist_report_timezone(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'Europe/Berlin']);
        $this->signIn($student)->getJson(route('student.insights.index', ['student_id' => Student::factory()->verified()->create()->id]))->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->get(route('student.insights.index', ['timezone' => 'Europe/Paris']))->assertOk()->assertSessionMissing('student_display_timezone');
        $this->assertSame('Europe/Berlin', $student->fresh()->preferred_timezone);
    }

    public function test_category_filters_all_projections_without_false_zeros(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $this->booking($student, '2026-11-01 12:00:00', '2026-11-01 13:00:00');

        $data = app(StudentInsightsReadModel::class)->page($this->context($student, ['category' => 'homework']), $student);

        $this->assertArrayNotHasKey('sessions_completed', $data['metrics']);
        $this->assertSame(0, array_sum(array_column($data['trends'], 'records')));
        $this->assertSame(0, array_sum(array_column($data['calendar']['days'], 'count')));
        $this->assertArrayNotHasKey('assignments_awaiting_review', $data['current']['states']);
    }

    public function test_exports_include_all_pages_preserve_numbers_and_exclude_private_fields_and_names(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create(['first_name' => '=HYPERLINK("bad")']);
        Homework::factory()->count(23)->create(['student_id' => $student->id, 'student_visible' => true, 'assigned_date' => '2026-11-01', 'instructions' => 'PRIVATE-EXCLUDED']);
        $this->actingAs(Administrator::factory()->create(), 'web');
        $filters = ['student_id' => $student->id, 'range' => 'month', 'timezone' => 'UTC'];

        $response = $this->get(route('admin.student-insights.export', $filters + ['dataset' => 'activity_records', 'format' => 'csv']));
        $csv = $response->streamedContent();

        $this->assertCount(24, array_filter(explode("\n", trim($csv))));
        $this->assertStringNotContainsString('PRIVATE-EXCLUDED', $csv);
        $this->assertStringNotContainsString('HYPERLINK', $csv);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $xlsx = $this->get(route('admin.student-insights.export', $filters + ['dataset' => 'individual_summary', 'format' => 'xlsx']))->assertOk();
        $path = $xlsx->baseResponse->getFile()->getPathname();
        $book = IOFactory::load($path);
        $sheet = $book->getActiveSheet();
        $this->assertSame('n', $sheet->getCell('D4')->getDataType());
        $this->assertSame(23, $sheet->getCell('D4')->getValue());
        $evidenceDirectory = getenv('INSIGHTS_EVIDENCE_DIR');
        if (is_string($evidenceDirectory) && is_dir($evidenceDirectory)) {
            copy($path, $evidenceDirectory.'/verified-synthetic-summary.xlsx');
        }
        $book->disconnectWorksheets();
        unlink($path);
    }

    public static function refusedRoles(): array
    {
        return ['assistant' => ['assistant'], 'content editor' => ['content_editor']];
    }

    #[DataProvider('refusedRoles')]
    public function test_unprivileged_staff_cannot_view_or_export(string $role): void
    {
        $this->prepare();
        $this->actingAs(Administrator::factory()->create(['role' => $role]), 'web');
        $this->get(route('admin.student-insights.index'))->assertForbidden();
        $this->get(route('admin.student-insights.export'))->assertForbidden();
    }

    public function test_login_return_only_retains_safe_insights_filters(): void
    {
        $this->prepare();
        $this->get(route('student.insights.index', ['category' => 'lms']))->assertRedirect(route('student.login'));
        $this->assertSame('/student/learning-insights?category=lms', app(StudentIntendedDestination::class)->safeRelativeUrl('/student/learning-insights?category=lms&student_id=99&body=secret'));
        $this->get(route('admin.student-insights.export'))->assertRedirect();
    }

    public function test_invalid_duration_is_unavailable_then_partial_without_losing_session_count(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $this->booking($student, '2026-11-01 12:00:00', '2026-11-01 11:00:00');
        $read = app(StudentInsightsReadModel::class);

        $invalid = $read->metrics($this->context($student));

        $this->assertSame(1, $invalid['sessions_completed']['value']);
        $this->assertNull($invalid['session_duration_seconds']['value']);
        $this->assertSame('unavailable', $invalid['session_duration_seconds']['state']);
        $this->booking($student, '2026-11-02 12:00:00', '2026-11-02 13:00:00');
        $partial = $read->metrics($this->context($student));
        $this->assertSame(3600, $partial['session_duration_seconds']['value']);
        $this->assertSame('partial', $partial['session_duration_seconds']['state']);
        $this->assertSame(1, $partial['session_duration_seconds']['excluded_invalid_durations']);
    }

    public function test_aggregate_active_students_are_distinct_and_comparison_uses_equal_local_dates(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $this->booking($student, '2026-11-01 12:00:00', '2026-11-01 13:00:00');
        $this->booking($student, '2026-11-02 12:00:00', '2026-11-02 13:00:00');
        $context = $this->context(null);

        $data = app(StudentInsightsReadModel::class)->page($context, null);

        $this->assertSame(1, $data['metrics']['students_with_recorded_activity']['value']);
        $this->assertSame(2, array_sum(array_column($data['trends'], 'students')));
        $this->assertSame('2026-10-29', $context->previous()->startDate);
        $this->assertSame('2026-10-31', $context->previous()->endDate);
        $this->assertSame('2026-11-03 12:00:00', $context->effectiveUntil()->format('Y-m-d H:i:s'));
        $this->assertCount(7, app(StudentInsightsReadModel::class)->trends($this->context(null, ['start_date' => '2026-05-01'])));
    }

    public function test_private_course_ownership_mismatches_cannot_contribute(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        [$course, $lesson] = $this->lesson($student);
        $this->evidence($student, $lesson, 'quiz', 'graded', '2026-11-02 12:00:00');
        DB::table('lms_courses')->where('id', $course->id)->update(['kind' => 'private', 'owner_student_id' => Student::factory()->verified()->create()->id]);

        $this->assertSame(0, app(StudentInsightsReadModel::class)->metrics($this->context($student))['quiz_attempts_submitted']['value']);
    }

    public function test_merge_moves_retained_activity_once_and_privacy_erasure_excludes_the_account(): void
    {
        $this->prepare();
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $this->booking($secondary, '2026-11-01 12:00:00', '2026-11-01 13:00:00');
        $admin = Administrator::factory()->create();
        app(StudentMergeService::class)->merge($primary->id, $secondary->id, $admin->id);

        $read = app(StudentInsightsReadModel::class);
        $this->assertSame(1, $read->population()->count());
        $this->assertSame(1, $read->metrics($this->context($primary))['sessions_completed']['value']);
        $this->assertSame(1, $read->metrics($this->context(null))['sessions_completed']['value']);
        app(StudentPrivacyService::class)->anonymize($primary->id, $admin->id);
        $this->assertSame(0, $read->population()->count());
        $this->assertSame(0, $read->metrics($this->context(null))['sessions_completed']['value']);
    }

    public static function unavailableStudents(): array
    {
        return ['suspended' => [['suspended_at' => '2026-11-01 00:00:00']], 'unverified' => [['identity_status' => 'legacy_unverified']], 'second factor' => [['two_factor_version' => 'missing-proof']]];
    }

    #[DataProvider('unavailableStudents')]
    public function test_student_status_and_second_factor_remain_required(array $state): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create($state);
        $this->signIn($student)->get(route('student.insights.index'))->assertRedirect(route('student.login'));
    }

    public function test_admin_preview_and_suspended_admin_cannot_use_student_or_report_endpoints(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $admin = Administrator::factory()->create();
        $this->actingAs($admin, 'web');
        $this->signIn($student)->withSession(['lms_preview' => ['actor_id' => $admin->id]])->get(route('student.insights.index'))->assertForbidden();
        $admin->update(['suspended_at' => now()]);
        $this->get(route('admin.student-insights.export'))->assertRedirect(route('admin.login'));
    }

    public function test_aggregate_pages_are_bounded_stable_and_export_contains_zero_activity_members(): void
    {
        $this->prepare();
        Student::factory()->count(23)->create();
        $this->actingAs(Administrator::factory()->create(), 'web');

        $page = $this->get(route('admin.student-insights.index'))->assertOk();

        $this->assertCount(20, $page->viewData('students'));
        $this->assertCount(3, $this->get(route('admin.student-insights.index', ['page' => 2]))->viewData('students'));
        $csv = $this->get(route('admin.student-insights.export', ['dataset' => 'aggregate_summary', 'format' => 'csv']))->streamedContent();
        $rows = array_map(str_getcsv(...), explode("\n", trim($csv)));
        $this->assertCount(23, array_filter($rows, fn (array $row): bool => $row[1] === 'recorded_learning_days' && $row[6] === 'period'));
        $this->assertCount(23, array_filter($rows, fn (array $row): bool => $row[1] === 'recorded_learning_days' && $row[6] === 'previous_period'));
    }

    public function test_aggregate_query_count_does_not_grow_with_each_student(): void
    {
        $this->prepare();
        Student::factory()->verified()->create();
        $read = app(StudentInsightsReadModel::class);
        $context = $this->context(null);
        DB::enableQueryLog();
        $read->page($context, null);
        $read->students($context);
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        Student::factory()->count(19)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $read->page($context, null);
        $read->students($context);
        $twenty = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($one, $twenty);
        $this->assertLessThan(20, $twenty);
    }

    public function test_spring_dst_day_has_23_hours_and_preferred_timezone_falls_back_safely(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'Europe/Berlin']);
        $context = $this->context($student, ['start_date' => '2026-03-29', 'end_date' => '2026-03-29', 'timezone' => 'Europe/Berlin']);

        $this->assertSame('2026-03-28 23:00:00', $context->from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-29 22:00:00', $context->until->format('Y-m-d H:i:s'));
        $this->signIn($student)->withSession(['student_display_timezone' => 'Invalid/Zone'])->get(route('student.insights.index'))->assertOk()->assertViewHas('context', fn ($value) => $value->timezone === 'Europe/Berlin');
    }

    public function test_partly_future_period_defaults_calendar_to_last_observed_date_and_marks_future_month(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $context = $this->context($student, ['end_date' => '2026-12-31']);

        $this->assertSame('2026-11', $context->month);
        $this->assertSame('incomplete', $context->metadata()['period_state']);
        $future = $this->context($student, ['end_date' => '2026-12-31', 'month' => '2026-12']);
        $calendar = app(StudentInsightsReadModel::class)->calendar($future);
        $this->assertSame(['future'], array_values(array_unique(array_column($calendar['days'], 'state'))));
    }

    public function test_switching_from_custom_dates_to_a_preset_uses_the_selected_preset(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();

        $response = $this->signIn($student)->get(route('student.insights.index', ['range' => '7d', 'start_date' => '2026-10-01', 'end_date' => '2026-10-15', 'timezone' => 'UTC']))->assertOk();

        $context = $response->viewData('context');
        $this->assertSame('7d', $context->range);
        $this->assertSame('2026-10-28', $context->startDate);
        $this->assertSame('2026-11-03', $context->endDate);
    }

    public function test_export_capacity_failure_keeps_context_and_offers_csv_recovery(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $this->actingAs(Administrator::factory()->create(), 'web');
        $this->mock(ExportService::class)->shouldReceive('export')->once()->andThrow(ValidationException::withMessages(['format' => 'Export CSV or narrow the dates.']));
        $filters = ['range' => '7d', 'timezone' => 'Europe/Berlin', 'category' => 'homework', 'student_id' => $student->id];

        $this->get(route('admin.student-insights.export', $filters + ['format' => 'xlsx']))->assertRedirect(route('admin.student-insights.index', $filters))->assertSessionHasErrors('format');
    }

    public static function languages(): array
    {
        return ['English' => ['en', 'Learning Insights'], 'French' => ['fr', 'Aperçu de mon apprentissage'], 'German' => ['de', 'Lernübersicht']];
    }

    #[DataProvider('languages')]
    public function test_insights_catalogues_have_complete_translations_and_matching_placeholders(string $locale, string $title): void
    {
        $english = Arr::dot(require lang_path('en/insights.php'));
        $translated = Arr::dot(require lang_path($locale.'/insights.php'));
        $this->assertSame(array_keys($english), array_keys($translated));

        foreach ($english as $key => $source) {
            $this->assertTrue(app('translator')->hasForLocale('insights.'.$key, $locale), $locale.': '.$key);
            $message = trans('insights.'.$key, [], $locale);
            $this->assertIsString($message);
            $this->assertNotSame('', trim($message));
            preg_match_all('/:[a-z_]+/i', $source, $expected);
            preg_match_all('/:[a-z_]+/i', $message, $actual);
            sort($expected[0]);
            sort($actual[0]);
            $this->assertSame($expected[0], $actual[0], $locale.': '.$key);
        }

        $this->assertSame($title, trans('insights.title', [], $locale));
    }

    #[DataProvider('languages')]
    public function test_custom_date_errors_are_fully_localized_in_json_and_on_the_page(string $locale, string $title): void
    {
        $this->prepare();
        $this->signIn(Student::factory()->verified()->create())->withSession(['public_locale' => $locale === 'fr' ? 'de' : 'fr']);
        $message = [
            'en' => 'Enter both dates for a custom period.',
            'fr' => 'Indiquez les deux dates pour une période personnalisée.',
            'de' => 'Geben Sie für einen eigenen Zeitraum beide Daten an.',
        ][$locale];
        $url = route('student.insights.index', ['lang' => $locale]);

        $this->from($url)->followingRedirects()->get(route('student.insights.index', ['range' => 'custom', 'lang' => $locale]))
            ->assertSeeText($title)->assertSeeText($message);

        $this->getJson(route('student.insights.index', ['range' => 'custom', 'lang' => $locale]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.start_date.0', $message)
            ->assertJsonPath('errors.end_date.0', $message)
            ->assertJsonPath('message', $message.' '.[
                'en' => '(and 1 more error)',
                'fr' => '(et 1 autre erreur)',
                'de' => '(und 1 weiterer Fehler)',
            ][$locale]);

    }

    #[DataProvider('languages')]
    public function test_incomplete_date_pairs_use_localized_instructions(string $locale, string $title): void
    {
        $this->prepare();
        $this->signIn(Student::factory()->verified()->create());
        $message = [
            'en' => 'Enter both the start date and the end date.',
            'fr' => 'Indiquez la date de début et la date de fin.',
            'de' => 'Geben Sie sowohl das Startdatum als auch das Enddatum an.',
        ][$locale];

        foreach (['start_date' => 'end_date', 'end_date' => 'start_date'] as $provided => $missing) {
            $this->getJson(route('student.insights.index', [$provided => '2026-11-01', 'lang' => $locale]))
                ->assertUnprocessable()->assertJsonPath('errors.'.$missing.'.0', $message);
        }
    }

    #[DataProvider('languages')]
    public function test_expired_insights_sessions_show_a_localized_sign_in_message(string $locale, string $title): void
    {
        $this->prepare();
        $message = [
            'en' => 'The session has expired or is invalid.',
            'fr' => 'Votre session a expiré ou n’est plus valide.',
            'de' => 'Die Sitzung ist abgelaufen oder nicht mehr gültig.',
        ][$locale];

        $this->followingRedirects()->get(route('student.insights.index', ['lang' => $locale]))
            ->assertSeeText($message)->assertSee('lang="'.$locale.'"', false);

        $this->assertGuest('student');
    }

    #[DataProvider('languages')]
    public function test_malformed_insights_filters_use_localized_field_names_and_account_errors(string $locale, string $title): void
    {
        $this->prepare();
        $this->signIn(Student::factory()->verified()->create());
        $fields = ['range', 'category', 'timezone', 'month', 'page', 'records_page', 'q', 'format', 'dataset'];
        $filters = array_fill_keys($fields, 'invalid');
        $filters['q'] = str_repeat('x', 101);
        $filters['student_id'] = 999;
        $labels = [
            'en' => ['Date range', 'Learning category', 'Time zone', 'Calendar month', 'page number', 'activity page number', 'student search', 'export format', 'export content'],
            'fr' => ['Période', 'Catégorie d’apprentissage', 'Fuseau horaire', 'Mois du calendrier', 'numéro de page', 'numéro de page des activités', 'recherche d’élève', 'format d’exportation', 'contenu de l’exportation'],
            'de' => ['Zeitraum', 'Lernkategorie', 'Zeitzone', 'Kalendermonat', 'Seitennummer', 'Seitennummer der Aktivitäten', 'Suche nach Lernenden', 'Exportformat', 'Exportinhalt'],
        ][$locale];

        $response = $this->getJson(route('student.insights.index', $filters + ['lang' => $locale]))->assertUnprocessable();

        foreach (array_combine($fields, $labels) as $field => $label) {
            $this->assertStringContainsString($label, $response->json('errors.'.$field.'.0'));
        }
        $response->assertJsonPath('errors.student_id.0', [
            'en' => 'You can only view learning insights for your own account.',
            'fr' => 'Vous pouvez uniquement consulter l’aperçu de votre propre apprentissage.',
            'de' => 'Sie können nur die Lernübersicht Ihres eigenen Kontos ansehen.',
        ][$locale]);
    }

    #[DataProvider('languages')]
    public function test_activity_calendar_counts_and_duration_states_use_localized_singular_and_plural_text(string $locale, string $title): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $this->booking($student, '2026-11-01 09:00:00', '2026-11-01 10:00:00');
        $this->booking($student, '2026-11-02 09:00:00', '2026-11-02 10:00:00');
        $this->booking($student, '2026-11-02 11:00:00', '2026-11-02 10:00:00');
        [$course] = $this->lesson($student);
        $course->forceFill(['title' => 'Authored course — merci, danke'])->save();
        $phrases = [
            'en' => ['1 qualifying record', '2 qualifying records', '1 invalid duration record excluded', 'Partial', 'Required lessons completed: 0 / 1'],
            'fr' => ['1 enregistrement retenu', '2 enregistrements retenus', '1 durée invalide exclue', 'Partiel', 'Leçons obligatoires terminées : 0 / 1'],
            'de' => ['1 berücksichtigter Eintrag', '2 berücksichtigte Einträge', '1 ungültige Zeitspanne ausgeschlossen', 'Teilweise', 'Abgeschlossene Pflichtlektionen: 0 / 1'],
        ][$locale];

        $response = $this->signIn($student)->get(route('student.insights.index', ['lang' => $locale, 'timezone' => 'UTC']))->assertOk();

        foreach ($phrases as $phrase) {
            $response->assertSeeText($phrase);
        }
        $response->assertSeeText('Authored course — merci, danke');
        $this->assertStringNotContainsString('{1}', $response->getContent());
        $this->assertStringNotContainsString('[0,*]', $response->getContent());
    }

    #[DataProvider('languages')]
    public function test_languages_and_mobile_primary_navigation_are_preserved(string $locale, string $title): void
    {
        $this->prepare();
        $response = $this->signIn(Student::factory()->verified()->create())->get(route('student.insights.index', ['lang' => $locale]))->assertOk()->assertSee($title);
        $html = $response->getContent();
        $this->assertStringNotContainsString('insights.', $html);
        preg_match('/<nav class="student-bottom-nav".*?<\/nav>/s', $html, $nav);
        $this->assertSame(4, substr_count($nav[0], '<a '));
        $this->assertStringNotContainsString('/student/learning-insights', $nav[0]);
        $this->assertStringContainsString('data-current-section', $nav[0]);
        $response->assertSee('student-more-learning', false);
    }
}
