<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Models\Booking;
use App\Domains\CMS\Models\Setting;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\AuthorizedDevice;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LearningAssignment;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LmsAsset;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Lms\Models\VideoProviderConnection;
use App\Domains\Lms\Services\CourseStudioService;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Lms\Services\LmsAssignmentService;
use App\Domains\Lms\Services\LmsLearningNotifications;
use App\Domains\Lms\Services\LmsProgressService;
use App\Domains\Lms\Services\LmsQuizService;
use App\Domains\Lms\Services\LmsTeachingTimeline;
use App\Domains\Lms\Services\LmsTutoringAssignments;
use App\Domains\Lms\Services\LmsTutoringReadModel;
use App\Domains\Lms\Services\LmsVideoProfiles;
use App\Domains\Lms\Services\VideoDeviceService;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\LearningPlan;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Models\TutorPreparation;
use App\Domains\Students\Services\InstallmentScheduleService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use Database\Factories\AdministratorFactory;
use Database\Factories\ResourceFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TutoringLearningIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Administrator $actor;

    private Student $lucy;

    private Student $sarah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->lucy = Student::factory()->verified()->create(['first_name' => 'Lucy', 'email' => 'stage7-lucy@example.test']);
        $this->sarah = Student::factory()->verified()->create(['first_name' => 'Sarah', 'email' => 'stage7-sarah@example.test']);
    }

    private function signIn(Student $student): static
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id,
            'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String(), 'student_display_timezone' => 'Africa/Cairo']);
        app('session')->save();

        return $this->withCookie(config('session.cookie'), app('session')->getId());
    }

    private function lesson(): Lesson
    {
        $course = Course::factory()->published()->create(['title' => 'Arabic conversations']);
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['title' => 'Greetings practice', 'course_id' => $course->id, 'section_id' => $section->id]);
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'rich_text', 'status' => 'ready', 'payload' => ['html' => '<p>أهلاً · Welcome</p>']]);

        return $lesson;
    }

    private function data(string $kind, int $id, array $extra = []): array
    {
        return array_replace(['kind' => $kind, 'target_id' => $id, 'request_key' => (string) Str::uuid(), 'access_mode' => 'permanent'], $extra);
    }

    private function assign(string $kind, int $id, array $extra = []): LearningAssignment|ResourceAssignment
    {
        return app(LmsTutoringAssignments::class)->assign($this->actor, $this->lucy, $this->data($kind, $id, $extra))['record'];
    }

    private function privateItem(string $kind = 'rich_text', array $extra = []): Course
    {
        return app(LmsTutoringAssignments::class)->createPrivate($this->actor, $this->lucy, array_replace([
            'title' => 'Lucy’s private practice', 'kind' => $kind, 'html' => '<p>Private practice العربية</p>',
            'instructions' => 'Practise before our next session.', 'request_key' => (string) Str::uuid(), 'share_now' => $kind !== 'video',
        ], $extra));
    }

    private function capture(string $name, string $url): void
    {
        $directory = getenv('LMS_TUTORING_UI_REVIEW_DIR');
        if (is_string($directory) && is_dir($directory)) {
            file_put_contents($directory.'/'.$name.'.html', $this->get($url)->assertOk()->getContent());
        }
    }

    public function test_profile_assignment_issues_one_canonical_grant_and_replay_retains_terms(): void
    {
        $lesson = $this->lesson();
        $url = route('admin.students.learning.store', $this->lucy);
        $data = $this->data('course', $lesson->course_id, ['access_mode' => 'relative', 'relative_days' => 2, 'instructions' => 'Practise greetings.']);
        $this->actingAs($this->actor, 'web')->post($url, $data + ['student_id' => $this->sarah->id])->assertRedirect(route('admin.students.show', $this->lucy));
        $this->post($url, $data)->assertSessionHas('success', fn ($text) => str_contains($text, 'already assigned'));
        $this->post($url, array_replace($data, ['request_key' => (string) Str::uuid(), 'relative_days' => 10, 'instructions' => 'Changed']))->assertSessionHas('success', fn ($text) => str_contains($text, 'already assigned'));
        $grant = AccessGrant::query()->firstOrFail();
        $this->assertSame($this->lucy->id, $grant->student_id);
        $this->assertSame(172800.0, $grant->starts_at->diffInSeconds($grant->expires_at));
        $this->assertSame('Practise greetings.', LearningAssignment::query()->firstOrFail()->instructions);
        $this->assertDatabaseCount('lms_access_grants', 1);
        $this->assertDatabaseCount('lms_learning_assignments', 1);
        $this->assertDatabaseCount('lms_enrollments', 1);
        $this->signIn($this->lucy)->get(route('student.learning.index'))->assertOk()->assertSee('Practise greetings.')->assertViewHas('forYou', fn ($items) => $items->first()['group'] === 'New');
        $this->signIn($this->sarah)->get(route('student.learning.index'))->assertDontSee('Practise greetings.');
    }

    public function test_fixed_start_expiry_extend_revoke_and_other_students_grant_are_guarded(): void
    {
        $lesson = $this->lesson();
        $row = $this->assign('course', $lesson->course_id, ['access_mode' => 'fixed', 'starts_at' => now('UTC')->addDay()->toIso8601String(), 'expires_at' => now('UTC')->addDays(2)->toIso8601String(), 'instructions' => 'Do not show before start']);
        $read = app(LmsTutoringReadModel::class);
        $this->assertSame('Scheduled', $read->assignments($this->lucy)->first()['group']);
        $this->assertNull($read->assignments($this->lucy)->first()['instructions']);
        $this->travel(1)->days();
        $this->assertTrue(app(LmsAccessService::class)->canAccess($this->lucy, $row));
        $grant = $row->accessGrant;
        $change = route('admin.students.learning.change', [$this->lucy, $grant]);
        $this->actingAs($this->actor, 'web')->patchJson(route('admin.students.learning.change', [$this->sarah, $grant]), ['action' => 'revoke', 'version' => 1, 'request_key' => (string) Str::uuid()])->assertNotFound();
        $this->patch($change, ['action' => 'extend', 'version' => 1, 'request_key' => (string) Str::uuid(), 'expires_at' => now('UTC')->addDays(3)->toIso8601String()])->assertRedirect();
        $this->patchJson($change, ['action' => 'revoke', 'version' => 1, 'request_key' => (string) Str::uuid()])->assertConflict();
        $this->patch($change, ['action' => 'revoke', 'version' => 2, 'request_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertFalse(app(LmsAccessService::class)->canAccess($this->lucy, $row));
        $this->assertSame('Expired', $read->assignments($this->lucy)->first()['group']);
        $this->assertSame('Access revoked', $read->assignments($this->lucy)->first()['reason']);
        $this->assertDatabaseCount('lms_access_grants', 1);
    }

    public function test_elapsed_expiry_is_exclusive_and_can_be_reassigned_with_a_new_request(): void
    {
        $lesson = $this->lesson();
        $one = $this->assign('lesson', $lesson->id, ['access_mode' => 'relative', 'relative_days' => 1]);
        $this->travel(1)->days();
        $this->assertFalse(app(LmsAccessService::class)->canAccess($this->lucy, $one));
        $this->assertSame('Expired', app(LmsTutoringReadModel::class)->assignments($this->lucy)->first()['group']);
        $two = $this->assign('lesson', $lesson->id);
        $this->assertNotSame($one->id, $two->id);
        $this->assertTrue(app(LmsAccessService::class)->canAccess($this->lucy, $two));
        $this->assertDatabaseCount('lms_enrollments', 1);
    }

    public function test_existing_lesson_and_assessment_assignments_use_canonical_lesson_scope(): void
    {
        $lesson = $this->lesson();
        $sibling = Lesson::factory()->published()->create(['course_id' => $lesson->course_id, 'section_id' => $lesson->section_id, 'title' => 'Unassigned sibling']);
        $quiz = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'quiz', 'status' => 'ready', 'payload' => $this->quizDefinition()]);
        $row = $this->assign('quiz', $quiz->id);
        $this->assertSame($quiz->id, $row->lesson_block_id);
        $this->assertSame('quiz', $row->block_kind);
        $this->assertSame($lesson->id, $row->accessGrant->lesson_id);
        $this->signIn($this->lucy)->get(route('student.learning.assignments.show', $row))->assertRedirect(route('student.evidence.show', [$lesson->course, $lesson, $quiz]));
        $this->get(route('student.learning.lessons.show', [$lesson->course, $sibling]))->assertNotFound();
        $this->signIn($this->sarah)->get(route('student.learning.assignments.show', $row))->assertNotFound();
        $this->actingAs($this->actor, 'web')->postJson(route('admin.students.learning.store', $this->lucy), $this->data('assignment', $quiz->id))->assertNotFound();
    }

    private function quizDefinition(): array
    {
        return ['title' => 'Greetings quiz', 'passing_score' => 80, 'attempt_limit' => 2, 'review_policy' => 'after_grading',
            'questions' => [['type' => 'choice', 'prompt' => 'Choose hello', 'points' => 1, 'options' => ['Hello', 'Goodbye'], 'answer' => 0]]];
    }

    public function test_private_item_is_idempotent_and_cannot_absorb_tutor_only_records(): void
    {
        $booking = Booking::factory()->create(['student_id' => $this->lucy->id, 'status' => 'completed', 'completed_at' => now('UTC')]);
        TutorPreparation::factory()->create(['student_id' => $this->lucy->id, 'booking_id' => $booking->id, 'body' => 'TUTOR ONLY PREPARATION']);
        StudentBin::factory()->create(['student_id' => $this->lucy->id, 'student_visible' => false, 'body' => 'STAFF ONLY NOTE']);
        $data = ['title' => 'Private follow-up', 'kind' => 'rich_text', 'html' => '<p>Practice</p>', 'booking_id' => $booking->id, 'request_key' => (string) Str::uuid(), 'share_now' => true];
        $url = route('admin.students.private-learning.store', $this->lucy);
        $this->actingAs($this->actor, 'web')->post($url, $data)->assertRedirect();
        $this->post($url, $data)->assertRedirect();
        $this->postJson($url, array_replace($data, ['title' => 'Changed original title']))->assertConflict();
        $course = Course::query()->firstOrFail();
        $this->assertDatabaseCount('lms_courses', 1);
        $this->assertDatabaseCount('lms_learning_assignments', 1);
        $this->assertSame($booking->id, LearningAssignment::query()->firstOrFail()->booking_id);
        $this->assertArrayNotHasKey('private_learning_fingerprint', $course->toArray());
        $this->signIn($this->lucy)->get(route('student.learning.courses.show', $course))->assertOk()->assertDontSee('TUTOR ONLY PREPARATION')->assertDontSee('STAFF ONLY NOTE');
        $this->signIn($this->sarah)->get(route('student.learning.courses.show', $course))->assertNotFound()->assertDontSee('Private follow-up');
        $this->actingAs($this->actor, 'web')->get(route('admin.students.private-learning.edit', [$this->sarah, $course]))->assertNotFound();
    }

    public function test_delivery_needs_no_follow_up_and_later_or_failed_assignment_cannot_revert_it(): void
    {
        $lesson = $this->lesson();
        $booking = Booking::factory()->create(['student_id' => $this->lucy->id]);
        $url = route('admin.bookings.show', $booking);
        $this->actingAs($this->actor, 'web')->from($url)->post(route('admin.bookings.complete', $booking))->assertRedirect($url);
        $completedAt = $booking->fresh()->completed_at->toIso8601String();
        $this->assertDatabaseCount('lms_learning_assignments', 0);
        $this->get($url)->assertOk()->assertSee('Assign Follow-up Learning');
        $this->get(route('admin.lessons.show', $booking))->assertOk()->assertSee('Session delivered.');
        $this->postJson(route('admin.students.learning.store', $this->lucy), $this->data('course', 999999, ['booking_id' => $booking->id]))->assertNotFound();
        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame($completedAt, $booking->fresh()->completed_at->toIso8601String());
        $this->assign('course', $lesson->course_id, ['booking_id' => $booking->id]);
        $this->travel(1)->days();
        $resource = ResourceFactory::new()->create();
        $this->assign('resource', $resource->id, ['booking_id' => $booking->id]);
        $this->get(route('booking.confirmation', $booking->confirmation_token))->assertOk()->assertSee('Session Delivered')->assertDontSee('Booking Canceled');
        $this->get(route('booking.confirmation', $booking->confirmation_token))->assertSee('Session Delivered')->assertDontSee('Booking Canceled');
        $this->assertSame($completedAt, $booking->fresh()->completed_at->toIso8601String());
        $this->assertSame(1, DB::table('booking_events')->where('booking_id', $booking->id)->where('event_type', 'completed')->count());
    }

    public static function invalidSessions(): array
    {
        return [['other'], ['cancelled'], ['no_show'], ['pending'], ['deleted']];
    }

    #[DataProvider('invalidSessions')]
    public function test_session_context_rejects_other_students_and_ineligible_sessions(string $state): void
    {
        $lesson = $this->lesson();
        $booking = Booking::factory()->create(['student_id' => $state === 'other' ? $this->sarah->id : $this->lucy->id, 'status' => in_array($state, ['other', 'deleted'], true) ? 'confirmed' : $state]);
        if ($state === 'deleted') {
            $booking->delete();
        }
        $this->actingAs($this->actor, 'web')->postJson(route('admin.students.learning.store', $this->lucy), $this->data('course', $lesson->course_id, ['booking_id' => $booking->id]))->assertStatus(in_array($state, ['other', 'deleted'], true) ? 404 : 422);
        $this->postJson(route('admin.students.private-learning.store', $this->lucy), ['title' => 'Private', 'kind' => 'video', 'request_key' => (string) Str::uuid(), 'booking_id' => $booking->id])->assertStatus(in_array($state, ['other', 'deleted'], true) ? 404 : 422);
        $this->assertDatabaseCount('lms_access_grants', 0);
        $this->assertSame(1, Course::query()->count());
    }

    public function test_resources_keep_existing_file_authority_and_withdrawal_keeps_source_even_when_unpublished(): void
    {
        $resource = ResourceFactory::new()->create(['title' => 'Shared resource source']);
        $assignment = $this->assign('resource', $resource->id, ['instructions' => 'Read this before class.']);
        $duplicate = $this->assign('resource', $resource->id);
        $this->assertSame($assignment->id, $duplicate->id);
        $this->assertDatabaseCount('lms_access_grants', 0);
        $this->signIn($this->lucy)->get(route('student.resources.open', $assignment))->assertRedirect('https://example.test/practice');
        $this->signIn($this->sarah)->get(route('student.resources.open', $assignment))->assertNotFound();
        $this->actingAs($this->actor, 'web')->deleteJson(route('admin.students.learning.resources.withdraw', [$this->sarah, $assignment]))->assertNotFound();
        $resource->update(['status' => 'draft']);
        $this->delete(route('admin.students.learning.resources.withdraw', [$this->lucy, $assignment]))->assertRedirect();
        $this->assertFalse($assignment->fresh()->student_visible);
        $this->assertDatabaseHas('resources', ['id' => $resource->id, 'title' => 'Shared resource source']);
        $this->assertDatabaseHas('resource_assignments', ['id' => $assignment->id]);
        $this->postJson(route('admin.students.learning.store', $this->lucy), $this->data('resource', 999999))->assertNotFound();
    }

    public function test_private_quiz_and_assignment_use_stage6_scoring_review_and_profile_progress(): void
    {
        $quizCourse = $this->privateItem('quiz', ['title' => 'محادثة · Conversation quiz', 'definition' => $this->quizDefinition()]);
        $lesson = Lesson::query()->where('course_id', $quizCourse->id)->firstOrFail();
        $block = $lesson->blocks()->where('kind', 'quiz')->firstOrFail();
        $quiz = app(LmsQuizService::class);
        $attempt = $quiz->begin($this->lucy, $quizCourse, $lesson, $block, (string) Str::uuid());
        $quiz->submit($this->lucy, $quizCourse, $lesson, $block, $attempt->id, [0]);
        $assignmentCourse = $this->privateItem('assignment', ['title' => 'Private written practice', 'definition' => ['title' => 'Writing', 'instructions' => 'Write a greeting.', 'types' => ['text']]]);
        $assignmentLesson = Lesson::query()->where('course_id', $assignmentCourse->id)->firstOrFail();
        $assignmentBlock = $assignmentLesson->blocks()->where('kind', 'assignment')->firstOrFail();
        $submission = app(LmsAssignmentService::class)->submit($this->lucy, $assignmentCourse, $assignmentLesson, $assignmentBlock, ['kind' => 'text', 'body' => 'أهلاً', 'request_key' => (string) Str::uuid()], null);
        $profile = app(LmsTutoringReadModel::class)->profile($this->actor, $this->lucy);
        $this->assertSame(1, $profile['review_needs']['assignments']);
        $this->assertSame('Completed', $profile['cards']->firstWhere('course.id', $quizCourse->id)['progress']['status']);
        $this->actingAs($this->actor, 'web')->get(route('admin.students.show', $this->lucy))->assertOk()->assertSee('1 assignments awaiting review')->assertSee('100% complete');
        $this->capture('stage7-profile', route('admin.students.show', $this->lucy));
        $this->capture('stage7-assign', route('admin.students.learning.create', $this->lucy));
        $this->capture('stage7-private', route('admin.students.private-learning.edit', [$this->lucy, $assignmentCourse]));
        app(LmsAssignmentService::class)->review($this->actor, $submission->id, 1, 'approved', 'Well done');
        $this->signIn($this->lucy)->get(route('student.learning.index'))->assertOk()->assertViewHas('forYou', fn ($items) => $items->where('group', 'Completed')->count() === 2);
        $this->capture('stage7-for-you', route('student.learning.index'));
        $this->signIn($this->sarah)->get(route('student.evidence.show', [$quizCourse, $lesson, $block]))->assertNotFound();
        $this->postJson(route('student.evidence.assignment.submit', [$assignmentCourse, $assignmentLesson, $assignmentBlock]), ['kind' => 'text', 'body' => 'Stolen', 'request_key' => (string) Str::uuid()])->assertNotFound();
    }

    public function test_manual_quiz_review_need_and_group_come_from_canonical_attempt(): void
    {
        $definition = $this->quizDefinition();
        $definition['questions'] = [['type' => 'written', 'prompt' => 'Write a greeting', 'points' => 1]];
        $course = $this->privateItem('quiz', ['definition' => $definition]);
        $lesson = Lesson::query()->where('course_id', $course->id)->firstOrFail();
        $block = $lesson->blocks()->where('kind', 'quiz')->firstOrFail();
        $row = $this->assign('quiz', $block->id);
        $attempt = app(LmsQuizService::class)->begin($this->lucy, $course, $lesson, $block, (string) Str::uuid());
        app(LmsQuizService::class)->submit($this->lucy, $course, $lesson, $block, $attempt->id, ['أهلاً']);
        $profile = app(LmsTutoringReadModel::class)->profile($this->actor, $this->lucy);
        $this->assertSame(1, $profile['review_needs']['quizzes']);
        $this->assertSame('Awaiting manual review', $profile['items']->firstWhere('assignment.id', $row->id)['assessment']);
        app(LmsQuizService::class)->review($this->actor, $attempt->id, 2, [0 => 1], 'Good');
        $this->assertSame('Completed', app(LmsTutoringReadModel::class)->assignments($this->lucy)->first()['group']);
    }

    public function test_quiz_retake_keeps_canonical_completion_and_reports_latest_result(): void
    {
        $course = $this->privateItem('quiz', ['definition' => $this->quizDefinition()]);
        $lesson = Lesson::query()->where('course_id', $course->id)->firstOrFail();
        $block = $lesson->blocks()->where('kind', 'quiz')->firstOrFail();
        $row = $this->assign('quiz', $block->id);
        $quizzes = app(LmsQuizService::class);
        foreach ([0, 1] as $answer) {
            $attempt = $quizzes->begin($this->lucy, $course, $lesson, $block, (string) Str::uuid());
            $quizzes->submit($this->lucy, $course, $lesson, $block, $attempt->id, [$answer]);
        }
        $item = app(LmsTutoringReadModel::class)->assignments($this->lucy)->firstWhere('assignment.id', $row->id);
        $this->assertSame('Completed', $item['group']);
        $this->assertSame('Graded — not passed', $item['assessment']);
        $this->assertSame('Completed', $item['progress']['status']);
    }

    public function test_private_video_reuses_bunny_ready_checks_and_protected_owner_playback(): void
    {
        config(['app.url' => 'http://example.test']);
        $this->withCredentials();
        Http::preventStrayRequests();
        $course = $this->privateItem('video');
        $profile = app(LmsVideoProfiles::class)->effective($course);
        $this->assertTrue($profile->secure_playback);
        $this->assertTrue($profile->watermark);
        $this->assertFalse($profile->downloads_allowed);
        $connection = VideoProviderConnection::factory()->create(['enabled' => true, 'allowed_domains' => ['example.test'], 'api_key' => 'fixture-api-key-123456',
            'signing_key' => 'fixture-sign-key-123456', 'account_key' => 'fixture-account-key-123456', 'read_only_key' => 'fixture-read-key-123456']);
        $guid = (string) Str::uuid();
        Http::fake(['video.bunnycdn.com/library/123/videos' => Http::response(['guid' => $guid]),
            'video.bunnycdn.com/library/123/videos/'.$guid => Http::response(['guid' => $guid, 'videoLibraryId' => 123, 'status' => 4, 'length' => 300, 'hasMP4Fallback' => false]),
            'api.bunny.net/videolibrary/123' => Http::response(['Id' => 123, 'PullZoneId' => 456, 'PlayerTokenAuthenticationEnabled' => true, 'BlockNoneReferrer' => true,
                'EnableMP4Fallback' => false, 'ExposeOriginals' => false, 'AllowDirectPlay' => false, 'AllowEarlyPlay' => false, 'EnableDRM' => false, 'AllowedReferrers' => ['example.test']]),
            'api.bunny.net/pullzone/456' => Http::response(['Id' => 456, 'Enabled' => true, 'Suspended' => false, 'ZoneSecurityEnabled' => true, 'ZoneSecurityIncludeHashRemoteIP' => false,
                'ZoneSecurityKey' => 'fixture-sign-key-123456', 'Hostnames' => [['Value' => 'test-library.b-cdn.net', 'ForceSSL' => true]], 'BlockNoneReferrer' => true, 'AllowedReferrers' => ['example.test'],
                'EdgeRules' => [], 'EdgeScriptId' => null, 'MiddlewareScriptId' => null, 'EnableAccessControlOriginHeader' => true, 'AccessControlOriginHeaderExtensions' => ['*']])]);
        $this->actingAs($this->actor, 'web')->postJson(route('admin.students.private-learning.share', [$this->lucy, $course]), ['version' => $course->lock_version, 'request_key' => (string) Str::uuid()])->assertUnprocessable();
        $created = $this->postJson(route('admin.students.private-learning.video', [$this->lucy, $course]), ['version' => $course->lock_version, 'label' => 'Lucy’s video', 'request_key' => (string) Str::uuid()])->assertOk()->assertDontSee('fixture-api-key');
        $asset = VideoAsset::query()->findOrFail($created->json('asset_id'));
        $this->postJson($created->json('authorization_url'))->assertOk()->assertJsonPath('endpoint', 'https://video.bunnycdn.com/tusupload');
        $this->postJson($created->json('status_url'))->assertOk()->assertJsonPath('status', 'ready');
        $graph = app(CourseStudioService::class)->draft($this->actor, $course->fresh());
        $key = $graph['sections'][0]['lessons'][0]['key'];
        $this->patch(route('admin.students.private-learning.update', [$this->lucy, $course]), ['version' => $course->fresh()->lock_version, 'operation' => 'add_block', 'parent_key' => $key, 'kind' => 'video', 'video_asset_id' => $asset->id, 'protection_profile_id' => 999999])->assertRedirect();
        $this->post(route('admin.students.private-learning.share', [$this->lucy, $course]), ['version' => $course->fresh()->lock_version, 'request_key' => (string) Str::uuid()])->assertRedirect();
        $lesson = Lesson::query()->where('course_id', $course->id)->firstOrFail();
        $block = $lesson->blocks()->where('kind', 'video')->firstOrFail();
        $this->assertNull($lesson->protection_profile_id);
        $token = bin2hex(random_bytes(32));
        AuthorizedDevice::factory()->create(['student_id' => $this->lucy->id, 'token_hash' => hash('sha256', $token)]);
        $this->withCookie(VideoDeviceService::COOKIE, $token);
        $this->signIn($this->lucy);
        app('session')->save();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $this->get(route('student.learning.lessons.show', [$course, $lesson]))->assertOk()->assertSee('data-protected-player', false)->assertDontSee($guid);
        $this->postJson(route('student.video.authorize', [$course, $lesson, $block]), ['request_key' => (string) Str::uuid(), 'lease_token' => bin2hex(random_bytes(32))])->assertOk()->assertJsonPath('watermark', fn ($text) => str_contains($text, 'Learner'))->assertDontSee('fixture-sign-key');
        $this->signIn($this->sarah)->get(route('student.learning.lessons.show', [$course, $lesson]))->assertNotFound();
        $this->postJson(route('student.video.authorize', [$course, $lesson, $block]), ['request_key' => (string) Str::uuid(), 'lease_token' => bin2hex(random_bytes(32))])->assertNotFound();
        $this->actingAs($this->actor, 'web')->postJson(route('admin.students.private-learning.video.status', [$this->sarah, $course, $asset]))->assertNotFound();
        $foreign = VideoAsset::factory()->create(['course_id' => Course::factory()->create()->id, 'provider_connection_id' => $connection->id]);
        $this->postJson(route('admin.students.private-learning.video.upload', [$this->lucy, $course, $foreign]))->assertNotFound();
    }

    public function test_bunny_failure_keeps_private_draft_and_delivered_session_unchanged(): void
    {
        Http::preventStrayRequests();
        Http::fake(['video.bunnycdn.com/library/123/videos' => Http::failedConnection()]);
        VideoProviderConnection::factory()->create(['enabled' => true, 'api_key' => 'fixture-api-key-123456']);
        $booking = Booking::factory()->create(['student_id' => $this->lucy->id, 'status' => 'completed', 'completed_at' => now('UTC')]);
        $before = $booking->fresh()->getRawOriginal();
        $course = $this->privateItem('video', ['booking_id' => $booking->id]);
        $this->actingAs($this->actor, 'web')->postJson(route('admin.students.private-learning.video', [$this->lucy, $course]), ['version' => $course->lock_version, 'label' => 'Private video', 'request_key' => (string) Str::uuid()])->assertStatus(503);
        $this->assertSame('failed', VideoAsset::query()->firstOrFail()->status);
        $this->assertSame('draft', $course->fresh()->status);
        $this->assertSame($before, $booking->fresh()->getRawOriginal());
        $this->assertDatabaseCount('lms_access_grants', 0);
    }

    public function test_private_editor_rejects_foreign_lesson_asset_and_nonready_video_and_shares_owned_attachments(): void
    {
        Storage::fake('local');
        $course = $this->privateItem('rich_text', ['share_now' => false]);
        $other = app(LmsTutoringAssignments::class)->createPrivate($this->actor, $this->sarah, ['kind' => 'rich_text', 'title' => 'Sarah private', 'html' => '<p>Sarah only</p>', 'request_key' => (string) Str::uuid()]);
        $studio = app(CourseStudioService::class);
        $lesson = $studio->draft($this->actor, $course)['sections'][0]['lessons'][0];
        $foreignLesson = $studio->draft($this->actor, $other)['sections'][0]['lessons'][0];
        $url = route('admin.students.private-learning.update', [$this->lucy, $course]);
        $this->actingAs($this->actor, 'web')->patchJson($url, ['version' => $course->lock_version, 'operation' => 'add_block', 'parent_key' => $foreignLesson['key'], 'kind' => 'rich_text', 'html' => '<p>Tampered</p>'])->assertNotFound();
        $this->post(route('admin.students.private-learning.upload', [$this->lucy, $course]), ['version' => $course->lock_version, 'kind' => 'image', 'file' => UploadedFile::fake()->image('private-image.png')])->assertRedirect();
        $asset = LmsAsset::query()->where('course_id', $course->id)->firstOrFail();
        $this->patchJson(route('admin.students.private-learning.update', [$this->sarah, $other]), ['version' => $other->lock_version, 'operation' => 'add_block', 'parent_key' => $foreignLesson['key'], 'kind' => 'image', 'asset_id' => $asset->id, 'alt' => 'Stolen image'])->assertUnprocessable();
        $processing = VideoAsset::factory()->create(['course_id' => $course->id, 'status' => 'processing']);
        $this->patchJson($url, ['version' => $course->fresh()->lock_version, 'operation' => 'add_block', 'parent_key' => $lesson['key'], 'kind' => 'video', 'video_asset_id' => $processing->id])->assertNotFound();
        $this->patch($url, ['version' => $course->fresh()->lock_version, 'operation' => 'add_block', 'parent_key' => $lesson['key'], 'kind' => 'image', 'asset_id' => $asset->id, 'alt' => 'Arabic writing practice'])->assertRedirect();
        $resource = ResourceFactory::new()->create();
        $this->patch($url, ['version' => $course->fresh()->lock_version, 'operation' => 'add_block', 'parent_key' => $lesson['key'], 'kind' => 'resource', 'resource_id' => $resource->id])->assertRedirect();
        $this->post(route('admin.students.private-learning.share', [$this->lucy, $course]), ['version' => $course->fresh()->lock_version, 'request_key' => (string) Str::uuid()])->assertRedirect();
        $publishedLesson = Lesson::query()->where('course_id', $course->id)->firstOrFail();
        $image = $publishedLesson->blocks()->where('kind', 'image')->firstOrFail();
        $this->signIn($this->lucy)->get(route('student.learning.materials.open', [$course, $publishedLesson, $image]))->assertOk();
        $this->signIn($this->sarah)->get(route('student.learning.materials.open', [$course, $publishedLesson, $image]))->assertNotFound();
        Storage::disk('local')->assertExists($asset->path);
    }

    public function test_review_notifications_and_timeline_use_current_owned_canonical_evidence(): void
    {
        $quizCourse = $this->privateItem('quiz', ['definition' => $this->quizDefinition()]);
        $lesson = Lesson::query()->where('course_id', $quizCourse->id)->firstOrFail();
        $block = $lesson->blocks()->where('kind', 'quiz')->firstOrFail();
        $attempt = app(LmsQuizService::class)->begin($this->lucy, $quizCourse, $lesson, $block, (string) Str::uuid());
        app(LmsQuizService::class)->submit($this->lucy, $quizCourse, $lesson, $block, $attempt->id, [0]);
        $this->travel(1)->minutes();
        $course = $this->privateItem('assignment', ['definition' => ['title' => 'Practice response', 'instructions' => 'Write Arabic', 'types' => ['text']]]);
        $lesson = Lesson::query()->where('course_id', $course->id)->firstOrFail();
        $block = $lesson->blocks()->where('kind', 'assignment')->firstOrFail();
        $submission = app(LmsAssignmentService::class)->submit($this->lucy, $course, $lesson, $block, ['kind' => 'text', 'body' => 'Private response', 'request_key' => (string) Str::uuid()], null);
        $this->travel(1)->minutes();
        app(LmsAssignmentService::class)->review($this->actor, $submission->id, 1, 'approved', 'Private review feedback');
        $events = app(LmsTeachingTimeline::class)->staff($this->actor, $this->lucy);
        $this->assertSame(1, $events->where('label', 'Quiz passed')->count());
        $this->assertSame(1, $events->where('label', 'Assignment submitted')->count());
        $this->assertSame(1, $events->where('label', 'Assignment reviewed')->count());
        $this->assertTrue($events->firstWhere('label', 'Assignment reviewed')['at']->gt($events->firstWhere('label', 'Assignment submitted')['at']));
        $facts = app(LmsLearningNotifications::class)->facts($this->lucy);
        $this->assertCount(1, collect($facts)->where('title', 'Quiz result available'));
        $this->assertCount(1, collect($facts)->where('title', 'Assignment review updated'));
        $this->assertSame([], app(LmsLearningNotifications::class)->facts($this->sarah));
        $this->assertStringNotContainsString('Private response', json_encode($facts));
        $this->assertStringNotContainsString('Private review feedback', $events->toJson());
        $quizAt = $events->firstWhere('label', 'Quiz passed')['at']->toIso8601String();
        $reviewAt = $events->firstWhere('label', 'Assignment reviewed')['at']->toIso8601String();
        $this->travel(1)->days();
        app(StudentMergeService::class)->merge($this->sarah->id, $this->lucy->id, $this->actor->id);
        $merged = app(LmsTeachingTimeline::class)->staff($this->actor, $this->sarah);
        $this->assertSame($quizAt, $merged->firstWhere('label', 'Quiz passed')['at']->toIso8601String());
        $this->assertSame($reviewAt, $merged->firstWhere('label', 'Assignment reviewed')['at']->toIso8601String());
    }

    public static function invalidAssignmentPairs(): array
    {
        return [[null, 'quiz'], ['block', null], ['block', 'video']];
    }

    #[DataProvider('invalidAssignmentPairs')]
    public function test_database_rejects_incomplete_or_nonassessment_target_pairs(?string $block, ?string $kind): void
    {
        $lesson = $this->lesson();
        $row = $this->assign('lesson', $lesson->id);
        $blockId = $lesson->blocks()->firstOrFail()->id;
        $this->expectException(QueryException::class);
        DB::table('lms_learning_assignments')->where('id', $row->id)->update(['lesson_block_id' => $block ? $blockId : null, 'block_kind' => $kind]);
    }

    public function test_notifications_sync_only_on_visits_and_preserve_semantic_ownership_and_read_state(): void
    {
        $lesson = $this->lesson();
        $this->assign('course', $lesson->course_id);
        $this->assertDatabaseCount('student_notifications', 0);
        $this->signIn($this->sarah)->get(route('student.notifications.index'))->assertOk();
        $this->assertDatabaseCount('student_notifications', 0);
        $this->signIn($this->lucy)->get(route('student.notifications.index'))->assertOk()->assertSee('Learning assigned');
        $notification = StudentNotification::query()->where('type', 'learning')->firstOrFail();
        $this->assertSame($this->lucy->id, $notification->student_id);
        $notification->update(['read_at' => now('UTC')]);
        $this->get(route('student.learning.index'))->assertOk();
        $this->assertSame(1, StudentNotification::query()->where('type', 'learning')->count());
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assign('lesson', $lesson->id, ['starts_at' => now('UTC')->addDay()->toIso8601String()]);
        $this->get(route('student.notifications.index'))->assertOk();
        $this->assertSame(1, StudentNotification::query()->where('type', 'learning')->count());
        $this->travel(1)->days();
        $this->signIn($this->lucy)->get(route('student.notifications.index'))->assertOk();
        $this->assertSame(2, StudentNotification::query()->where('type', 'learning')->count());
        $this->assertStringNotContainsString('stage7-lucy@', StudentNotification::query()->get()->toJson());
    }

    public function test_timeline_has_canonical_order_unique_keys_and_no_security_heartbeats(): void
    {
        $booking = Booking::factory()->create(['student_id' => $this->lucy->id, 'status' => 'completed', 'completed_at' => now('UTC')->subHour()]);
        $lesson = $this->lesson();
        $this->assign('course', $lesson->course_id, ['booking_id' => $booking->id]);
        $this->travel(1)->minutes();
        app(LmsProgressService::class)->manual($this->lucy, $lesson->course, $lesson);
        app(AuditLogService::class)->logStudent($this->lucy->id, 'lms_video_heartbeat', Student::class, $this->lucy->id);
        $timeline = app(LmsTeachingTimeline::class)->staff($this->actor, $this->lucy);
        $this->assertCount(4, $timeline);
        $this->assertSame(['Course completed', 'Lesson completed', 'Learning assigned', 'Session delivered'], $timeline->pluck('label')->all());
        $this->assertSame($timeline->pluck('key')->all(), $timeline->pluck('key')->unique()->all());
        $this->assertSame($timeline->pluck('key')->all(), app(LmsTeachingTimeline::class)->staff($this->actor, $this->lucy)->pluck('key')->all());
        $this->assertStringNotContainsString('heartbeat', $timeline->toJson());
        $this->assertCount(0, app(LmsTeachingTimeline::class)->student($this->sarah));
        $summary = app(LmsProgressService::class)->summary($this->lucy, collect([$lesson]));
        $this->assertSame(now('UTC')->toIso8601String(), $summary['completed_at']->toIso8601String());
        $this->assertSame($summary['completion_key'], app(LmsProgressService::class)->summary($this->lucy, collect([$lesson]))['completion_key']);
    }

    public function test_bulk_assigns_individual_rights_and_rolls_back_all_on_ineligible_student(): void
    {
        $lesson = $this->lesson();
        $data = $this->data('course', $lesson->course_id);
        $this->actingAs($this->actor, 'web')->post(route('admin.students.learning.store', $this->lucy), $data + ['extra_student_ids' => [$this->sarah->id]])->assertRedirect();
        $this->assertSame([$this->lucy->id, $this->sarah->id], AccessGrant::query()->orderBy('student_id')->pluck('student_id')->all());
        $this->assertDatabaseCount('lms_enrollments', 2);
        $other = Student::factory()->create();
        $new = $this->lesson();
        $this->postJson(route('admin.students.learning.store', $this->lucy), $this->data('course', $new->course_id) + ['extra_student_ids' => [$other->id]])->assertUnprocessable();
        $this->assertDatabaseCount('lms_access_grants', 2);
        $private = $this->privateItem('rich_text', ['share_now' => false]);
        $this->assertSame('draft', $private->status);
    }

    public static function deniedRoles(): array
    {
        return [['assistant'], ['student'], ['suspended']];
    }

    #[DataProvider('deniedRoles')]
    public function test_unauthorized_roles_cannot_assign_or_read_private_authoring(string $role): void
    {
        $course = $this->privateItem('video');
        if ($role === 'student') {
            $this->signIn($this->lucy);
        } else {
            $this->actingAs(AdministratorFactory::new()->create(['role' => $role === 'suspended' ? 'admin' : $role, 'suspended_at' => $role === 'suspended' ? now('UTC') : null]), 'web');
        }
        $this->getJson(route('admin.students.learning.create', $this->lucy))->assertStatus($role === 'student' ? 401 : ($role === 'suspended' ? 302 : 403));
        $this->postJson(route('admin.students.learning.store', $this->lucy), $this->data('course', $course->id))->assertStatus($role === 'assistant' ? 403 : 401);
        $this->getJson(route('admin.students.private-learning.edit', [$this->lucy, $course]))->assertStatus($role === 'assistant' ? 403 : 401);
        $this->assertDatabaseCount('lms_access_grants', 0);
    }

    public function test_profile_and_forms_link_existing_plans_without_changing_next_action(): void
    {
        $lesson = $this->lesson();
        Homework::factory()->create(['student_id' => $this->lucy->id, 'student_visible' => true, 'title' => 'Canonical Homework']);
        LearningPlan::factory()->create(['student_id' => $this->lucy->id, 'student_visible' => true]);
        $this->assign('course', $lesson->course_id);
        $this->signIn($this->lucy)->get(route('student.learning.index'))->assertOk()->assertViewHas('nextAction', fn ($action) => $action['label'] === 'Continue your homework')->assertSee('Homework & learning plans', false);
        $this->actingAs($this->actor, 'web')->get(route('admin.students.learning.create', $this->lucy))->assertOk()->assertSee('Create for this Student')->assertSee('Assign existing learning')->assertHeader('Cache-Control', 'no-store, private');
        $assistant = AdministratorFactory::new()->create(['role' => 'assistant']);
        $this->actingAs($assistant, 'web')->get(route('admin.students.show', $this->lucy))->assertOk()->assertDontSee('Assign Learning')->assertDontSee('Arabic conversations');
    }

    public function test_assignments_leave_existing_typed_funding_finance_and_teaching_records_identical(): void
    {
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($this->lucy, 'One hour', 3, '120.00', '0.00', 'USD', null, 'stage7-one', entitlementCode: 'one_hour');
        $ledger->createPackage($this->lucy, 'Two hour', 2, '160.00', '0.00', 'USD', null, 'stage7-two', entitlementCode: 'two_hour');
        $ledger->recordPayment($package, '40.00', 'stage7-payment', $this->actor->id);
        app(InstallmentScheduleService::class)->create($package, [['expected_amount' => '120.00', 'due_date' => now('UTC')->addMonth()->toDateString()]], 'stage7-schedule', $this->actor->id);
        DB::transaction(function () use ($ledger): void {
            Student::query()->whereKey($this->lucy->id)->lockForUpdate()->firstOrFail();
            $booking = Booking::factory()->create(['student_id' => $this->lucy->id, 'session_type_id' => $this->oneHourPackageLesson()->id]);
            $ledger->consumeForBooking($booking, 'stage7-consume');
        });
        Homework::factory()->create(['student_id' => $this->lucy->id]);
        LearningPlan::factory()->create(['student_id' => $this->lucy->id]);
        $tables = ['student_packages', 'student_package_entitlements', 'session_ledger_entries', 'payment_records', 'payment_refunds', 'package_installments', 'bookings', 'homeworks', 'learning_plans'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]);
        $lesson = $this->lesson();
        $row = $this->assign('course', $lesson->course_id);
        $this->privateItem();
        app(LmsTutoringAssignments::class)->change($this->actor, $this->lucy, $row->accessGrant, 'revoke', [], 1, (string) Str::uuid());
        app(LmsTutoringReadModel::class)->profile($this->actor, $this->lucy);
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
    }

    public static function invalidLocalTimes(): array
    {
        return [['2026-03-08T02:30'], ['2026-11-01T01:30']];
    }

    #[DataProvider('invalidLocalTimes')]
    public function test_assignment_wall_time_rejects_dst_gap_and_fold(string $local): void
    {
        $lesson = $this->lesson();
        Setting::set('business_timezone', 'America/New_York');
        $this->actingAs($this->actor, 'web')->postJson(route('admin.students.learning.store', $this->lucy), $this->data('course', $lesson->course_id, ['starts_local' => $local]))->assertUnprocessable();
        $this->assertDatabaseCount('lms_access_grants', 0);
    }

    public function test_private_learning_survives_canonical_merge_and_is_redacted_by_privacy_authority(): void
    {
        $course = $this->privateItem('quiz', ['definition' => $this->quizDefinition()]);
        $lesson = Lesson::query()->where('course_id', $course->id)->firstOrFail();
        $block = $lesson->blocks()->where('kind', 'quiz')->firstOrFail();
        $row = $this->assign('quiz', $block->id);
        app(StudentMergeService::class)->merge($this->sarah->id, $this->lucy->id, $this->actor->id);
        $this->assertSame($this->sarah->id, $course->fresh()->owner_student_id);
        $this->assertSame($block->id, $row->fresh()->lesson_block_id);
        $this->assertTrue(app(LmsAccessService::class)->canAccess($this->sarah, $row->fresh()));
        app(StudentPrivacyService::class)->anonymize($this->sarah->id, $this->actor->id);
        $this->assertFalse(app(LmsAccessService::class)->canAccess($this->sarah, $row->fresh()));
        $this->assertSame('withdrawn', $row->fresh()->status);
        $this->assertStringNotContainsString('Greetings quiz', $block->fresh()->toJson());
    }
}
