<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Enrollment;
use App\Domains\Lms\Models\LearningVisit;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonProgress;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Lms\Models\VideoProgress;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsAssignmentService;
use App\Domains\Lms\Services\LmsLearningDefinition;
use App\Domains\Lms\Services\LmsLearningNotifications;
use App\Domains\Lms\Services\LmsOperationsService;
use App\Domains\Lms\Services\LmsProgressService;
use App\Domains\Lms\Services\LmsQuizService;
use App\Domains\Lms\Services\LmsSettings;
use App\Domains\Lms\Services\LmsTutoringAssignments;
use App\Domains\Lms\Services\StudentLearningService;
use App\Domains\Lms\Services\StudentLearningStateService;
use App\Domains\Reporting\Services\ReportPeriod;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentNotification;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LearningAnalyticsOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00', 'UTC'));
        Http::preventStrayRequests();
    }

    private function fixture(?Student $student = null): array
    {
        $student ??= Student::factory()->verified()->create(['first_name' => 'Lucy']);
        $course = Course::factory()->published()->create(['title' => 'العربية · Arabic foundations']);
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = $this->lesson($course, $section);
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        app(LmsAccessOperations::class)->grant($actor, $student, $course, [], 'report-'.Str::uuid());

        return [$student, $course, $lesson, $actor];
    }

    private function lesson(Course $course, Section $section, array $changes = []): Lesson
    {
        return Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id,
            'learning_rules' => array_replace(['required' => true, 'methods' => ['manual'], 'video_threshold' => 95,
                'prerequisite_key' => null, 'drip_mode' => 'immediate', 'drip_days' => null, 'drip_at' => null], $changes)]);
    }

    private function signIn(Student $student): static
    {
        return $this->actingAs($student, 'student')->withHeader('User-Agent', 'Mozilla/5.0 Stage8 browser')->withSession([
            'student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String(), 'student_display_timezone' => 'Africa/Cairo']);
    }

    private function report(array $filters = [], ?CarbonImmutable $end = null): array
    {
        return app(AnalyticsService::class)->learningReport(now('UTC')->toImmutable()->subDays(30), $end ?? now('UTC')->toImmutable(), $filters);
    }

    private function retained(Student $student, Lesson $lesson, bool $complete, int $days = 0): LessonProgress
    {
        $row = new LessonProgress;
        $row->forceFill(['student_id' => $student->id, 'lesson_id' => $lesson->id,
            'requirement_hash' => app(LmsLearningDefinition::class)->requirementHash($lesson),
            'started_at' => now('UTC')->subDays($days), 'manual_completed_at' => $complete ? now('UTC')->subDays($days) : null,
            'completed_at' => $complete ? now('UTC')->subDays($days) : null])->save();

        return $row;
    }

    private function quiz(Lesson $lesson, bool $manual = false): LessonBlock
    {
        return LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'quiz', 'status' => 'ready', 'payload' => [
            'title' => 'Arabic quiz', 'passing_score' => 80, 'attempt_limit' => 10, 'review_policy' => 'after_grading',
            'questions' => [['type' => $manual ? 'written' : 'choice', 'prompt' => 'Practice Arabic', 'points' => 1,
                ...($manual ? [] : ['options' => ['Hello', 'Bye'], 'answer' => 0])]]]]);
    }

    private function assignment(Lesson $lesson): LessonBlock
    {
        return LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'assignment', 'status' => 'ready',
            'payload' => ['title' => 'Practice response', 'instructions' => 'Write Arabic', 'types' => ['text']]]);
    }

    private function capture(string $name, string $url): void
    {
        $directory = getenv('LMS_OPERATIONS_UI_REVIEW_DIR');
        if (is_string($directory) && is_dir($directory)) {
            file_put_contents($directory.'/'.$name.'.html', $this->get($url)->assertOk()->getContent());
        }
    }

    public function test_controlled_cohort_deduplicates_sources_and_matches_canonical_completion(): void
    {
        [$lucy,$course,$lesson,$actor] = $this->fixture();
        $sarah = Student::factory()->verified()->create();
        app(LmsAccessOperations::class)->grant($actor, $lucy, $lesson, [], 'extra-'.Str::uuid());
        app(LmsAccessOperations::class)->grant($actor, $sarah, $course, [], 'sarah-'.Str::uuid());
        $optional = $this->lesson($course, $lesson->section, ['required' => false]);
        $this->retained($lucy, $lesson, true);
        $this->retained($sarah, $lesson, false);
        app(StudentLearningStateService::class)->visit($lucy, $course, $lesson);
        $summary = $this->report()['summary'];
        $this->assertSame(2, $summary['enrolled']);
        $this->assertSame(2, $summary['active_enrollments']);
        $this->assertSame(2, $summary['started']);
        $this->assertSame(1, $summary['completed']);
        $this->assertSame(50.0, $summary['completion_rate']);
        $this->assertSame(1, $summary['lessons_completed']);
        $this->assertSame(1, $summary['active_learners']);
        $this->assertSame(50, $this->report()['courses']->sole()['average_progress']);
        $canonical = app(LmsProgressService::class)->summary($lucy, collect([$lesson, $optional]));
        $cohort = app(LmsProgressService::class)->cohortSummary(collect([$lesson, $optional]), LessonProgress::query()->where('student_id', $lucy->id)->get()->toBase());
        $this->assertSame($canonical['completion_key'], $cohort['completion_key']);
        $this->assertSame($canonical['percent'], $cohort['percent']);
        $this->assertSame($canonical['completion_key'], app(LmsProgressService::class)->cohortSummary(collect([$optional, $lesson]), LessonProgress::query()->where('student_id', $lucy->id)->get()->toBase())['completion_key']);
        $this->assertLessThanOrEqual($summary['enrolled'], $summary['completed']);
        Http::assertNothingSent();
    }

    public function test_current_requirement_generation_and_period_end_prevent_stale_completion(): void
    {
        [$student,$course,$lesson] = $this->fixture();
        $row = $this->retained($student, $lesson, true);
        $before = now('UTC')->toImmutable()->subSecond();
        $this->assertSame(0, $this->report([], $before)['summary']['completed']);
        $this->assertSame(1, $this->report()['summary']['completed']);
        $lesson->forceFill(['learning_rules' => array_replace($lesson->learning_rules, ['methods' => ['video']])])->save();
        $this->assertSame(0, $this->report()['summary']['completed']);
        $this->assertSame(0, $this->report()['summary']['started']);
        $this->assertNotNull($row->fresh()->completed_at);
    }

    public function test_retained_current_cohort_excludes_withdrawn_unverified_suspended_future_and_foreign_private_rows(): void
    {
        [$student,$course,$lesson,$actor] = $this->fixture();
        $this->retained($student, $lesson, true);
        foreach (['suspended', 'unverified', 'future', 'withdrawn'] as $kind) {
            $other = Student::factory()->verified()->create();
            app(LmsAccessOperations::class)->grant($actor, $other, $course, [], 'other-'.Str::uuid());
            $this->retained($other, $lesson, true);
            if ($kind === 'suspended') {
                $other->forceFill(['suspended_at' => now('UTC')])->save();
            }
            if ($kind === 'unverified') {
                $other->forceFill(['identity_status' => 'legacy_unverified'])->save();
            }
            if ($kind === 'future') {
                Enrollment::query()->where('student_id', $other->id)->update(['enrolled_at' => now('UTC')->addDay()]);
            }
            if ($kind === 'withdrawn') {
                Enrollment::query()->where('student_id', $other->id)->update(['status' => 'archived']);
            }
        }
        $this->assertSame(1, $this->report()['summary']['enrolled']);
        $this->assertSame(100.0, $this->report()['summary']['completion_rate']);
        $grant = AccessGrant::query()->where('student_id', $student->id)->sole();
        app(LmsAccessOperations::class)->change($actor, $grant, 'revoke', [], 1, 'revoke-'.Str::uuid());
        $this->assertSame(0, $this->report()['summary']['active_enrollments']);
        $this->assertSame(1, $this->report()['summary']['completed']);
        $course->forceFill(['status' => 'unpublished'])->save();
        $this->assertSame(0, $this->report()['summary']['enrolled']);
    }

    public function test_quiz_and_assignment_outcomes_are_subsets_of_period_submissions(): void
    {
        [$student,$course,$lesson,$actor] = $this->fixture();
        $quiz = $this->quiz($lesson);
        $service = app(LmsQuizService::class);
        foreach ([1, 0] as $answer) {
            $attempt = $service->begin($student, $course, $lesson, $quiz, (string) Str::uuid());
            $service->submit($student, $course, $lesson, $quiz, $attempt->id, [$answer]);
        }
        $pending = $this->quiz($lesson, true);
        $attempt = $service->begin($student, $course, $lesson, $pending, (string) Str::uuid());
        $service->submit($student, $course, $lesson, $pending, $attempt->id, ['Private answer']);
        $block = $this->assignment($lesson);
        $assignments = app(LmsAssignmentService::class);
        $submission = $assignments->submit($student, $course, $lesson, $block, ['kind' => 'text', 'body' => 'Private response', 'request_key' => (string) Str::uuid()], null);
        $assignments->review($actor, $submission->id, 1, 'needs_revision', 'Private feedback');
        $assignments->submit($student, $course, $lesson, $block, ['kind' => 'text', 'body' => 'Second private response', 'request_key' => (string) Str::uuid()], null);
        $summary = $this->report()['summary'];
        $this->assertSame(3, $summary['quiz_attempts']);
        $this->assertSame(1, $summary['quiz_passes']);
        $this->assertSame(50.0, $summary['quiz_average']);
        $this->assertSame(2, $summary['assignments_submitted']);
        $this->assertSame(1, $summary['assignments_reviewed']);
        $this->assertSame(1, $summary['assignments_awaiting_review']);
        $this->assertLessThanOrEqual($summary['quiz_attempts'], $summary['quiz_passes']);
        $this->assertLessThanOrEqual($summary['assignments_submitted'], $summary['assignments_reviewed']);
        $this->assertStringNotContainsString('Private response', json_encode($this->report(), JSON_THROW_ON_ERROR));
        $this->travel(31)->days();
        $this->assertSame(0, $this->report()['summary']['assignments_submitted']);
    }

    public function test_trusted_video_tiers_coverage_and_seconds_use_current_media_without_heartbeats(): void
    {
        [$lucy,$course,$lesson,$actor] = $this->fixture();
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'status' => 'ready', 'duration_seconds' => 100]);
        $block = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'video', 'status' => 'ready', 'video_asset_id' => $asset->id, 'payload' => null]);
        foreach ([20, 25, 50, 75, 95, 100] as $percent) {
            $student = $percent === 20 ? $lucy : Student::factory()->verified()->create();
            if ($student->id !== $lucy->id) {
                app(LmsAccessOperations::class)->grant($actor, $student, $course, [], 'video-'.Str::uuid());
            }
            $watch = new VideoProgress;
            $watch->forceFill(['student_id' => $student->id, 'block_id' => $block->id, 'media_hash' => app(LmsLearningDefinition::class)->mediaHash($block),
                'duration_milliseconds' => 100000, 'watched_ranges' => [[0, $percent * 1000]], 'position_milliseconds' => $percent * 1000, 'playing' => false, 'sequence' => 0])->save();
        }
        $video = $this->report()['videos']->sole();
        $this->assertSame(6, $video['started']);
        $this->assertSame(5, $video['25']);
        $this->assertSame(4, $video['50']);
        $this->assertSame(3, $video['75']);
        $this->assertSame(2, $video['completed']);
        $this->assertSame(60.8, $video['average_percent']);
        $this->assertSame(61, $video['average_seconds']);
        $this->assertLessThanOrEqual($video['started'], $video['completed']);
        $this->assertArrayNotHasKey('learning_time', $this->report()['summary']);
        $asset->forceFill(['duration_seconds' => 101])->save();
        $this->assertSame(0, $this->report()['videos']->sole()['started']);
        $asset->forceFill(['status' => 'processing'])->save();
        $this->assertCount(0, $this->report()['videos']);
        Http::assertNothingSent();
    }

    public function test_business_timezone_and_existing_report_period_boundaries_apply_to_learning(): void
    {
        Setting::set('business_timezone', 'Africa/Cairo');
        $period = app(ReportPeriod::class);
        [$start,$end] = $period->bounds(['range' => 'custom', 'start_date' => '2026-10-08', 'end_date' => '2026-10-08']);
        $this->assertSame('2026-10-07 21:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-08 20:59:59', $end->format('Y-m-d H:i:s'));
        [$student,$course,$lesson] = $this->fixture();
        $this->retained($student, $lesson, true);
        $this->assertSame(1, app(AnalyticsService::class)->learningReport($start, $end)['summary']['completed']);
    }

    public function test_five_semantic_event_kinds_have_one_backend_owner_and_retry_safe_ids(): void
    {
        [$student,$course,$manual] = $this->fixture();
        $quizLesson = $this->lesson($course, $manual->section, ['methods' => ['quiz_pass']]);
        $quiz = $this->quiz($quizLesson);
        $assignmentLesson = $this->lesson($course, $manual->section, ['methods' => ['assignment_submit']]);
        $block = $this->assignment($assignmentLesson);
        $this->signIn($student);
        $this->post(route('student.evidence.manual', [$course, $manual]))->assertRedirect();
        $this->post(route('student.evidence.manual', [$course, $manual]))->assertRedirect();
        $this->post(route('student.evidence.quiz.begin', [$course, $quizLesson, $quiz]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $attempt = QuizAttempt::query()->sole();
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('student.evidence.quiz.submit', [$course, $quizLesson, $quiz, $attempt]), ['answers' => [0]])->assertRedirect();
        }
        $body = ['kind' => 'text', 'body' => 'SECRET_PRIVATE_ANSWER', 'request_key' => (string) Str::uuid()];
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('student.evidence.assignment.submit', [$course, $assignmentLesson, $block]), $body)->assertRedirect();
        }
        $events = AnalyticsEvent::query()->where('event_name', 'like', 'lms_%')->get();
        $this->assertCount(7, $events);
        $this->assertSame(7, $events->pluck('event_uuid')->unique()->count());
        foreach (['lms_course_started' => 1, 'lms_lesson_completed' => 3, 'lms_course_completed' => 1, 'lms_quiz_submitted' => 1, 'lms_assignment_submitted' => 1] as $name => $count) {
            $this->assertSame($count, $events->where('event_name', $name)->count(), $name);
        }
        foreach ($events as $event) {
            $this->assertSame('/student/learn', $event->page);
            $this->assertNull($event->referrer);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $event->metadata['learner_key']);
            $this->assertArrayNotHasKey('student_id', $event->metadata);
            $this->assertLessThan(650, strlen(json_encode($event->metadata)));
        }
        $this->assertStringNotContainsString('SECRET_PRIVATE_ANSWER', $events->toJson());
        $this->assertStringNotContainsString($student->email, $events->toJson());
        $this->assertStringNotContainsString('127.0.0.1', $events->toJson());
        $this->postJson(route('analytics.track'), ['event_name' => 'lms_course_completed', 'event_uuid' => (string) Str::uuid(), 'metadata' => ['student_id' => $student->id]])->assertForbidden();
        $this->assertNull(app(AnalyticsService::class)->track('lms_course_completed', ['course_id' => $course->id]));
        $this->assertSame(7, AnalyticsEvent::query()->where('event_name', 'like', 'lms_%')->count());
    }

    public static function excludedTraffic(): array
    {
        return ['staff' => ['staff'], 'bot' => ['bot'], 'synthetic' => ['synthetic'], 'internal' => ['internal']];
    }

    #[DataProvider('excludedTraffic')]
    public function test_excluded_traffic_does_not_emit_semantic_learning_events(string $kind): void
    {
        [$student,$course,$lesson,$actor] = $this->fixture();
        $this->signIn($student);
        if ($kind === 'staff') {
            $this->actingAs($actor, 'web');
        }
        if ($kind === 'bot') {
            $this->withHeader('User-Agent', 'Googlebot');
        }
        if ($kind === 'synthetic') {
            $this->withHeader('X-Analytics-Synthetic', '1');
        }
        if ($kind === 'internal') {
            Setting::set('analytics.internal_hashes', [hash_hmac('sha256', '127.0.0.1', config('app.key'))]);
        }
        $this->post(route('student.evidence.manual', [$course, $lesson]))->assertRedirect();
        $this->assertSame(0, AnalyticsEvent::query()->where('event_name', 'like', 'lms_%')->count());
        $this->assertNotNull(LessonProgress::query()->sole()->completed_at);
    }

    public function test_inactivity_stall_and_expiry_flags_resolve_from_current_facts_and_access_union(): void
    {
        [$student,$course,$lesson,$actor] = $this->fixture();
        $this->retained($student, $lesson, false, 20);
        $grant = AccessGrant::query()->sole();
        app(LmsAccessOperations::class)->change($actor, $grant, 'set_expiration', ['expires_at' => now('UTC')->addDays(2)->toIso8601String()], 1, 'expiry-'.Str::uuid());
        $attention = app(LmsOperationsService::class)->attention();
        $this->assertSame(1, $attention->total());
        $labels = collect($attention->items()[0]['reasons'])->pluck('label');
        $this->assertCount(3, $labels);
        $this->actingAs($actor, 'web');
        $this->capture('stage8-attention', route('admin.lms.attention'));
        app(StudentLearningStateService::class)->visit($student, $course);
        $this->assertCount(2, $labels = collect(app(LmsOperationsService::class)->attention()->items()[0]['reasons']));
        app(LmsAccessOperations::class)->grant($actor, $student, $course, [], 'permanent-'.Str::uuid());
        $this->assertCount(1, app(LmsOperationsService::class)->attention()->items()[0]['reasons']);
        app(LmsProgressService::class)->manual($student, $course, $lesson);
        $this->assertSame(0, app(LmsOperationsService::class)->attention()->total());
    }

    public function test_quiz_failure_rule_uses_current_definition_and_recent_submission_is_activity(): void
    {
        [$student,$course,$lesson] = $this->fixture();
        Enrollment::query()->update(['enrolled_at' => now('UTC')->subDays(20)]);
        $quiz = $this->quiz($lesson);
        $service = app(LmsQuizService::class);
        for ($i = 0; $i < 3; $i++) {
            $attempt = $service->begin($student, $course, $lesson, $quiz, (string) Str::uuid());
            $service->submit($student, $course, $lesson, $quiz, $attempt->id, [1]);
        }
        $reasons = app(LmsOperationsService::class)->attention()->items()[0]['reasons'];
        $this->assertCount(1, $reasons);
        $this->assertStringContainsString('quiz failures', array_values($reasons)[0]['label']);
        $attempt = $service->begin($student, $course, $lesson, $quiz, (string) Str::uuid());
        $service->submit($student, $course, $lesson, $quiz, $attempt->id, [0]);
        $this->assertSame(0, app(LmsOperationsService::class)->attention()->total());
        $quiz->forceFill(['payload' => array_replace($quiz->payload, ['title' => 'New definition'])])->save();
        $this->assertSame(0, app(LmsOperationsService::class)->attention()->total());
    }

    public function test_private_unopened_rule_resolves_on_visit_and_attention_deduplicates_student_courses(): void
    {
        [$student,$course,$lesson,$actor] = $this->fixture();
        Enrollment::query()->update(['enrolled_at' => now('UTC')->subDays(20)]);
        $private = app(LmsTutoringAssignments::class)->createPrivate($actor, $student, ['title' => 'Private follow-up', 'kind' => 'rich_text', 'html' => '<p>Practice</p>', 'share_now' => true, 'request_key' => (string) Str::uuid()]);
        $this->travel(8)->days();
        $attention = app(LmsOperationsService::class)->attention();
        $this->assertSame(1, $attention->total());
        $this->assertStringContainsString('Private follow-up not opened', json_encode($attention->items()[0]['reasons']));
        $privateLesson = Lesson::query()->where('course_id', $private->id)->firstOrFail();
        app(LmsProgressService::class)->start($student, $private, $privateLesson);
        $this->assertSame(0, LearningVisit::query()->where('course_id', $private->id)->count());
        $this->assertStringNotContainsString('Private follow-up not opened', json_encode(app(LmsOperationsService::class)->attention()->items()[0]['reasons']));
        app(StudentLearningStateService::class)->visit($student, $private);
        $this->assertStringNotContainsString('Private follow-up not opened', json_encode(app(LmsOperationsService::class)->attention()->items()[0]['reasons']));
    }

    public function test_visit_notifications_deduplicate_expiry_drip_and_revision_keep_read_state_and_ownership(): void
    {
        [$student,$course,$lesson,$actor] = $this->fixture();
        $grant = AccessGrant::query()->sole();
        app(LmsAccessOperations::class)->change($actor, $grant, 'set_expiration', ['expires_at' => now('UTC')->addDays(2)->toIso8601String()], 1, 'notice-'.Str::uuid());
        $lesson->forceFill(['learning_rules' => array_replace($lesson->learning_rules, ['drip_mode' => 'fixed', 'drip_at' => now('UTC')->subMinute()->toIso8601String()])])->save();
        $block = $this->assignment($lesson);
        $row = app(LmsAssignmentService::class)->submit($student, $course, $lesson, $block, ['kind' => 'text', 'body' => 'PERSONAL_SECRET', 'request_key' => (string) Str::uuid()], null);
        app(LmsAssignmentService::class)->review($actor, $row->id, 1, 'needs_revision', 'PRIVATE_FEEDBACK');
        $this->assertDatabaseCount('student_notifications', 0);
        $this->signIn($student)->get(route('student.notifications.index'))->assertOk()->assertSee('Learning access ending soon')->assertSee('Scheduled lesson available')->assertSee('Assignment needs revision')->assertDontSee('PERSONAL_SECRET')->assertDontSee('PRIVATE_FEEDBACK');
        $this->assertSame(3, StudentNotification::query()->where('type', 'learning')->count());
        $notification = StudentNotification::query()->where('title', 'Scheduled lesson available')->sole();
        $this->patch(route('student.notifications.update', $notification))->assertRedirect();
        $read = $notification->fresh()->read_at->toIso8601String();
        $this->get(route('student.notifications.index'))->assertOk();
        $this->assertSame(3, StudentNotification::query()->where('type', 'learning')->count());
        $this->assertSame($read, $notification->fresh()->read_at->toIso8601String());
        $other = Student::factory()->verified()->create();
        $this->assertSame([], app(LmsLearningNotifications::class)->facts($other));
        app(LmsAccessOperations::class)->change($actor, $grant->fresh(), 'revoke', [], 2, 'withdraw-'.Str::uuid());
        $this->assertSame([], app(LmsLearningNotifications::class)->facts($student));
    }

    public function test_notification_options_and_scheduled_locks_are_respected_without_background_delivery(): void
    {
        [$student,$course,$lesson,$actor] = $this->fixture();
        $lesson->forceFill(['learning_rules' => array_replace($lesson->learning_rules, ['drip_mode' => 'fixed', 'drip_at' => now('UTC')->addDay()->toIso8601String()])])->save();
        $this->assertSame([], app(LmsLearningNotifications::class)->facts($student));
        $this->travel(2)->days();
        $this->assertCount(1, app(LmsLearningNotifications::class)->facts($student));
        $this->assertDatabaseCount('student_notifications', 0);
        $super = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $options = app(LmsSettings::class);
        $options->save($super, array_replace($options->values(), ['expiry_notifications' => false, 'drip_notifications' => false]), 0);
        $this->assertSame([], app(LmsLearningNotifications::class)->facts($student));
    }

    public function test_operations_queues_and_security_are_role_scoped_no_store_and_use_existing_review_routes(): void
    {
        [$student,$course,$lesson,$actor] = $this->fixture();
        $quiz = $this->quiz($lesson, true);
        $attempt = app(LmsQuizService::class)->begin($student, $course, $lesson, $quiz, (string) Str::uuid());
        app(LmsQuizService::class)->submit($student, $course, $lesson, $quiz, $attempt->id, ['SECRET_ANSWER']);
        $block = $this->assignment($lesson);
        app(LmsAssignmentService::class)->submit($student, $course, $lesson, $block, ['kind' => 'text', 'body' => 'SECRET_BODY', 'request_key' => (string) Str::uuid()], null);
        VideoAsset::factory()->create(['course_id' => $course->id, 'status' => 'failed', 'label' => 'Processing needs attention']);
        $this->actingAs($actor, 'web')->get(route('admin.lms.operations'))->assertOk()->assertSee('Learning analytics')->assertHeader('Cache-Control', 'no-store, private')->assertSee('retained enrollments');
        $this->get(route('admin.lms.reviews'))->assertOk()->assertSee('Processing needs attention')->assertSee(route('admin.lms.reviews.index', $course), false)->assertDontSee('SECRET_ANSWER')->assertDontSee('SECRET_BODY');
        $this->get(route('admin.lms.attention'))->assertOk();
        $this->get(route('admin.lms.permissions'))->assertOk();
        $this->get(route('admin.lms.security'))->assertForbidden();
        $this->get(route('admin.lms.settings'))->assertForbidden();
        $this->getJson(route('admin.lms.operations', ['videos' => ['bad']]))->assertStatus(422);
        $this->capture('stage8-analytics', route('admin.lms.operations'));
        $this->capture('stage8-reviews', route('admin.lms.reviews'));
        $super = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $this->actingAs($super, 'web')->get(route('admin.lms.settings'))->assertOk()->assertDontSee('signing_key');
        $this->get(route('admin.lms.security'))->assertOk()->assertDontSee('SECRET_BODY');
        $this->capture('stage8-settings', route('admin.lms.settings'));
        $this->capture('stage8-security', route('admin.lms.security'));
        $assistant = AdministratorFactory::new()->create(['role' => 'assistant']);
        $this->actingAs($assistant, 'web')->get(route('admin.lms.operations'))->assertForbidden();
        $this->get(route('admin.operations.index'))->assertOk()->assertDontSee(route('admin.lms.operations'), false);
        Http::assertNothingSent();
    }

    private function queryCount(callable $read): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $read();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_private_video_ready_fact_is_deduplicated_across_assignments_and_appears_only_on_visits(): void
    {
        [$student,$course,$lesson,$actor] = $this->fixture();
        $course->forceFill(['kind' => 'private', 'owner_student_id' => $student->id])->save();
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'status' => 'processing', 'duration_seconds' => 100]);
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'video', 'status' => 'ready', 'video_asset_id' => $asset->id, 'payload' => null]);
        foreach (['course' => $course->id, 'lesson' => $lesson->id] as $kind => $id) {
            app(LmsTutoringAssignments::class)->assign($actor, $student, ['kind' => $kind, 'target_id' => $id, 'request_key' => (string) Str::uuid()]);
        }
        $this->assertDatabaseCount('student_notifications', 0);
        $this->signIn($student)->get(route('student.notifications.index'))->assertOk()->assertSee('Private learning assigned')->assertDontSee('Private video ready');
        $this->assertSame(2, StudentNotification::query()->where('type', 'learning')->count());
        $asset->forceFill(['status' => 'ready'])->save();
        $this->assertSame(2, StudentNotification::query()->where('type', 'learning')->count());
        $this->get(route('student.notifications.index'))->assertOk()->assertSee('Private video ready')->assertDontSee($asset->provider_video_id);
        $this->get(route('student.notifications.index'))->assertOk();
        $this->assertSame(3, StudentNotification::query()->where('type', 'learning')->count());
        $this->assertSame(1, StudentNotification::query()->where('title', 'Private video ready')->count());
        Http::assertNothingSent();
    }

    public function test_media_queue_index_narrows_global_pending_status_without_a_course_filter(): void
    {
        [$student,$course,$lesson] = $this->fixture();
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'status' => 'failed', 'duration_seconds' => 100]);
        VideoAsset::factory()->count(100)->create(['course_id' => $course->id, 'provider_connection_id' => $asset->provider_connection_id, 'status' => 'ready', 'duration_seconds' => 100]);
        $plan = DB::select("EXPLAIN SELECT id,course_id,label,status FROM lms_video_assets WHERE provider = 'bunny' AND status IN ('uploading','processing','failed','delete_failed','create_unconfirmed') ORDER BY id LIMIT 20")[0];
        $this->assertStringContainsString('lms_video_operations', (string) $plan->possible_keys);
        $this->assertSame('lms_video_operations', $plan->key);
        $this->assertNotSame('ALL', $plan->type);
        $directory = getenv('LMS_OPERATIONS_UI_REVIEW_DIR');
        if (is_string($directory) && is_dir($directory)) {
            file_put_contents($directory.'/stage8-media-query-plan.json', json_encode($plan, JSON_THROW_ON_ERROR));
        }
    }

    public function test_hub_and_report_queries_are_batched_instead_of_per_course_or_student(): void
    {
        [$student,$course,$lesson,$actor] = $this->fixture();
        $smallHub = $this->queryCount(fn () => app(StudentLearningService::class)->hub($student));
        $smallReport = $this->queryCount(fn () => $this->report(['course_id' => $course->id]));
        for ($i = 0; $i < 19; $i++) {
            $this->fixture($student);
            $other = Student::factory()->verified()->create();
            app(LmsAccessOperations::class)->grant($actor, $other, $course, [], 'batch-'.Str::uuid());
        }
        $largeHub = $this->queryCount(fn () => app(StudentLearningService::class)->hub($student));
        $largeReport = $this->queryCount(fn () => $this->report(['course_id' => $course->id]));
        $this->assertLessThanOrEqual($smallHub + 3, $largeHub, 'Hub query count must remain bounded across 20 courses.');
        $this->assertLessThanOrEqual($smallReport + 2, $largeReport, 'Cohort query count must remain bounded across 20 Students.');
        $this->assertSame(20, $this->report(['course_id' => $course->id])['summary']['enrolled']);
        $this->assertCount(20, app(StudentLearningService::class)->hub($student)['cards']);
        $directory = getenv('LMS_OPERATIONS_UI_REVIEW_DIR');
        if (is_string($directory) && is_dir($directory)) {
            file_put_contents($directory.'/stage8-query-counts.json', json_encode(compact('smallHub', 'largeHub', 'smallReport', 'largeReport'), JSON_THROW_ON_ERROR));
        }
    }
}
