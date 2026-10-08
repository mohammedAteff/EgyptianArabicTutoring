<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\AssignmentSubmission;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonProgress;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\CourseStudioService;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsAssignmentService;
use App\Domains\Lms\Services\LmsLearningDefinition;
use App\Domains\Lms\Services\LmsProgressService;
use App\Domains\Lms\Services\LmsQuizService;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\LearningPlan;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\InstallmentScheduleService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LmsLearningEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(array $methods = ['manual']): array
    {
        $student = Student::factory()->verified()->create();
        $course = Course::factory()->published()->create();
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id,
            'learning_rules' => ['required' => true, 'methods' => $methods, 'video_threshold' => 95, 'prerequisite_key' => null, 'drip_mode' => 'immediate', 'drip_days' => null, 'drip_at' => null]]);
        app(LmsAccessOperations::class)->grant(AdministratorFactory::new()->create(), $student, $course, [], 'evidence-'.Str::uuid());
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'rich_text', 'status' => 'ready', 'payload' => ['html' => '<p>Learn</p>']]);

        return [$student, $course->fresh(), $lesson];
    }

    private function signIn(Student $student): static
    {
        return $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String(), 'student_display_timezone' => 'Africa/Cairo']);
    }

    private function capture(string $name, string $url, bool $asAdmin = false): void
    {
        $directory = getenv('LMS_EVIDENCE_UI_REVIEW_DIR');
        if (is_string($directory) && is_dir($directory)) {
            if ($asAdmin) {
                $this->actingAs(AdministratorFactory::new()->create(), 'web');
            }
            file_put_contents($directory.'/'.$name.'.html', $this->get($url)->assertOk()->getContent());
        }
    }

    public function test_manual_completion_is_idempotent_and_changes_canonical_course_progress(): void
    {
        [$student,$course,$lesson] = $this->fixture();
        $this->signIn($student)->post(route('student.evidence.manual', [$course, $lesson]), ['student_id' => 999, 'percent' => 100])->assertRedirect();
        $this->post(route('student.evidence.manual', [$course, $lesson]))->assertRedirect();
        $this->assertDatabaseCount('lms_lesson_progress', 1);
        $this->assertNotNull(LessonProgress::query()->firstOrFail()->completed_at);
        $summary = app(LmsProgressService::class)->summary($student, collect([$lesson]));
        $this->assertSame(100, $summary['percent']);
        $this->assertSame('Completed', $summary['status']);
        $this->capture('stage6-hub', route('student.learning.index'));
        $this->capture('stage6-course', route('student.learning.courses.show', $course));
    }

    public function test_every_quiz_type_uses_server_scoring_and_manual_review_and_retains_snapshot(): void
    {
        [$student,$course,$lesson] = $this->fixture(['quiz_pass']);
        $definition = app(LmsLearningDefinition::class)->assessment('quiz', ['title' => 'العربية English', 'passing_score' => 80, 'attempt_limit' => 2, 'review_policy' => 'after_grading',
            'questions' => [
                ['type' => 'choice', 'prompt' => 'Choose', 'points' => 1, 'options' => ['A', 'B'], 'answer' => 1],
                ['type' => 'multiple', 'prompt' => 'Choose several', 'points' => 1, 'options' => ['A', 'B', 'C'], 'answer' => [0, 2]],
                ['type' => 'boolean', 'prompt' => 'True?', 'points' => 1, 'answer' => true],
                ['type' => 'blank', 'prompt' => 'Say hello', 'points' => 1, 'accepted' => ['أهلاً', 'Hello']],
                ['type' => 'matching', 'prompt' => 'Match', 'points' => 1, 'options' => ['one', 'two'], 'answer' => ['واحد', 'اثنان']],
                ['type' => 'written', 'prompt' => 'Write', 'points' => 1],
            ]]);
        $block = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'quiz', 'status' => 'ready', 'payload' => $definition]);
        $quiz = app(LmsQuizService::class);
        $attempt = $quiz->begin($student, $course, $lesson, $block, (string) Str::uuid());
        $this->assertArrayNotHasKey('correct', $quiz->project($attempt)['questions'][0]);
        $this->signIn($student)->get(route('student.lms.lessons.show', [$course, $lesson]))->assertOk()->assertDontSee('accepted');
        $this->capture('stage6-quiz', route('student.evidence.show', [$course, $lesson, $block]));
        $answers = [1, [2, 0], true, '  HELLO  ', ['واحد', 'اثنان'], 'A written answer'];
        $row = $quiz->submit($student, $course, $lesson, $block, $attempt->id, $answers);
        $this->assertSame('pending_review', $row->status);
        $this->assertNull($row->score);
        $this->assertNull(LessonProgress::query()->firstOrFail()->completed_at);
        $this->capture('stage6-reviews-pending', route('admin.lms.reviews.index', $course), true);
        $row = $quiz->review(AdministratorFactory::new()->create(), $row->id, $row->lock_version, [5 => 1], 'Good');
        $this->assertSame(100.0, $row->score);
        $this->assertTrue($row->passed);
        $this->assertNotNull(LessonProgress::query()->firstOrFail()->completed_at);
        $this->assertSame(1, $quiz->project($row)['questions'][0]['correct']);
        $block->forceFill(['payload' => array_replace($definition, ['title' => 'Edited'])])->save();
        $this->assertSame('العربية English', $row->fresh()->definition['title']);
        $this->assertSame('Not Started', app(LmsProgressService::class)->lessonState($student, $lesson->fresh())['status']);
        $this->assertDatabaseCount('lms_quiz_attempts', 1);
    }

    private function quiz(Lesson $lesson, string $policy = 'after_grading', int $limit = 2): LessonBlock
    {
        $definition = app(LmsLearningDefinition::class)->assessment('quiz', ['title' => 'Arabic quiz', 'passing_score' => 80, 'attempt_limit' => $limit, 'review_policy' => $policy,
            'questions' => [['type' => 'choice', 'prompt' => 'Choose the greeting', 'points' => 1, 'options' => ['HELLO_SECRET_CORRECT', 'Bye'], 'answer' => 0]]]);

        return LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'quiz', 'status' => 'ready', 'payload' => $definition]);
    }

    private function assignment(Lesson $lesson, array $types = ['text']): LessonBlock
    {
        return LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'assignment', 'status' => 'ready', 'payload' => ['title' => 'Practice response', 'instructions' => 'Write or share a short response.', 'types' => $types]]);
    }

    public function test_quiz_fail_retry_limit_idempotency_and_authoritative_score(): void
    {
        [$student,$course,$lesson] = $this->fixture(['quiz_pass']);
        $block = $this->quiz($lesson);
        $quizzes = app(LmsQuizService::class);
        $first = $quizzes->begin($student, $course, $lesson, $block, (string) Str::uuid());
        $this->signIn($student)->post(route('student.evidence.quiz.submit', [$course, $lesson, $block, $first]), ['answers' => [1], 'score' => 100, 'passed' => true, 'student_id' => 999, 'number' => 99])->assertRedirect();
        $this->assertSame(0.0, $first->fresh()->score);
        $this->assertFalse($first->fresh()->passed);
        $this->post(route('student.evidence.quiz.submit', [$course, $lesson, $block, $first]), ['answers' => [1]])->assertRedirect();
        $this->postJson(route('student.evidence.quiz.submit', [$course, $lesson, $block, $first]), ['answers' => [0]])->assertStatus(409);
        $second = $quizzes->begin($student, $course, $lesson, $block, (string) Str::uuid());
        $this->assertSame(2, $second->number);
        $quizzes->submit($student, $course, $lesson, $block, $second->id, [0]);
        $this->assertNotNull(LessonProgress::query()->firstOrFail()->completed_at);
        $this->postJson(route('student.evidence.quiz.begin', [$course, $lesson, $block]), ['request_key' => (string) Str::uuid()])->assertStatus(422);
        $this->assertDatabaseCount('lms_quiz_attempts', 2);
    }

    public function test_quiz_completion_can_accept_a_graded_fail_and_never_policy_keeps_key_private(): void
    {
        [$student,$course,$lesson] = $this->fixture(['quiz_complete']);
        $block = $this->quiz($lesson, 'never');
        $quizzes = app(LmsQuizService::class);
        $row = $quizzes->begin($student, $course, $lesson, $block, (string) Str::uuid());
        $row = $quizzes->submit($student, $course, $lesson, $block, $row->id, [1]);
        $this->assertFalse($row->passed);
        $this->assertNotNull(LessonProgress::query()->firstOrFail()->completed_at);
        $this->assertArrayNotHasKey('correct', $quizzes->project($row)['questions'][0]);
        $this->signIn($student)->get(route('student.evidence.show', [$course, $lesson, $block]))->assertOk()->assertDontSee('HELLO_SECRET_CORRECT');
    }

    public function test_arabic_fill_blank_preserves_letters_diacritics_and_multiple_accepted_answers(): void
    {
        [$student,$course,$lesson] = $this->fixture(['quiz_pass']);
        $block = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'quiz', 'status' => 'ready', 'payload' => ['title' => 'تحية', 'passing_score' => 100, 'attempt_limit' => 3, 'review_policy' => 'never', 'questions' => [['type' => 'blank', 'prompt' => 'اكتب التحية', 'points' => 1, 'accepted' => ['أهلاً', 'Hello there']]]]]);
        $service = app(LmsQuizService::class);
        $one = $service->begin($student, $course, $lesson, $block, (string) Str::uuid());
        $this->assertFalse($service->submit($student, $course, $lesson, $block, $one->id, ['اهلا'])->passed);
        $two = $service->begin($student, $course, $lesson, $block, (string) Str::uuid());
        $this->assertTrue($service->submit($student, $course, $lesson, $block, $two->id, ['  HELLO   THERE  '])->passed);
        $three = $service->begin($student, $course, $lesson, $block, (string) Str::uuid());
        $this->assertTrue($service->submit($student, $course, $lesson, $block, $three->id, ['أهلاً'])->passed);
    }

    public function test_all_mixed_completion_requirements_must_be_met_and_assignment_history_is_retained(): void
    {
        [$student,$course,$lesson] = $this->fixture(['manual', 'quiz_pass', 'assignment_approve']);
        $quiz = $this->quiz($lesson);
        $assignment = $this->assignment($lesson);
        $progress = app(LmsProgressService::class);
        $progress->manual($student, $course, $lesson);
        $quizzes = app(LmsQuizService::class);
        $attempt = $quizzes->begin($student, $course, $lesson, $quiz, (string) Str::uuid());
        $quizzes->submit($student, $course, $lesson, $quiz, $attempt->id, [0]);
        $assignments = app(LmsAssignmentService::class);
        $key = (string) Str::uuid();
        $first = $assignments->submit($student, $course, $lesson, $assignment, ['kind' => 'text', 'body' => 'First response', 'request_key' => $key], null);
        $this->assertSame($first->id, $assignments->submit($student, $course, $lesson, $assignment, ['kind' => 'text', 'body' => 'First response', 'request_key' => $key], null)->id);
        $this->assertNull(LessonProgress::query()->firstOrFail()->completed_at);
        $actor = AdministratorFactory::new()->create();
        $first = $assignments->review($actor, $first->id, 1, 'under_review', null);
        $first = $assignments->review($actor, $first->id, 2, 'needs_revision', 'Add an example.');
        $second = $assignments->submit($student, $course, $lesson, $assignment, ['kind' => 'text', 'body' => 'Revised response', 'request_key' => (string) Str::uuid()], null);
        $this->assertSame(2, $second->number);
        $this->assertSame('First response', $first->fresh()->body);
        $this->assertSame('needs_revision', $first->fresh()->status);
        $assignments->review($actor, $second->id, 1, 'approved', 'Accepted');
        $this->assertNotNull(LessonProgress::query()->firstOrFail()->completed_at);
        $this->assertDatabaseCount('lms_lesson_progress', 1);
        $this->assertDatabaseCount('lms_assignment_submissions', 2);
        $this->signIn($student)->get(route('student.evidence.show', [$course, $lesson, $assignment]))->assertOk()->assertSee('Revised response')->assertSee('Add an example.');
        $this->capture('stage6-assignment', route('student.evidence.show', [$course, $lesson, $assignment]));
        $this->actingAs($actor, 'web')->get(route('admin.lms.reviews.index', $course))->assertOk();
        $this->capture('stage6-reviews', route('admin.lms.reviews.index', $course));
    }

    public static function submissionTypes(): array
    {
        return ['text' => ['text'], 'file' => ['file'], 'audio' => ['audio'], 'video' => ['video'], 'link' => ['external_link']];
    }

    #[DataProvider('submissionTypes')]
    public function test_supported_submission_types_are_private_and_submission_completion_is_canonical(string $kind): void
    {
        Storage::fake('local');
        [$student,$course,$lesson] = $this->fixture(['assignment_submit']);
        $block = $this->assignment($lesson, [$kind]);
        $file = match ($kind) {
            'file' => UploadedFile::fake()->createWithContent('response.pdf', "%PDF-1.4\nPrivate response"),
            'audio' => UploadedFile::fake()->createWithContent('response.wav', 'RIFF'.pack('V', 40).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 4)."\0\0\0\0"),
            'video' => UploadedFile::fake()->createWithContent('response.mp4', hex2bin('000000186674797069736f6d0000020069736f6d69736f32').str_repeat("\0", 100)),
            default => null,
        };
        $data = ['kind' => $kind, 'request_key' => (string) Str::uuid(), 'body' => 'Private text', 'url' => 'https://example.test/response', 'student_id' => 999, 'approved' => true];
        if ($file) {
            $data['file'] = $file;
        }
        $this->signIn($student);
        $this->capture('stage6-submission-'.$kind, route('student.evidence.show', [$course, $lesson, $block]));
        $this->signIn($student)->post(route('student.evidence.assignment.submit', [$course, $lesson, $block]), $data)->assertRedirect();
        $row = AssignmentSubmission::query()->firstOrFail();
        $this->assertSame($student->id, $row->student_id);
        $this->assertSame('submitted', $row->status);
        $this->assertNotNull(LessonProgress::query()->firstOrFail()->completed_at);
        if ($file) {
            Storage::disk('local')->assertExists($row->path);
            $this->get(route('student.evidence.file', $row))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->signIn(Student::factory()->verified()->create())->get(route('student.evidence.file', $row))->assertNotFound();
        }
    }

    public function test_unsafe_file_extensions_mime_sizes_links_and_client_paths_are_rejected(): void
    {
        Storage::fake('local');
        [$student,$course,$lesson] = $this->fixture(['assignment_submit']);
        $block = $this->assignment($lesson, ['file', 'external_link']);
        $this->signIn($student);
        foreach ([UploadedFile::fake()->createWithContent('exploit.php', '%PDF-1.4 harmless'),
            UploadedFile::fake()->createWithContent('response.pdf', '<?php executable'),
            UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf')] as $file) {
            $this->postJson(route('student.evidence.assignment.submit', [$course, $lesson, $block]), ['kind' => 'file', 'request_key' => (string) Str::uuid(), 'file' => $file])->assertStatus(422);
        }
        $this->postJson(route('student.evidence.assignment.submit', [$course, $lesson, $block]), ['kind' => 'external_link', 'request_key' => (string) Str::uuid(), 'url' => 'https://user:password@example.test/'])->assertStatus(422);
        $this->postJson(route('student.evidence.assignment.submit', [$course, $lesson, $block]), ['kind' => 'file', 'request_key' => (string) Str::uuid(), 'path' => '../other.pdf'])->assertStatus(422);
        $this->assertDatabaseCount('lms_assignment_submissions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('lms-submissions'));
    }

    public function test_student_a_cannot_submit_review_or_read_student_b_assessments(): void
    {
        [$a,$course,$lesson] = $this->fixture(['quiz_pass']);
        $b = Student::factory()->verified()->create();
        app(LmsAccessOperations::class)->grant(AdministratorFactory::new()->create(), $b, $course, [], 'other-'.Str::uuid());
        $block = $this->quiz($lesson);
        $attempt = app(LmsQuizService::class)->begin($b, $course, $lesson, $block, (string) Str::uuid());
        $this->signIn($a)->postJson(route('student.evidence.quiz.submit', [$course, $lesson, $block, $attempt]), ['answers' => [0], 'student_id' => $b->id])->assertNotFound();
        $this->get(route('student.evidence.show', [$course, $lesson, $block]))->assertOk()->assertDontSee('Attempt 1');
        $this->postJson(route('admin.lms.reviews.quiz', $attempt), ['version' => 1, 'marks' => [0 => 1]])->assertUnauthorized();
        $this->assertSame('started', $attempt->fresh()->status);
        $assistant = AdministratorFactory::new()->create(['role' => 'assistant']);
        $this->actingAs($assistant, 'web')->get(route('admin.lms.reviews.index', $course))->assertForbidden();
    }

    public function test_prerequisite_blocks_direct_urls_content_and_completion_until_current_prerequisite_is_complete(): void
    {
        [$student,$course,$first] = $this->fixture();
        $next = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $first->section_id, 'learning_rules' => array_replace($first->learning_rules, ['prerequisite_key' => 'lesson:'.$first->id])]);
        $block = $this->assignment($next);
        $this->signIn($student)->get(route('student.learning.courses.show', $course))->assertOk()->assertSee('Complete the prerequisite lesson first.');
        $this->get(route('student.learning.lessons.show', [$course, $next]))->assertNotFound()->assertSee('Complete the prerequisite lesson first.');
        $this->getJson(route('student.lms.lessons.show', [$course, $next]))->assertNotFound();
        $this->postJson(route('student.evidence.manual', [$course, $next]))->assertNotFound();
        $this->get(route('student.evidence.show', [$course, $next, $block]))->assertNotFound();
        app(LmsProgressService::class)->manual($student, $course, $first);
        $this->get(route('student.learning.lessons.show', [$course, $next]))->assertOk();
        $first->blocks()->first()->forceFill(['payload' => ['html' => '<p>New required content</p>']])->save();
        $this->get(route('student.learning.lessons.show', [$course, $next]))->assertNotFound();
        $this->assertDatabaseCount('lms_lesson_progress', 2);
    }

    public function test_authoring_rejects_cycles_and_preserves_live_rules_until_publish(): void
    {
        [$student,$course,$first] = $this->fixture();
        $second = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $first->section_id]);
        $actor = AdministratorFactory::new()->create();
        $studio = app(CourseStudioService::class);
        $course = $studio->write($actor, $course, 'learning', $first->learning_rules + ['key' => 'lesson:'.$first->id], $course->lock_version);
        $course = $studio->write($actor, $course, 'learning', array_replace($first->learning_rules, ['key' => 'lesson:'.$second->id, 'prerequisite_key' => 'lesson:'.$first->id]), $course->lock_version);
        $this->assertNull($second->fresh()->learning_rules);
        $this->actingAs($actor, 'web')->postJson(route('admin.lms.courses.update', $course), array_replace($first->learning_rules, ['operation' => 'learning', 'version' => $course->lock_version, 'key' => 'lesson:'.$first->id, 'prerequisite_key' => 'lesson:'.$second->id]))->assertStatus(422);
    }

    public function test_relative_drip_boundary_is_elapsed_days_and_expired_entitlement_still_denies(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-29T12:00:00+00:00'));
        [$student,$course,$lesson] = $this->fixture();
        $lesson->forceFill(['learning_rules' => array_replace($lesson->learning_rules, ['drip_mode' => 'relative', 'drip_days' => 1])])->save();
        $this->signIn($student)->get(route('student.learning.lessons.show', [$course, $lesson]))->assertNotFound();
        $this->travel(86399)->seconds();
        $this->signIn($student)->get(route('student.learning.lessons.show', [$course, $lesson]))->assertNotFound();
        $this->travel(1)->seconds();
        $this->signIn($student)->get(route('student.learning.lessons.show', [$course, $lesson]))->assertOk();
        AccessGrant::query()->where('student_id', $student->id)->update(['expires_at' => now('UTC'), 'access_mode' => 'fixed']);
        $this->get(route('student.learning.lessons.show', [$course, $lesson]))->assertNotFound();
    }

    public function test_fixed_drip_uses_explicit_timezone_and_exact_unlock_and_client_override_is_ignored(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08T12:00:00+00:00'));
        [$student,$course,$lesson] = $this->fixture();
        $lesson->forceFill(['learning_rules' => app(LmsLearningDefinition::class)->rules(array_replace($lesson->learning_rules, ['drip_mode' => 'fixed', 'drip_at' => '2026-10-08T15:01:00+03:00']))])->save();
        $this->signIn($student)->postJson(route('student.evidence.manual', [$course, $lesson]), ['early_override' => true])->assertNotFound();
        $this->travel(59)->seconds();
        $this->get(route('student.learning.lessons.show', [$course, $lesson]))->assertNotFound();
        $this->travel(1)->seconds();
        $this->get(route('student.learning.lessons.show', [$course, $lesson]))->assertOk();
        $this->assertSame('2026-10-08T12:01:00+00:00', $lesson->fresh()->learning_rules['drip_at']);
    }

    public function test_optional_lessons_do_not_block_course_completion_and_scoped_access_cannot_fake_completion(): void
    {
        [$student,$course,$lesson] = $this->fixture();
        $other = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $lesson->section_id, 'title' => 'Hidden required lesson', 'learning_rules' => array_replace($lesson->learning_rules, ['required' => false])]);
        app(LmsProgressService::class)->manual($student, $course, $lesson);
        $this->signIn($student)->get(route('student.learning.courses.show', $course))->assertViewHas('progress', fn ($p) => $p['percent'] === 100 && $p['status'] === 'Completed');
        $other->forceFill(['learning_rules' => array_replace($lesson->learning_rules, ['required' => true])])->save();
        AccessGrant::query()->where('student_id', $student->id)->update(['status' => 'revoked', 'revoked_at' => now('UTC')]);
        app(LmsAccessOperations::class)->grant(AdministratorFactory::new()->create(), $student, $lesson, [], 'scoped-'.Str::uuid());
        $this->get(route('student.learning.courses.show', $course))->assertViewHas('progress', fn ($p) => $p['percent'] === 50 && $p['status'] === 'In Progress')->assertDontSee('Hidden required lesson');
        $this->assertDatabaseCount('lms_lesson_progress', 1);
    }

    public function test_lms_completion_keeps_typed_funding_payments_installments_homework_and_teaching_progress_identical(): void
    {
        [$student,$course,$lesson] = $this->fixture(['manual', 'quiz_pass', 'assignment_submit']);
        $ledger = app(StudentLedgerService::class);
        $actor = AdministratorFactory::new()->create();
        $one = $ledger->createPackage($student, 'One hour', 3, '120.00', '0.00', 'USD', null, 'isolate-one', entitlementCode: 'one_hour');
        $ledger->createPackage($student, 'Two hour', 2, '160.00', '0.00', 'USD', null, 'isolate-two', entitlementCode: 'two_hour');
        $ledger->recordPayment($one, '40.00', 'isolate-payment', $actor->id);
        app(InstallmentScheduleService::class)->create($one, [['expected_amount' => '120.00', 'due_date' => now('UTC')->addMonth()->toDateString()]], 'isolate-schedule', $actor->id);
        $type = SessionType::query()->create(['title' => 'Typed lesson', 'slug' => 'typed-isolation', 'duration_minutes' => 60, 'price' => '40.00', 'currency' => 'USD', 'active' => true,
            'funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::query()->where('code', 'one_hour')->value('id'), 'required_entitlement_units' => 1]);
        DB::transaction(function () use ($student, $type, $ledger): void {
            Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            $booking = Booking::factory()->create(['student_id' => $student->id, 'session_type_id' => $type->id]);
            $ledger->consumeForBooking($booking, 'isolate-consume');
        });
        Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'status' => 'assigned']);
        LearningPlan::factory()->create(['student_id' => $student->id, 'student_visible' => true]);
        $tables = ['student_packages', 'student_package_entitlements', 'session_ledger_entries', 'payment_records', 'payment_refunds', 'package_installments', 'bookings', 'homeworks', 'learning_plans'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $progress = app(LmsProgressService::class);
        $quiz = $this->quiz($lesson);
        $assignment = $this->assignment($lesson);
        $progress->manual($student, $course, $lesson);
        $quizzes = app(LmsQuizService::class);
        $attempt = $quizzes->begin($student, $course, $lesson, $quiz, (string) Str::uuid());
        $quizzes->submit($student, $course, $lesson, $quiz, $attempt->id, [0]);
        app(LmsAssignmentService::class)->submit($student, $course, $lesson, $assignment, ['kind' => 'text', 'body' => 'Response', 'request_key' => (string) Str::uuid()], null);
        $this->assertNotNull(LessonProgress::query()->orderByDesc('id')->firstOrFail()->completed_at);
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table.' must be identical');
        }
        $this->signIn($student)->get(route('student.teaching.index'))->assertOk();
    }

    public function test_merge_preserves_learning_history_then_privacy_redacts_answers_and_submission_bodies(): void
    {
        [$primary,$course,$lesson] = $this->fixture(['manual', 'quiz_pass']);
        $secondary = Student::factory()->verified()->create();
        $actor = AdministratorFactory::new()->create();
        app(LmsAccessOperations::class)->grant($actor, $secondary, $course, [], 'secondary-'.Str::uuid());
        $quiz = $this->quiz($lesson, limit: 3);
        $quizzes = app(LmsQuizService::class);
        $one = $quizzes->begin($primary, $course, $lesson, $quiz, (string) Str::uuid());
        $quizzes->submit($primary, $course, $lesson, $quiz, $one->id, [1]);
        $two = $quizzes->begin($secondary, $course, $lesson, $quiz, (string) Str::uuid());
        $quizzes->submit($secondary, $course, $lesson, $quiz, $two->id, [0]);
        app(LmsProgressService::class)->manual($secondary, $course, $lesson);
        $assignment = $this->assignment($lesson);
        $submission = app(LmsAssignmentService::class)->submit($secondary, $course, $lesson, $assignment, ['kind' => 'text', 'body' => 'PRIVATE RESPONSE', 'request_key' => (string) Str::uuid()], null);
        app(StudentMergeService::class)->merge($primary->id, $secondary->id, $actor->id);
        $this->assertSame($primary->id, $two->fresh()->student_id);
        $this->assertSame(2, $two->fresh()->number);
        $this->assertSame(1, $two->fresh()->original_number);
        $this->assertSame($primary->id, $submission->fresh()->student_id);
        $this->assertSame('PRIVATE RESPONSE', $submission->fresh()->body);
        app(StudentPrivacyService::class)->anonymize($primary->id, $actor->id);
        $this->assertNull($two->fresh()->answers);
        $this->assertSame([], $two->fresh()->definition);
        $this->assertSame('erased', $two->fresh()->status);
        $this->assertNull($submission->fresh()->body);
        $this->assertSame([], $submission->fresh()->definition);
    }

    public static function invalidBusinessTimes(): array
    {
        return ['spring gap' => ['2026-03-08T02:30'], 'fall fold' => ['2026-11-01T01:30']];
    }

    public function test_answer_keys_stay_out_of_student_html_json_and_pending_manual_review(): void
    {
        [$student,$course,$lesson] = $this->fixture(['quiz_pass']);
        $definition = app(LmsLearningDefinition::class)->assessment('quiz', ['title' => 'Private keys', 'passing_score' => 80, 'attempt_limit' => 2, 'review_policy' => 'after_grading',
            'questions' => [['type' => 'blank', 'prompt' => 'Greeting', 'points' => 1, 'accepted' => ['SECRET_ACCEPTED_ANSWER']], ['type' => 'written', 'prompt' => 'Explain', 'points' => 1]]]);
        $block = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'quiz', 'status' => 'ready', 'payload' => $definition]);
        $service = app(LmsQuizService::class);
        $row = $service->begin($student, $course, $lesson, $block, (string) Str::uuid());
        $this->signIn($student)->get(route('student.evidence.show', [$course, $lesson, $block]))->assertOk()->assertDontSee('SECRET_ACCEPTED_ANSWER');
        $this->getJson(route('student.lms.lessons.show', [$course, $lesson]))->assertOk()->assertDontSee('SECRET_ACCEPTED_ANSWER');
        $service->submit($student, $course, $lesson, $block, $row->id, ['Wrong', 'Explanation']);
        $this->get(route('student.evidence.show', [$course, $lesson, $block]))->assertOk()->assertDontSee('SECRET_ACCEPTED_ANSWER')->assertSee('Explanation');
        $this->get(route('student.learning.lessons.show', [$course, $lesson]))->assertOk()->assertSee('Awaiting manual review');
        $row = $service->review(AdministratorFactory::new()->create(), $row->id, $row->fresh()->lock_version, [1 => 1], null);
        $this->get(route('student.evidence.show', [$course, $lesson, $block]))->assertOk()->assertSee('SECRET_ACCEPTED_ANSWER');
    }

    public function test_expired_and_revoked_access_preserves_completion_and_reenrollment_restores_it(): void
    {
        [$student,$course,$lesson] = $this->fixture();
        app(LmsProgressService::class)->manual($student, $course, $lesson);
        $row = LessonProgress::query()->firstOrFail();
        $snapshot = $row->getRawOriginal();
        AccessGrant::query()->update(['status' => 'revoked', 'revoked_at' => now('UTC')]);
        $this->signIn($student)->get(route('student.learning.lessons.show', [$course, $lesson]))->assertNotFound();
        $this->postJson(route('student.evidence.manual', [$course, $lesson]))->assertNotFound();
        $this->assertSame($snapshot, $row->fresh()->getRawOriginal());
        app(LmsAccessOperations::class)->grant(AdministratorFactory::new()->create(), $student, $course, [], 'reenrollment-'.Str::uuid());
        $this->get(route('student.learning.courses.show', $course))->assertOk()->assertSee('100%')->assertSee('Completed');
        $this->get(route('student.learning.index'))->assertOk()->assertSee('Completed');
        $this->assertDatabaseCount('lms_lesson_progress', 1);
    }

    public function test_private_submission_integrity_and_path_containment_are_enforced(): void
    {
        Storage::fake('local');
        [$student,$course,$lesson] = $this->fixture(['assignment_submit']);
        $block = $this->assignment($lesson, ['file']);
        $row = app(LmsAssignmentService::class)->submit($student, $course, $lesson, $block, ['kind' => 'file', 'request_key' => (string) Str::uuid()], UploadedFile::fake()->createWithContent('response.pdf', "%PDF-1.4\nprivate"));
        Storage::disk('local')->put($row->path, 'tampered content');
        $this->signIn($student)->get(route('student.evidence.file', $row))->assertNotFound();
        $row->forceFill(['path' => '../response.pdf'])->save();
        $this->get(route('student.evidence.file', $row))->assertNotFound();
    }

    public function test_assignment_terminal_decisions_and_stale_versions_cannot_rewrite_history(): void
    {
        [$student,$course,$lesson] = $this->fixture(['assignment_approve']);
        $block = $this->assignment($lesson);
        $service = app(LmsAssignmentService::class);
        $row = $service->submit($student, $course, $lesson, $block, ['kind' => 'text', 'body' => 'Response', 'request_key' => (string) Str::uuid()], null);
        $actor = AdministratorFactory::new()->create();
        $this->actingAs($actor, 'web')->post(route('admin.lms.reviews.assignment', $row), ['version' => 1, 'status' => 'under_review'])->assertRedirect();
        $this->postJson(route('admin.lms.reviews.assignment', $row), ['version' => 1, 'status' => 'approved'])->assertConflict();
        $this->postJson(route('admin.lms.reviews.assignment', $row), ['version' => 2, 'status' => 'needs_revision', 'feedback' => '  '])->assertUnprocessable();
        $this->post(route('admin.lms.reviews.assignment', $row), ['version' => 2, 'status' => 'approved', 'feedback' => 'Approved response'])->assertRedirect();
        $this->postJson(route('admin.lms.reviews.assignment', $row), ['version' => 3, 'status' => 'needs_revision', 'feedback' => 'Overwrite'])->assertConflict();
        $this->assertSame('approved', $row->fresh()->status);
        $this->assertSame('Approved response', $row->fresh()->feedback);
        $this->assertNotNull(LessonProgress::query()->firstOrFail()->completed_at);
    }

    public function test_native_author_quiz_fields_publish_and_duplicate_with_internal_prerequisites(): void
    {
        $actor = AdministratorFactory::new()->create();
        $studio = app(CourseStudioService::class);
        $course = $studio->create($actor, ['title' => 'محادثة Conversation', 'slug' => 'conversation', 'kind' => 'catalog']);
        $write = function (string $operation, array $data) use ($actor, $studio, &$course): void {
            $course = $studio->write($actor, $course, $operation, $data, $course->lock_version);
        };
        $write('add_section', ['title' => 'Core', 'status' => 'published']);
        $section = $studio->draft($actor, $course)['sections'][0]['key'];
        $write('add_lesson', ['parent_key' => $section, 'title' => 'First', 'slug' => 'first', 'status' => 'published']);
        $write('add_lesson', ['parent_key' => $section, 'title' => 'Second', 'slug' => 'second', 'status' => 'published']);
        [$first,$second] = $studio->draft($actor, $course)['sections'][0]['lessons'];
        $write('add_block', ['parent_key' => $first['key'], 'kind' => 'quiz', 'definition' => ['title' => 'Native quiz', 'passing_score' => '80', 'attempt_limit' => '2', 'review_policy' => 'after_grading',
            'questions' => [['type' => 'choice', 'prompt' => 'Choose مرحباً', 'points' => '1', 'options_text' => "Hello\nGoodbye", 'answer_text' => '1']]]]);
        $write('add_block', ['parent_key' => $second['key'], 'kind' => 'rich_text', 'html' => '<p>Next lesson</p>']);
        $rules = ['required' => true, 'methods' => ['quiz_pass'], 'video_threshold' => 95, 'drip_mode' => 'immediate'];
        $write('learning', $rules + ['key' => $first['key']]);
        $write('learning', array_replace($rules, ['key' => $second['key'], 'methods' => ['manual'], 'prerequisite_key' => $first['key']]));
        $this->actingAs($actor, 'web')->get(route('admin.lms.courses.edit', $course))->assertOk()->assertSee('Native quiz')->assertSee('Correct option number');
        $this->capture('stage6-studio', route('admin.lms.courses.edit', $course));
        $studio->publish($actor, $course, $course->lock_version);
        $lessons = Lesson::query()->where('course_id', $course->id)->orderBy('sort_order')->get();
        $this->assertSame('lesson:'.$lessons[0]->id, $lessons[1]->learning_rules['prerequisite_key']);
        $this->assertSame(0, $lessons[0]->blocks()->firstOrFail()->payload['questions'][0]['answer']);
        $copy = $studio->duplicate($actor, $course->fresh(), $course->fresh()->lock_version);
        $copied = $studio->draft($actor, $copy)['sections'][0]['lessons'];
        $this->assertSame($copied[0]['key'], $copied[1]['learning_rules']['prerequisite_key']);
        $this->assertNotSame($first['key'], $copied[0]['key']);
        $this->assertDatabaseCount('lms_quiz_attempts', 0);
    }

    #[DataProvider('invalidBusinessTimes')]
    public function test_author_fixed_schedule_rejects_missing_or_ambiguous_business_wall_time(string $local): void
    {
        [$student,$course,$lesson] = $this->fixture();
        Setting::set('business_timezone', 'America/New_York');
        $actor = AdministratorFactory::new()->create();
        $this->actingAs($actor, 'web')->postJson(route('admin.lms.courses.update', $course), array_replace($lesson->learning_rules, ['operation' => 'learning', 'key' => 'lesson:'.$lesson->id, 'version' => $course->lock_version, 'drip_mode' => 'fixed', 'drip_local' => $local]))
            ->assertStatus(422);
    }
}
