<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\AdminNotification;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Notifications\Services\TelegramAutomationService;
use App\Domains\Students\Models\FeedbackConversation;
use App\Domains\Students\Models\FeedbackEvent;
use App\Domains\Students\Models\FeedbackReadState;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Services\FeedbackNotifications;
use App\Domains\Students\Services\FeedbackReadModel;
use App\Domains\Students\Services\FeedbackService;
use App\Domains\Students\Services\StudentIntendedDestination;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StudentFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();
    }

    private function signIn(Student $student): static
    {
        $this->flushSession();
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()]);
        auth('student')->login($student);
        $this->withCredentials();
        $this->withCookie(config('session.cookie'), session()->getId());
        $proof = $this->getJson(route('student.feedback.session'))->assertOk()->json('proof');
        $this->withHeader('X-Feedback-Session', $proof);

        return $this;
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'type' => 'question', 'area' => 'learning', 'subject' => 'أهلاً · A question', 'body' => "Bonjour — مرحباً\n\nSecond paragraph."], $overrides);
    }

    private function conversation(Student $student): FeedbackConversation
    {
        $operation = app(FeedbackService::class)->create($student, $this->payload());

        return FeedbackConversation::query()->findOrFail($operation->conversation_id);
    }

    private function action(FeedbackConversation $conversation, string $action, array $extra = []): array
    {
        return $extra + ['request_key' => (string) Str::uuid(), 'action' => $action, 'version' => $conversation->fresh()->version, 'body' => 'A tutor reply'];
    }

    public function test_creation_preserves_unicode_and_creates_one_private_history_with_exact_notification_identity(): void
    {
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $this->signIn($student);
        $payload = $this->payload(['subject' => " \u{00A0} سؤال é\u{00A0} ", 'student_id' => $other->id, 'status' => 'closed', 'actor_id' => 999]);
        $this->postJson(route('student.feedback.store'), $payload)->assertOk()->assertJsonStructure(['redirect']);
        $conversation = FeedbackConversation::query()->sole();
        $this->assertSame($student->id, $conversation->student_id);
        $this->assertSame('open', $conversation->status);
        $this->assertSame('سؤال é', $conversation->subject);
        $this->assertSame($payload['body'], FeedbackEvent::query()->sole()->body);
        $this->assertDatabaseCount('feedback_operations', 1);
        $notification = AdminNotification::query()->where('type', 'feedback')->sole();
        $this->assertStringNotContainsString('سؤال', json_encode($notification->toArray(), JSON_THROW_ON_ERROR));
        $this->assertSame(route('admin.feedback.show', $conversation->id, false), $notification->link);
        $notification->delete();
        $this->postJson(route('student.feedback.store'), $payload)->assertOk();
        $this->assertDatabaseCount('feedback_conversations', 1);
        $this->assertDatabaseCount('feedback_events', 1);
        $this->assertSame(0, AdminNotification::query()->where('type', 'feedback')->count());
        $payload['body'] = 'Edited request';
        $this->postJson(route('student.feedback.store'), $payload)->assertConflict();
        $this->postJson(route('student.feedback.store'), $this->payload())->assertOk();
        $this->assertDatabaseCount('feedback_conversations', 2);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public static function invalidInputs(): array
    {
        return [
            'type' => [['type' => 'survey'], 'type'],
            'area' => [['area' => 'staff'], 'area'],
            'empty subject' => [['subject' => " \u{00A0}\n "], 'subject'],
            'empty body' => [['body' => " \u{00A0}\n "], 'body'],
            'long subject' => [['subject' => str_repeat('ع', 161)], 'subject'],
            'long body' => [['body' => str_repeat('é', 5001)], 'body'],
            'identity' => [['request_key' => 'bad'], 'request_key'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_submission_returns_field_errors_and_writes_nothing(array $invalid, string $field): void
    {
        $this->signIn(Student::factory()->verified()->create());
        $this->postJson(route('student.feedback.store'), $this->payload($invalid))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('feedback_conversations', 0);
        $this->assertDatabaseCount('feedback_events', 0);
        $this->assertDatabaseCount('feedback_operations', 0);
    }

    public function test_unicode_length_boundaries_are_accepted_without_truncation(): void
    {
        $this->signIn(Student::factory()->verified()->create());
        $this->postJson(route('student.feedback.store'), $this->payload(['subject' => str_repeat('ع', 160), 'body' => str_repeat('é', 5000)]))->assertOk();
        $this->assertSame(5000, mb_strlen(FeedbackEvent::query()->sole()->body));
    }

    public function test_message_outer_paragraphs_are_preserved_and_student_reply_quota_is_shared(): void
    {
        $student = Student::factory()->verified()->create();
        $this->signIn($student);
        $body = "\nأهلاً\n\nBonjour\n";
        $this->postJson(route('student.feedback.store'), $this->payload(['body' => $body]))->assertOk();
        $conversation = FeedbackConversation::query()->sole();
        $this->assertSame($body, FeedbackEvent::query()->sole()->body);
        for ($index = 0; $index < 10; $index++) {
            $this->postJson(route('student.feedback.update', $conversation->id), $this->action($conversation, 'reply'))->assertOk();
        }
        $this->signIn($student);
        $this->postJson(route('student.feedback.update', $conversation->id), $this->action($conversation, 'reply'))->assertTooManyRequests();
        $conversation->update(['status' => 'closed']);
        $this->postJson(route('student.feedback.update', $conversation->id), $this->action($conversation, 'resolve'))->assertForbidden();
        $this->assertDatabaseCount('feedback_events', 11);
    }

    public static function workflow(): array
    {
        return [
            ['student', 'open', 'reply', 'open', 1], ['student', 'waiting_student', 'reply', 'open', 2], ['student', 'resolved', 'reply', 'open', 2],
            ['administrator', 'open', 'reply', 'open', 1], ['administrator', 'waiting_student', 'reply', 'waiting_student', 1], ['administrator', 'resolved', 'reply', 'resolved', 1],
            ['administrator', 'open', 'clarify', 'waiting_student', 2], ['administrator', 'waiting_student', 'clarify', 'waiting_student', 1], ['administrator', 'resolved', 'clarify', 'waiting_student', 2],
            ['administrator', 'open', 'resolve', 'resolved', 1], ['administrator', 'waiting_student', 'resolve', 'resolved', 1],
            ['administrator', 'resolved', 'close', 'closed', 1], ['administrator', 'closed', 'reopen', 'open', 1],
        ];
    }

    #[DataProvider('workflow')]
    public function test_allowed_workflow_commits_ordered_events_one_version_and_one_notification(string $role, string $before, string $action, string $after, int $events): void
    {
        $student = Student::factory()->verified()->create();
        $conversation = $this->conversation($student);
        $conversation->update(['status' => $before]);
        $actor = $role === 'student' ? $student : AdministratorFactory::new()->create(['role' => 'admin']);
        $this->mock(TelegramAutomationService::class)->shouldNotReceive('emit');
        $operation = app(FeedbackService::class)->mutate($actor, $conversation->id, $this->action($conversation, $action));
        $this->assertSame($after, $conversation->fresh()->status);
        $this->assertSame(2, $conversation->fresh()->version);
        $this->assertSame(range(1, $events + 1), $conversation->events()->orderBy('sequence')->pluck('sequence')->all());
        $this->assertSame($events + 1, $operation->result_sequence);
        $this->assertSame($role === 'student' ? 2 : 1, AdminNotification::where('type', 'feedback')->count());
        $this->assertSame($role === 'student' ? 0 : 1, StudentNotification::where('type', 'feedback')->count());
        $this->assertDatabaseCount('feedback_read_states', 0);
    }

    public static function prohibitedWorkflow(): array
    {
        return [['open', 'close'], ['waiting_student', 'close'], ['closed', 'reply'], ['closed', 'clarify'], ['closed', 'resolve'], ['open', 'reopen'], ['resolved', 'resolve']];
    }

    #[DataProvider('prohibitedWorkflow')]
    public function test_prohibited_or_no_change_status_actions_write_nothing(string $status, string $action): void
    {
        $conversation = $this->conversation(Student::factory()->verified()->create());
        $conversation->update(['status' => $status]);
        $this->actingAs(AdministratorFactory::new()->create(), 'web');
        $this->postJson(route('admin.feedback.update', $conversation->id), $this->action($conversation, $action))->assertConflict();
        $this->assertDatabaseCount('feedback_events', 1);
        $this->assertDatabaseCount('feedback_operations', 1);
        $this->assertSame(1, $conversation->fresh()->version);
        $this->assertDatabaseCount('student_notifications', 0);
    }

    public function test_stale_explicit_actions_conflict_but_ordinary_replies_serialize_and_keep_text(): void
    {
        $conversation = $this->conversation(Student::factory()->verified()->create());
        $admin = AdministratorFactory::new()->create();
        $this->actingAs($admin, 'web');
        $clarify = $this->action($conversation, 'clarify', ['body' => 'Clarification draft']);
        $this->postJson(route('admin.feedback.update', $conversation->id), $this->action($conversation, 'reply'))->assertOk();
        $this->postJson(route('admin.feedback.update', $conversation->id), $clarify)->assertConflict()->assertJsonPath('message', __('feedback.stale'));
        $this->postJson(route('admin.feedback.update', $conversation->id), $this->action($conversation, 'reply', ['version' => 1, 'body' => 'Another reply']))->assertOk();
        $this->assertSame(['Bonjour — مرحباً'."\n\nSecond paragraph.", 'A tutor reply', 'Another reply'], $conversation->events()->orderBy('sequence')->pluck('body')->all());
        $this->assertSame(2, StudentNotification::where('type', 'feedback')->count());
    }

    public function test_student_ownership_context_and_account_changes_are_non_disclosing(): void
    {
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $conversation = $this->conversation($other);
        $this->signIn($student);
        $this->get(route('student.feedback.index'))->assertDontSee($conversation->subject);
        foreach ([$conversation->id, 999999] as $id) {
            $this->getJson(route('student.feedback.show', $id))->assertNotFound();
            $this->postJson(route('student.feedback.update', $id), $this->action($conversation, 'reply'))->assertNotFound();
        }
        $hidden = Homework::factory()->create(['student_id' => $student->id]);
        $foreign = Homework::factory()->create(['student_id' => $other->id, 'student_visible' => true]);
        foreach ([$hidden->id, $foreign->id, 999999] as $id) {
            $this->postJson(route('student.feedback.store'), $this->payload(['context_type' => 'homework', 'context_id' => $id]))->assertNotFound();
        }
        $this->actingAs($other, 'student')->withSession(['student_id' => $other->id]);
        $this->postJson(route('student.feedback.store'), $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('feedback_conversations', 1);
    }

    public function test_context_reuses_lms_and_homework_access_without_visits_and_withdrawal_hides_titles(): void
    {
        $student = Student::factory()->verified()->create();
        $admin = AdministratorFactory::new()->create();
        $course = Course::factory()->published()->create(['kind' => 'private', 'owner_student_id' => $student->id]);
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id]);
        app(LmsAccessOperations::class)->grant($admin, $student, $lesson, [], 'feedback-context-'.Str::uuid());
        $this->signIn($student)->postJson(route('student.feedback.store'), $this->payload(['context_type' => 'lesson', 'context_id' => $lesson->id]))->assertOk();
        $conversation = FeedbackConversation::query()->sole();
        $this->get(route('student.feedback.show', $conversation->id))->assertSee($lesson->title);
        $this->assertDatabaseCount('lms_learning_visits', 0);
        $this->assertDatabaseCount('lms_lesson_progress', 0);
        $lesson->forceFill(['status' => 'archived'])->save();
        $this->get(route('student.feedback.show', $conversation->id))->assertSee(__('feedback.context_unavailable'))->assertDontSee($lesson->title);
    }

    public function test_read_receipts_are_independent_monotonic_and_do_not_cover_unrendered_arrivals_or_gaps(): void
    {
        $student = Student::factory()->verified()->create();
        $conversation = $this->conversation($student);
        $first = AdministratorFactory::new()->create();
        $second = AdministratorFactory::new()->create();
        config(['feedback.replies_per_minute' => 200]);
        for ($index = 0; $index < 105; $index++) {
            app(FeedbackService::class)->mutate($student, $conversation->id, $this->action($conversation, 'reply'));
        }
        $this->actingAs($first, 'web');
        $page = $this->get(route('admin.feedback.show', $conversation->id))->assertOk()->assertViewHas('page', 1);
        $this->assertCount(50, $page->viewData('events'));
        $this->assertDatabaseCount('feedback_read_states', 0);
        $late = app(FeedbackReadModel::class)->segment($first, $conversation->fresh(), 3);
        $this->postJson(route('admin.feedback.read', $conversation->id), ['receipt' => $late['receipt']])->assertConflict();
        $this->postJson(route('admin.feedback.read', $conversation->id), ['receipt' => 'forged'])->assertUnprocessable();
        app(FeedbackService::class)->mutate($student, $conversation->id, $this->action($conversation, 'reply'));
        $this->postJson(route('admin.feedback.read', $conversation->id), ['receipt' => $page->viewData('receipt')])->assertOk()->assertJsonPath('sequence', 50);
        $this->assertTrue((bool) app(FeedbackReadModel::class)->conversations($first)->sole()->has_unread);
        $this->assertTrue((bool) app(FeedbackReadModel::class)->conversations($second)->sole()->has_unread);
        app(FeedbackService::class)->mutate($first, $conversation->id, $this->action($conversation, 'reply'));
        $this->assertSame(50, FeedbackReadState::where('actor_id', $first->id)->where('actor_type', 'administrator')->sole()->last_sequence);
        $this->get(route('admin.feedback.show', $conversation->id))->assertViewHas('page', 2);
        $middle = app(FeedbackReadModel::class)->segment($first, $conversation->fresh(), 2);
        $this->postJson(route('admin.feedback.read', $conversation->id), ['receipt' => $middle['receipt']])->assertOk()->assertJsonPath('sequence', 100);
        $this->postJson(route('admin.feedback.read', $conversation->id), ['receipt' => $page->viewData('receipt')])->assertOk()->assertJsonPath('sequence', 100);
        $this->actingAs($second, 'web')->postJson(route('admin.feedback.read', $conversation->id), ['receipt' => $page->viewData('receipt')])->assertUnprocessable();
        $notification = AdminNotification::where('type', 'feedback')->first();
        $this->post(route('admin.notifications.read', $notification))->assertRedirect();
        $this->assertDatabaseCount('feedback_read_states', 1);
    }

    public function test_student_read_marker_never_changes_the_conversation_or_other_participants(): void
    {
        $student = Student::factory()->verified()->create();
        $conversation = $this->conversation($student);
        app(FeedbackService::class)->mutate(AdministratorFactory::new()->create(), $conversation->id, $this->action($conversation, 'reply'));
        $before = $conversation->fresh()->getAttributes();
        $this->signIn($student);
        $page = $this->get(route('student.feedback.show', $conversation->id));
        $this->postJson(route('student.feedback.read', $conversation->id), ['receipt' => $page->viewData('receipt')])->assertOk()->assertJsonPath('sequence', 2);
        $this->assertSame($before, $conversation->fresh()->getAttributes());
        $this->assertSame(0, StudentNotification::whereNotNull('read_at')->count());
        $this->assertFalse((bool) app(FeedbackReadModel::class)->conversations($student)->sole()->has_unread);
    }

    public function test_student_thread_automatically_requests_acknowledgment_and_admin_sees_confirmed_student_read_state(): void
    {
        $student = Student::factory()->verified()->create();
        $conversation = $this->conversation($student);
        $admin = AdministratorFactory::new()->create();
        app(FeedbackService::class)->mutate($admin, $conversation->id, $this->action($conversation, 'reply'));
        $before = $conversation->fresh()->getAttributes();
        $this->actingAs($admin, 'web')->get(route('admin.feedback.show', $conversation->id))
            ->assertSee(__('feedback.not_read_by_student'))
            ->assertSee(__('feedback.acknowledge'))
            ->assertDontSee('data-feedback-auto-read', false);

        $this->signIn($student);
        $page = $this->get(route('student.feedback.show', $conversation->id))
            ->assertSee('data-feedback-auto-read hidden', false)
            ->assertDontSee(__('feedback.read_help'))
            ->assertDontSee(__('feedback.acknowledge'))
            ->assertDontSee(__('feedback.draft_notice'));
        $this->assertDatabaseCount('feedback_read_states', 0);
        $this->postJson(route('student.feedback.read', $conversation->id), ['receipt' => $page->viewData('receipt')])->assertOk()->assertJsonPath('sequence', 2);

        $this->actingAs($admin, 'web')->get(route('admin.feedback.show', $conversation->id))
            ->assertSee(__('feedback.read_by_student'))
            ->assertDontSee(__('feedback.not_read_by_student'))
            ->assertSee(__('feedback.acknowledge'));
        $this->assertDatabaseCount('feedback_read_states', 1);
        $this->assertSame('student', FeedbackReadState::query()->sole()->actor_type);
        $this->assertSame($before, $conversation->fresh()->getAttributes());
        $this->assertSame(0, StudentNotification::query()->whereNotNull('read_at')->count());
        $this->assertTrue((bool) app(FeedbackReadModel::class)->conversations($admin)->sole()->has_unread);
    }

    public function test_activity_after_the_loaded_snapshot_does_not_select_a_nonexistent_history_segment(): void
    {
        $student = Student::factory()->verified()->create();
        $conversation = $this->conversation($student);
        for ($sequence = 2; $sequence <= 50; $sequence++) {
            FeedbackEvent::factory()->create(['conversation_id' => $conversation->id, 'sequence' => $sequence, 'actor_type' => 'student', 'actor_id' => $student->id, 'body' => 'Earlier own message']);
        }
        $conversation->update(['last_sequence' => 50]);
        $snapshot = $conversation->fresh();
        app(FeedbackService::class)->mutate(AdministratorFactory::new()->create(), $conversation->id, $this->action($conversation, 'reply'));

        $segment = app(FeedbackReadModel::class)->segment($student, $snapshot);
        $this->assertSame(1, $segment['page']);
        $this->assertCount(50, $segment['events']);
        $this->assertSame(50, app(FeedbackReadModel::class)->acknowledge($student, $conversation->id, $segment['receipt']));
        $this->assertTrue((bool) app(FeedbackReadModel::class)->conversations($student)->sole()->has_unread);
    }

    public static function roles(): array
    {
        return [['super_admin', true], ['admin', true], ['assistant', false]];
    }

    #[DataProvider('roles')]
    public function test_admin_access_is_role_scoped_on_screens_and_mutations(string $role, bool $allowed): void
    {
        $conversation = $this->conversation(Student::factory()->verified()->create());
        $this->actingAs(AdministratorFactory::new()->create(['role' => $role]), 'web');
        $screen = $this->get(route('admin.feedback.index'));
        $write = $this->postJson(route('admin.feedback.update', $conversation->id), $this->action($conversation, 'reply'));
        if ($allowed) {
            $screen->assertSee($conversation->subject);
            $write->assertOk();
        } else {
            $screen->assertForbidden();
            $write->assertForbidden();
            $this->assertDatabaseCount('feedback_events', 1);
        }
    }

    public function test_expired_restricted_and_preview_students_cannot_mutate(): void
    {
        $student = Student::factory()->verified()->create();
        $this->get(route('student.feedback.create'))->assertRedirect(route('student.login'));
        $this->assertSame('/student/feedback/new', session(StudentIntendedDestination::SESSION_KEY));
        $this->signIn($student);
        $student->update(['suspended_at' => now()]);
        $this->postJson(route('student.feedback.store'), $this->payload())->assertUnauthorized();
        $student->update(['suspended_at' => null]);
        $this->signIn($student);
        $admin = AdministratorFactory::new()->create();
        $this->actingAs($admin, 'web')->withSession(['lms_preview' => ['actor_id' => $admin->id]]);
        $this->postJson(route('student.feedback.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('feedback_conversations', 0);
    }

    public function test_personal_quota_counts_committed_operations_across_sessions_but_allows_replays(): void
    {
        $student = Student::factory()->verified()->create();
        $this->signIn($student);
        $first = $this->payload();
        $this->postJson(route('student.feedback.store'), $first)->assertOk();
        for ($index = 0; $index < 4; $index++) {
            $this->postJson(route('student.feedback.store'), $this->payload())->assertOk();
        }
        $this->signIn($student);
        $this->postJson(route('student.feedback.store'), $first)->assertOk();
        $this->postJson(route('student.feedback.store'), $this->payload())->assertTooManyRequests()->assertJsonPath('message', __('feedback.rate_limit'));
        $this->assertDatabaseCount('feedback_conversations', 5);
        $this->signIn(Student::factory()->verified()->create())->postJson(route('student.feedback.store'), $this->payload())->assertOk();
    }

    public function test_inbox_filters_compose_and_use_inclusive_business_dates_and_personal_read_state(): void
    {
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $this->travelTo(now('Africa/Cairo')->setDate(2026, 11, 3)->startOfDay()->addHour());
        $matching = $this->conversation($student);
        $this->conversation($other);
        $admin = AdministratorFactory::new()->create();
        $this->actingAs($admin, 'web');
        $filters = ['status' => 'open', 'type' => 'question', 'area' => 'learning', 'student_id' => $student->id, 'date_from' => '2026-11-03', 'date_to' => '2026-11-03', 'read' => 'unread'];
        $response = $this->get(route('admin.feedback.index', $filters))->assertOk();
        $this->assertSame([$matching->id], $response->viewData('conversations')->pluck('id')->all());
        $this->getJson(route('admin.feedback.index', ['date_from' => '2026-11-04', 'date_to' => '2026-11-03']))->assertUnprocessable();
        $this->getJson(route('admin.feedback.index', ['status' => 'bad']))->assertUnprocessable();
    }

    public function test_merge_preserves_thread_retry_and_read_integrity_then_erasure_scrubs_all_storage(): void
    {
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $admin = AdministratorFactory::new()->create();
        $conversation = $this->conversation($secondary);
        $reply = $this->action($conversation, 'reply', ['body' => 'PRIVATE STUDENT CONTENT']);
        app(FeedbackService::class)->mutate($secondary, $conversation->id, $reply);
        app(FeedbackService::class)->mutate($admin, $conversation->id, $this->action($conversation, 'reply', ['body' => 'PRIVATE TUTOR QUOTE']));
        $segment = app(FeedbackReadModel::class)->segment($secondary, $conversation->fresh());
        app(FeedbackReadModel::class)->acknowledge($secondary, $conversation->id, $segment['receipt']);
        app(StudentMergeService::class)->merge($primary->id, $secondary->id, $admin->id);
        $this->assertSame($primary->id, $conversation->fresh()->student_id);
        $this->assertSame(3, FeedbackReadState::where('actor_type', 'student')->sole()->last_sequence);
        app(FeedbackService::class)->mutate($primary, $conversation->id, $reply);
        $this->assertDatabaseCount('feedback_events', 3);
        $this->assertSame($primary->id, StudentNotification::where('type', 'feedback')->sole()->student_id);
        app(StudentPrivacyService::class)->anonymize($primary->id, $admin->id);
        $this->assertNotNull($conversation->fresh()->erased_at);
        $this->assertSame('Redacted feedback', $conversation->fresh()->subject);
        $this->assertSame(0, FeedbackEvent::whereNotNull('body')->count());
        $this->assertDatabaseCount('feedback_operations', 0);
        $this->assertDatabaseCount('feedback_read_states', 0);
        $this->assertSame(0, AdminNotification::where('type', 'feedback')->count());
        $this->assertSame(0, StudentNotification::where('type', 'feedback')->count());
        $this->actingAs($admin, 'web')->getJson(route('admin.feedback.show', $conversation->id))->assertNotFound();
    }

    public function test_notification_failure_rolls_back_history_state_and_retry_receipt(): void
    {
        $student = Student::factory()->verified()->create();
        $this->mock(FeedbackNotifications::class)->shouldReceive('send')->once()->andThrow(new \RuntimeException('Synthetic notification failure'));
        try {
            app(FeedbackService::class)->create($student, $this->payload());
            $this->fail('Expected rollback');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic notification failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('feedback_conversations', 0);
        $this->assertDatabaseCount('feedback_events', 0);
        $this->assertDatabaseCount('feedback_operations', 0);
    }

    public function test_merge_does_not_turn_the_students_own_history_into_incoming_activity(): void
    {
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $admin = AdministratorFactory::new()->create();
        $conversation = $this->conversation($secondary);
        app(StudentMergeService::class)->merge($primary->id, $secondary->id, $admin->id);

        $this->assertFalse((bool) app(FeedbackReadModel::class)->conversations($primary)->sole()->has_unread);
        app(FeedbackService::class)->mutate($admin, $conversation->id, $this->action($conversation, 'reply'));
        $this->assertTrue((bool) app(FeedbackReadModel::class)->conversations($primary)->sole()->has_unread);
        $this->assertDatabaseCount('feedback_read_states', 0);
    }

    public function test_http_failure_hides_private_exception_details_and_rolls_back_the_operation(): void
    {
        $this->signIn(Student::factory()->verified()->create());
        $this->mock(FeedbackNotifications::class)->shouldReceive('send')->once()->andThrow(new \RuntimeException('PRIVATE SQL CONTENT SENTINEL'));
        Log::shouldReceive('error')->once()->with('Feedback request failed.', ['exception_type' => \RuntimeException::class]);

        $this->postJson(route('student.feedback.store'), $this->payload())
            ->assertStatus(500)
            ->assertJsonPath('message', __('feedback.failed'))
            ->assertDontSee('PRIVATE SQL CONTENT SENTINEL');
        $this->assertDatabaseCount('feedback_conversations', 0);
        $this->assertDatabaseCount('feedback_operations', 0);
    }

    public function test_admin_inbox_failure_keeps_a_themed_recoverable_page_without_private_details(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(), 'web');
        $this->mock(FeedbackReadModel::class)->shouldReceive('filters')->once()->andThrow(new \RuntimeException('PRIVATE DATABASE DETAILS'));
        Log::shouldReceive('error')->once()->with('Feedback request failed.', ['exception_type' => \RuntimeException::class]);

        $this->get(route('admin.feedback.index'))
            ->assertServerError()
            ->assertViewIs('errors.feedback')
            ->assertSee('data-admin-appearance-init', false)
            ->assertSee(__('feedback.load_failed'))
            ->assertSee(route('admin.dashboard'))
            ->assertDontSee('PRIVATE DATABASE DETAILS')
            ->assertDontSee(__('feedback.failed'));
    }

    public function test_guest_admin_feedback_requests_keep_the_normal_login_and_json_authentication_responses(): void
    {
        $this->get(route('admin.feedback.index'))->assertRedirect(route('admin.login'));
        $this->getJson(route('admin.feedback.index'))->assertUnauthorized();
    }

    #[DataProvider('locales')]
    public function test_unavailable_student_thread_renders_a_localized_page_with_navigation(string $locale, string $heading): void
    {
        $this->signIn(Student::factory()->verified()->create())->withSession(['public_locale' => $locale]);

        $this->get(route('student.feedback.show', 999999))
            ->assertNotFound()
            ->assertViewIs('errors.feedback')
            ->assertSee(trans('feedback.workspace', [], $locale))
            ->assertSee(route('student.home'))
            ->assertSee(route('student.feedback.index'))
            ->assertDontSee('No query results')
            ->assertDontSee('App\\Domains\\Students\\Models\\FeedbackConversation')
            ->assertDontSee('data-admin-appearance-init', false);
    }

    public function test_restriction_after_loading_blocks_staff_and_student_writes_and_deleted_admin_access(): void
    {
        $student = Student::factory()->verified()->create();
        $conversation = $this->conversation($student);
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->actingAs($admin, 'web');
        $this->get(route('admin.feedback.show', $conversation->id))->assertOk();
        $student->update(['suspended_at' => now()]);
        $this->postJson(route('admin.feedback.update', $conversation->id), $this->action($conversation, 'reply'))->assertNotFound();
        $student->update(['suspended_at' => null]);
        $admin->delete();
        $this->get(route('admin.feedback.index'))->assertRedirect(route('admin.login'));
        $this->assertDatabaseCount('feedback_events', 1);
    }

    #[DataProvider('locales')]
    public function test_student_pages_localize_escape_authored_text_and_preserve_navigation(string $locale, string $heading): void
    {
        $student = Student::factory()->verified()->create();
        $conversation = $this->conversation($student);
        $conversation->update(['subject' => '<script>alert("private")</script>']);
        $this->signIn($student)->withSession(['public_locale' => $locale]);
        $this->get(route('student.feedback.index'))->assertSee($heading)->assertSee(trans('Practice & Feedback', [], $locale))->assertDontSee('<script>alert("private")</script>', false);
        $this->get(route('student.feedback.create'))->assertSee(trans('feedback.new', [], $locale));
        $this->get(route('student.feedback.show', $conversation->id))->assertSee('&lt;script&gt;', false);
        $this->assertSame('/student/feedback/'.$conversation->id, app(StudentIntendedDestination::class)->safeRelativeUrl('/student/feedback/'.$conversation->id));
        $this->assertNull(app(StudentIntendedDestination::class)->safeRelativeUrl('//evil.test/student/feedback'));
    }

    public static function locales(): array
    {
        return [['en', 'My Feedback'], ['fr', 'Mes retours'], ['de', 'Meine Rückmeldungen']];
    }

    #[DataProvider('locales')]
    public function test_feedback_messages_are_available_in_each_language_with_the_same_placeholders(string $locale, string $heading): void
    {
        $english = Arr::dot(require lang_path('en/feedback.php'));
        $translated = Arr::dot(require lang_path($locale.'/feedback.php'));
        $this->assertSame(array_keys($english), array_keys($translated));

        foreach ($english as $key => $source) {
            $this->assertTrue(app('translator')->hasForLocale('feedback.'.$key, $locale), $locale.': '.$key);
            $message = trans('feedback.'.$key, [], $locale);
            $this->assertIsString($message);
            $this->assertNotSame('', trim($message));
            preg_match_all('/:[a-z_]+/i', $source, $expected);
            preg_match_all('/:[a-z_]+/i', $message, $actual);
            sort($expected[0]);
            sort($actual[0]);
            $this->assertSame($expected[0], $actual[0], $locale.': '.$key);
        }

        $this->assertSame($heading, trans('feedback.title', [], $locale));
    }

    #[DataProvider('localizedRequestErrors')]
    public function test_framework_feedback_errors_use_localized_recovery_messages(string $locale, int $status, string $key): void
    {
        $this->signIn(Student::factory()->verified()->create())->withSession(['public_locale' => $locale]);
        $this->mock(FeedbackReadModel::class)->shouldReceive('filters')->once()
            ->andThrow(new HttpException($status, 'Untranslated framework error', headers: ['Retry-After' => '60']));

        $this->getJson(route('student.feedback.index'))
            ->assertStatus($status)
            ->assertJsonPath('message', trans('feedback.'.$key, [], $locale))
            ->assertHeader('Retry-After', '60')
            ->assertDontSee('Untranslated framework error');
    }

    public static function localizedRequestErrors(): array
    {
        $cases = [];
        foreach (['en', 'fr', 'de'] as $locale) {
            foreach ([401 => 'session_expired', 403 => 'unavailable', 404 => 'unavailable', 419 => 'session_expired', 429 => 'rate_limit', 500 => 'failed'] as $status => $key) {
                $cases[$locale.' '.$status] = [$locale, $status, $key];
            }
        }

        return $cases;
    }

    #[DataProvider('locales')]
    public function test_feedback_auxiliary_validation_names_fields_in_the_selected_language(string $locale, string $heading): void
    {
        $student = Student::factory()->verified()->create();
        $conversation = $this->conversation($student);
        $this->signIn($student)->withSession(['public_locale' => $locale]);

        $receipt = $this->postJson(route('student.feedback.read', $conversation->id), []);
        $receipt->assertUnprocessable()->assertJsonValidationErrors('receipt');
        $this->assertStringContainsString(trans('feedback.history', [], $locale), $receipt->json('errors.receipt.0'));
        $segment = $this->getJson(route('student.feedback.show', [$conversation->id, 'segment' => 'invalid']));
        $segment->assertUnprocessable()->assertJsonValidationErrors('segment');
        $this->assertStringContainsString(trans('feedback.history', [], $locale), $segment->json('errors.segment.0'));
        $this->assertDatabaseCount('feedback_read_states', 0);
    }

    #[DataProvider('locales')]
    public function test_feedback_forms_keep_their_display_language_and_use_localized_validation(string $locale, string $heading): void
    {
        $this->signIn(Student::factory()->verified()->create())->withSession(['public_locale' => $locale]);
        $this->get(route('student.feedback.create'))->assertSee('data-feedback-locale="'.$locale.'"', false)->assertSee('novalidate', false);

        $this->withSession(['public_locale' => $locale === 'de' ? 'fr' : 'de']);
        $this->postJson(route('student.feedback.store', ['lang' => $locale]), $this->payload(['body' => '']))
            ->assertUnprocessable()
            ->assertJsonPath('message', trans('feedback.validation_failed', [], $locale))
            ->assertJsonPath('errors.body.0', trans('feedback.required', [], $locale));
        $this->assertDatabaseCount('feedback_conversations', 0);
        $this->assertDatabaseCount('feedback_operations', 0);
    }

    #[DataProvider('locales')]
    public function test_expired_feedback_proof_uses_the_language_of_the_open_form(string $locale, string $heading): void
    {
        $this->signIn(Student::factory()->verified()->create())
            ->withSession(['public_locale' => $locale === 'de' ? 'fr' : 'de'])
            ->withHeader('X-Feedback-Session', 'expired-proof');

        $this->postJson(route('student.feedback.store', ['lang' => $locale]), $this->payload())
            ->assertUnauthorized()
            ->assertJsonPath('message', trans('feedback.session_expired', [], $locale));
        $this->assertDatabaseCount('feedback_conversations', 0);
    }

    #[DataProvider('locales')]
    public function test_feedback_status_history_success_and_notifications_follow_the_students_language(string $locale, string $heading): void
    {
        $student = Student::factory()->verified()->create(['first_name' => 'Amina']);
        $conversation = $this->conversation($student);
        $administrator = AdministratorFactory::new()->create(['name' => 'Synthetic staff name']);
        app(FeedbackService::class)->mutate($administrator, $conversation->id, $this->action($conversation, 'clarify', ['body' => 'Original tutor reply — مرحباً.']));
        $notification = StudentNotification::query()->where('student_id', $student->id)->sole();
        $this->signIn($student)->withSession(['public_locale' => $locale]);

        $this->get(route('student.feedback.show', $conversation->id))
            ->assertSeeText(trans('feedback.waiting_student', [], $locale))
            ->assertSeeText('Amina')->assertSeeText('Abdallah')->assertSeeText('Original tutor reply — مرحباً.');
        $expected = [
            'en' => ['Feedback updated', 'Your tutor has updated your feedback conversation.'],
            'fr' => ['Retour mis à jour', 'Votre tuteur a mis à jour votre conversation.'],
            'de' => ['Rückmeldung aktualisiert', 'Ihr Tutor hat die Unterhaltung zu Ihrer Rückmeldung aktualisiert.'],
        ][$locale];
        $this->get(route('student.notifications.index'))->assertSeeText($expected[0])->assertSeeText($expected[1])
            ->assertSeeText(['en' => 'Unread', 'fr' => 'Non lue', 'de' => 'Ungelesen'][$locale]);
        $this->get(route('student.home'))->assertSeeText($expected[0]);
        $this->assertSame('Feedback updated', $notification->fresh()->title);
        $this->assertSame('Your tutor has updated your feedback conversation.', $notification->fresh()->message);
        $this->patch(route('student.notifications.update', $notification->id))->assertRedirect(route('student.notifications.index'));
        $this->get(route('student.notifications.index'))
            ->assertSeeText(['en' => 'Read', 'fr' => 'Lue', 'de' => 'Gelesen'][$locale]);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertDatabaseCount('feedback_read_states', 0);

        $response = $this->postJson(route('student.feedback.update', ['conversation' => $conversation->id, 'lang' => $locale]), $this->action($conversation, 'reply', ['body' => 'Original student reply — merci, danke.']))
            ->assertOk()
            ->assertJsonPath('redirect', route('student.feedback.show', ['conversation' => $conversation->id, 'lang' => $locale]));
        $this->get($response->json('redirect'))->assertSeeText(trans('feedback.saved', [], $locale))->assertSeeText('Original student reply — merci, danke.');
        app(FeedbackService::class)->mutate($administrator, $conversation->id, $this->action($conversation, 'resolve'));
        app(FeedbackService::class)->mutate($administrator, $conversation->id, $this->action($conversation, 'close'));

        $this->get(route('student.feedback.show', ['conversation' => $conversation->id, 'lang' => $locale]))
            ->assertSeeText(trans('feedback.closed_notice', [], $locale))
            ->assertSeeText(trans('feedback.transition', ['from' => trans('feedback.resolved', [], $locale), 'to' => trans('feedback.closed', [], $locale)], $locale))
            ->assertDontSee('name="body"', false);
        $this->assertDatabaseHas('feedback_events', ['conversation_id' => $conversation->id, 'body' => 'Original student reply — merci, danke.']);
    }
}
