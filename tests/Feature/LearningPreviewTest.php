<?php

namespace Tests\Feature;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\AuthorizedDevice;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonNote;
use App\Domains\Lms\Models\PlaybackLease;
use App\Domains\Lms\Models\ProtectionProfile;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Lms\Models\VideoProviderConnection;
use App\Domains\Lms\Services\CourseStudioService;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsPreviewService;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LearningPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $private = false): array
    {
        $this->freezeTime();
        $this->withCredentials();
        config(['app.url' => 'http://example.test']);
        $actor = AdministratorFactory::new()->create(['role' => 'admin', 'name' => 'Preview Admin']);
        $lucy = Student::factory()->verified()->create(['first_name' => 'Lucy', 'last_name' => 'Student']);
        $course = Course::factory()->published()->create(['title' => 'Preview Arabic', 'kind' => $private ? 'private' : 'catalog', 'owner_student_id' => $private ? $lucy->id : null]);
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id, 'title' => 'Read-only practice']);
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'rich_text', 'status' => 'ready', 'payload' => ['html' => '<p>أهلاً · Preview lesson</p>']]);
        app(LmsAccessOperations::class)->assign($actor, $lucy, $course, [], 'preview-fixture-'.Str::uuid());

        return [$actor, $lucy, $course->fresh(), $lesson];
    }

    private function begin($actor, Course $course, ?Student $student = null, ?string $key = null): string
    {
        $response = $this->actingAs($actor, 'web')->post(route('admin.lms.preview.start', $course),
            ['request_key' => $key ?? (string) Str::uuid(), 'student_id' => $student?->id]);
        $response->assertRedirect();
        $id = app('session')->get(LmsPreviewService::SESSION_KEY)['id'];
        app('session')->save();
        $this->withCookie(config('session.cookie'), app('session')->getId());

        return $id;
    }

    private function snapshot(): array
    {
        $tables = ['lms_enrollments', 'lms_access_grants', 'lms_learning_assignments', 'lms_learning_visits', 'lms_lesson_notes', 'lms_lesson_bookmarks',
            'lms_lesson_progress', 'lms_video_progress', 'lms_quiz_attempts', 'lms_assignment_submissions', 'lms_authorized_devices', 'lms_playback_leases', 'student_notifications', 'analytics_events'];
        $snapshot = [];
        foreach ($tables as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }

    private function capture(string $name, string $url): void
    {
        $directory = getenv('LMS_OPERATIONS_UI_REVIEW_DIR');
        if (is_string($directory) && is_dir($directory)) {
            file_put_contents($directory.'/'.$name.'.html', $this->get($url)->assertOk()->getContent());
        }
    }

    public function test_specific_preview_persists_banner_across_admin_pages_and_respects_prerequisites(): void
    {
        [$actor,$lucy,$course,$lesson] = $this->fixture();
        $locked = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $lesson->section_id,
            'title' => 'Later practice', 'learning_rules' => ['required' => true, 'methods' => ['manual'], 'video_threshold' => 95,
                'prerequisite_key' => 'lesson:'.$lesson->id, 'drip_mode' => 'immediate', 'drip_days' => null, 'drip_at' => null]]);
        LessonBlock::factory()->create(['lesson_id' => $locked->id, 'kind' => 'rich_text', 'status' => 'ready', 'payload' => ['html' => '<p>HIDDEN_PREREQUISITE_CONTENT</p>']]);
        $before = $this->snapshot();
        $id = $this->begin($actor, $course, $lucy);
        $this->get(route('admin.lms.preview.lesson', [$id, $locked]))->assertOk()->assertSee('prerequisite lesson first')->assertDontSee('HIDDEN_PREREQUISITE_CONTENT');
        $this->get(route('admin.lms.operations'))->assertOk()->assertSee('Previewing as Lucy Student')->assertSee('Exit preview');
        $this->capture('stage8-preview-student', route('admin.lms.preview.lesson', [$id, $lesson]));
        $this->capture('stage8-preview-locked', route('admin.lms.preview.lesson', [$id, $locked]));
        $this->assertSame($before, $this->snapshot());
        $this->post(route('admin.lms.preview.exit', $id))->assertRedirect();
        $this->get(route('admin.lms.operations'))->assertOk()->assertDontSee('Previewing as Lucy Student');
    }

    public function test_private_material_preview_rechecks_ownership_and_published_access(): void
    {
        Storage::fake('local');
        [$actor,$lucy,$course,$lesson] = $this->fixture(true);
        $asset = app(CourseStudioService::class)->upload($actor, $course, UploadedFile::fake()->createWithContent('practice.pdf', "%PDF-1.4\nPrivate preview"), 'file', $course->lock_version);
        $block = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'file', 'status' => 'ready', 'asset_id' => $asset->id, 'payload' => ['label' => 'Private practice PDF']]);
        $before = $this->snapshot();
        $id = $this->begin($actor, $course, $lucy);
        $this->get(route('admin.lms.preview.material', [$id, $lesson, $block]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $foreign = LessonBlock::factory()->create();
        $this->get(route('admin.lms.preview.material', [$id, $lesson, $foreign]))->assertNotFound();
        $this->assertSame($before, $this->snapshot());
        $course->forceFill(['status' => 'unpublished'])->save();
        $this->get(route('admin.lms.preview.material', [$id, $lesson, $block]))->assertNotFound();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_preview_token_is_clipped_to_student_access_end_and_ignores_occupied_student_allowances(): void
    {
        [$actor,$lucy,$course,$lesson] = $this->fixture(true);
        $asset = $this->video($lesson, $course);
        $block = LessonBlock::query()->where('lesson_id', $lesson->id)->where('kind', 'video')->sole();
        $grant = AccessGrant::query()->where('student_id', $lucy->id)->sole();
        app(LmsAccessOperations::class)->change($actor, $grant, 'set_expiration', ['expires_at' => now('UTC')->addSeconds(30)->toIso8601String()], 1, 'short-preview-'.Str::uuid());
        $profile = ProtectionProfile::query()->where('name', 'Private')->sole();
        $devices = AuthorizedDevice::factory()->count(3)->create(['student_id' => $lucy->id]);
        PlaybackLease::factory()->create(['student_id' => $lucy->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id, 'block_id' => $block->id,
            'video_asset_id' => $asset->id, 'device_id' => $devices->first()->id, 'profile_id' => $profile->id]);
        $before = $this->snapshot();
        $id = $this->begin($actor, $course, $lucy);
        $body = ['request_key' => (string) Str::uuid(), 'lease_token' => bin2hex(random_bytes(32))];
        $response = $this->postJson(route('admin.lms.preview.video', [$id, $lesson, $block]), $body)->assertOk();
        $this->assertSame(now('UTC')->addSeconds(30)->timestamp, CarbonImmutable::parse($response->json('expires_at'))->timestamp);
        $this->assertSame($before, $this->snapshot());
        $this->travel(30)->seconds();
        $this->postJson(route('admin.lms.preview.video.renew', [$id, $lesson, $block]), $body)->assertNotFound();
        $this->assertSame($before, $this->snapshot());
    }

    public static function changedVideoPolicy(): array
    {
        return ['profile version' => ['profile', 409], 'disabled provider' => ['provider', 409], 'replaced media' => ['media', 409]];
    }

    #[DataProvider('changedVideoPolicy')]
    public function test_minted_preview_does_not_renew_after_policy_or_provider_changes(string $change, int $status): void
    {
        [$actor,$lucy,$course,$lesson] = $this->fixture(true);
        $asset = $this->video($lesson, $course);
        $block = LessonBlock::query()->where('lesson_id', $lesson->id)->where('kind', 'video')->sole();
        $id = $this->begin($actor, $course, $lucy);
        $body = ['request_key' => (string) Str::uuid(), 'lease_token' => bin2hex(random_bytes(32))];
        $this->postJson(route('admin.lms.preview.video', [$id, $lesson, $block]), $body)->assertOk();
        if ($change === 'profile') {
            ProtectionProfile::query()->where('name', 'Private')->increment('lock_version');
        }
        if ($change === 'provider') {
            $asset->connection->forceFill(['enabled' => false, 'lock_version' => 2])->save();
        }
        if ($change === 'media') {
            $replacement = VideoAsset::factory()->create(['course_id' => $course->id, 'provider_connection_id' => $asset->provider_connection_id, 'status' => 'ready', 'duration_seconds' => 300]);
            $block->forceFill(['video_asset_id' => $replacement->id])->save();
        }
        $before = $this->snapshot();
        $this->postJson(route('admin.lms.preview.video.renew', [$id, $lesson, $block]), $body)->assertStatus($status);
        $this->assertSame($before, $this->snapshot());
        $audit = AuditLog::query()->where('action', 'lms_preview_started')->sole();
        $this->assertStringNotContainsString($body['lease_token'], json_encode($audit));
        $this->assertNull($audit->ip_address);
    }

    public function test_generic_preview_is_read_only_and_keeps_real_actor_with_safe_assessment_projection(): void
    {
        [$actor,$lucy,$course,$lesson] = $this->fixture();
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'quiz', 'status' => 'ready', 'payload' => ['title' => 'Blank practice', 'passing_score' => 80, 'attempt_limit' => 3, 'review_policy' => 'after_grading',
            'questions' => [['type' => 'blank', 'prompt' => 'Write a greeting', 'points' => 1, 'accepted' => ['NEVER_DISPLAY_THIS_KEY']]]]]);
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'assignment', 'status' => 'ready', 'payload' => ['title' => 'Writing practice', 'instructions' => 'Write two Arabic sentences.', 'types' => ['text']]]);
        LessonNote::query()->forceCreate(['student_id' => $lucy->id, 'lesson_id' => $lesson->id, 'body' => 'ONLY_LUCYS_PERSONAL_NOTE']);
        $before = $this->snapshot();
        $id = $this->begin($actor, $course);
        $response = $this->get(route('admin.lms.preview.lesson', [$id, $lesson]));
        $response->assertOk()->assertSee('Previewing as a generic learner')->assertSee('Signed in as Preview Admin')->assertSee('Preview lesson')
            ->assertSee('Write a greeting')->assertSee('fieldset disabled', false)->assertDontSee('NEVER_DISPLAY_THIS_KEY')->assertDontSee('ONLY_LUCYS_PERSONAL_NOTE')
            ->assertDontSee('Submit quiz')->assertDontSee('data-watch-url', false)->assertHeader('Cache-Control', 'no-store, private');
        $this->capture('stage8-preview-generic', route('admin.lms.preview.lesson', [$id, $lesson]));
        $this->assertSame($before, $this->snapshot());
        $this->assertNull(app('session')->get('student_id'));
        $this->assertSame($actor->id, Auth::guard('web')->id());
        $this->assertSame(1, AuditLog::query()->where('action', 'lms_preview_started')->where('administrator_id', $actor->id)->count());
        $this->post(route('admin.lms.preview.exit', $id))->assertRedirect(route('admin.lms.courses.index'));
        $this->post(route('admin.lms.preview.exit', $id))->assertRedirect();
        $this->assertSame(1, AuditLog::query()->where('action', 'lms_preview_exited')->count());
        $this->get(route('admin.lms.preview.show', $id))->assertNotFound();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_specific_preview_uses_current_access_and_cannot_preview_sarah_as_owner_of_lucys_private_course(): void
    {
        [$actor,$lucy,$course,$lesson] = $this->fixture(true);
        $sarah = Student::factory()->verified()->create();
        $this->actingAs($actor, 'web')->post(route('admin.lms.preview.start', $course), ['request_key' => (string) Str::uuid(), 'student_id' => $sarah->id])->assertNotFound();
        $this->assertSame(0, AuditLog::query()->where('action', 'lms_preview_started')->count());
        $before = $this->snapshot();
        $id = $this->begin($actor, $course, $lucy);
        $this->get(route('admin.lms.preview.lesson', [$id, $lesson]))->assertOk()->assertSee('Previewing as Lucy Student')->assertSee('Preview lesson');
        $this->assertSame($before, $this->snapshot());
        $grant = AccessGrant::query()->where('student_id', $lucy->id)->sole();
        app(LmsAccessOperations::class)->change($actor, $grant, 'revoke', [], $grant->lock_version, 'preview-revoke-'.Str::uuid());
        $this->get(route('admin.lms.preview.show', $id))->assertOk()->assertSee('Learning unavailable')->assertDontSee('Preview lesson');
        $this->get(route('admin.lms.preview.lesson', [$id, $lesson]))->assertNotFound();
    }

    public function test_specific_preview_preserves_prerequisite_and_drip_locks_without_fake_enrollment(): void
    {
        [$actor,$lucy,$course,$lesson] = $this->fixture();
        $lesson->forceFill(['learning_rules' => ['required' => true, 'methods' => ['manual'], 'video_threshold' => 95, 'prerequisite_key' => null, 'drip_mode' => 'relative', 'drip_days' => 2, 'drip_at' => null]])->save();
        $before = $this->snapshot();
        $id = $this->begin($actor, $course, $lucy);
        $this->get(route('admin.lms.preview.lesson', [$id, $lesson]))->assertOk()->assertSee('scheduled for later')->assertDontSee('Preview lesson');
        $this->assertSame($before, $this->snapshot());
        $this->travel(2)->days();
        $this->post(route('admin.lms.preview.exit', $id))->assertRedirect();
        $id = $this->begin($actor, $course, $lucy);
        $this->get(route('admin.lms.preview.lesson', [$id, $lesson]))->assertOk()->assertSee('Preview lesson');
        $this->assertSame($before, $this->snapshot());
    }

    public function test_preview_begin_retries_and_context_tampering_remain_actor_session_target_bound(): void
    {
        [$actor,$lucy,$course,$lesson] = $this->fixture();
        $key = (string) Str::uuid();
        $id = $this->begin($actor, $course, $lucy, $key);
        $this->assertSame($id, $this->begin($actor, $course, $lucy, $key));
        $this->assertSame(1, AuditLog::query()->where('action', 'lms_preview_started')->count());
        $this->post(route('admin.lms.preview.start', $course), ['request_key' => $key])->assertStatus(409);
        $foreign = Lesson::factory()->published()->create();
        $this->get(route('admin.lms.preview.lesson', [$id, $foreign]))->assertNotFound();
        $this->get(route('admin.lms.preview.show', (string) Str::uuid()))->assertNotFound();
        $other = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->actingAs($other, 'web')->get(route('admin.lms.preview.show', $id))->assertNotFound();
        $this->actingAs($actor, 'web');
        app('session')->regenerate();
        app('session')->save();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $this->get(route('admin.lms.preview.show', $id))->assertNotFound();
        $replacement = $this->begin($actor, $course, $lucy);
        $this->assertNotSame($id, $replacement);
        $this->get(route('admin.lms.preview.show', $replacement))->assertOk();
        $this->assertSame(2, AuditLog::query()->where('action', 'lms_preview_started')->count());
        app('session')->regenerate();
        app('session')->save();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $this->post(route('admin.lms.preview.exit', $replacement))->assertRedirect();
        $this->assertNull(app('session')->get(LmsPreviewService::SESSION_KEY));
        $this->assertSame(0, AuditLog::query()->where('action', 'lms_preview_exited')->count());
    }

    public function test_preview_cannot_mutate_real_student_state_even_when_both_guards_are_authenticated(): void
    {
        [$actor,$lucy,$course,$lesson] = $this->fixture();
        $this->actingAs($lucy, 'student')->withSession(['student_id' => $lucy->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()]);
        app('session')->save();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $before = $this->snapshot();
        $id = $this->begin($actor, $course, $lucy);
        $this->postJson(route('student.evidence.manual', [$course, $lesson]), [])->assertForbidden();
        $this->get(route('student.learning.index'))->assertForbidden();
        $this->assertSame($before, $this->snapshot());
        $this->assertSame($lucy->id, app('session')->get('student_id'));
        $this->assertSame($actor->id, Auth::guard('web')->id());
        $this->post(route('admin.lms.preview.exit', $id))->assertRedirect();
    }

    public static function forbiddenRoles(): array
    {
        return ['assistant' => ['assistant'], 'suspended admin' => ['suspended']];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_preview_rejects_forbidden_staff_without_audit_or_context(string $role): void
    {
        [$actor,$lucy,$course] = $this->fixture();
        $actor->forceFill($role === 'suspended' ? ['suspended_at' => now('UTC')] : ['role' => $role])->save();
        $response = $this->actingAs($actor, 'web')->post(route('admin.lms.preview.start', $course), ['request_key' => (string) Str::uuid()]);
        if ($role === 'suspended') {
            $response->assertRedirect();
        } else {
            $response->assertForbidden();
        }
        $this->assertNull(app('session')->get(LmsPreviewService::SESSION_KEY));
        $this->assertSame(0, AuditLog::query()->where('action', 'lms_preview_started')->count());
    }

    public function test_context_expiry_and_new_role_revoke_read_and_play_authority(): void
    {
        [$actor,$lucy,$course] = $this->fixture();
        $id = $this->begin($actor, $course, $lucy);
        $this->travel(16)->minutes();
        $this->get(route('admin.lms.preview.show', $id))->assertNotFound();
        $this->travelBack();
        $actor->forceFill(['role' => 'assistant'])->save();
        $this->get(route('admin.lms.preview.show', $id))->assertForbidden();
    }

    private function video(Lesson $lesson, Course $course, int $status = 4): VideoAsset
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $connection = VideoProviderConnection::factory()->create(['enabled' => true, 'allowed_domains' => ['example.test'], 'api_key' => 'fixture-api-key-123456', 'signing_key' => 'fixture-sign-key-123456',
            'account_key' => 'fixture-account-key-123456', 'read_only_key' => 'fixture-read-key-123456']);
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'provider_connection_id' => $connection->id, 'status' => 'ready', 'duration_seconds' => 300]);
        Http::fake(['api.bunny.net/videolibrary/123' => Http::response(['Id' => 123, 'PullZoneId' => 456, 'PlayerTokenAuthenticationEnabled' => true, 'BlockNoneReferrer' => true, 'EnableMP4Fallback' => false,
            'ExposeOriginals' => false, 'AllowDirectPlay' => false, 'AllowEarlyPlay' => false, 'EnableDRM' => false, 'AllowedReferrers' => ['example.test']]),
            'api.bunny.net/pullzone/456' => Http::response(['Id' => 456, 'Enabled' => true, 'Suspended' => false, 'ZoneSecurityEnabled' => true, 'ZoneSecurityIncludeHashRemoteIP' => false,
                'ZoneSecurityKey' => 'fixture-sign-key-123456', 'Hostnames' => [['Value' => 'test-library.b-cdn.net', 'ForceSSL' => true]], 'BlockNoneReferrer' => true, 'AllowedReferrers' => ['example.test'], 'EdgeRules' => [],
                'EdgeScriptId' => null, 'MiddlewareScriptId' => null, 'EnableAccessControlOriginHeader' => true, 'AccessControlOriginHeaderExtensions' => ['*']]),
            'video.bunnycdn.com/library/123/videos/'.$asset->provider_video_id => Http::response(['guid' => $asset->provider_video_id, 'videoLibraryId' => 123, 'status' => $status, 'length' => 300, 'hasMP4Fallback' => false])]);
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'video', 'status' => 'ready', 'video_asset_id' => $asset->id, 'payload' => null]);

        return $asset;
    }

    public function test_bunny_preview_uses_secure_short_provider_access_without_student_devices_streams_or_progress(): void
    {
        [$actor,$lucy,$course,$lesson] = $this->fixture(true);
        $asset = $this->video($lesson, $course);
        $block = LessonBlock::query()->where('lesson_id', $lesson->id)->where('kind', 'video')->sole();
        $before = $this->snapshot();
        $id = $this->begin($actor, $course, $lucy);
        $this->get(route('admin.lms.preview.lesson', [$id, $lesson]))->assertOk()->assertSee('data-protected-player', false)->assertDontSee('data-watch-url', false)
            ->assertDontSee($asset->provider_video_id)->assertDontSee('fixture-sign-key');
        Http::assertNothingSent();
        $this->capture('stage8-preview-video', route('admin.lms.preview.lesson', [$id, $lesson]));
        $body = ['request_key' => (string) Str::uuid(), 'lease_token' => bin2hex(random_bytes(32))];
        $result = $this->postJson(route('admin.lms.preview.video', [$id, $lesson, $block]), $body)->assertOk()->assertJsonPath('lease_id', 0)->assertJsonPath('watermark', 'STAFF PREVIEW · '.strtoupper(substr(hash('sha256', $id), 0, 8)));
        $this->assertStringContainsString('/bcdn_token=HS256-', $result->json('url'));
        $this->assertSame(120, CarbonImmutable::parse($result->json('expires_at'))->timestamp - now('UTC')->timestamp);
        $this->assertSame($before, $this->snapshot());
        Http::assertSentCount(3);
        $this->postJson(route('admin.lms.preview.video.renew', [$id, $lesson, $block]), $body)->assertOk();
        $this->postJson(route('admin.lms.preview.video.close', [$id, $block]), ['lease_token' => $body['lease_token']])->assertNoContent();
        $this->postJson(route('admin.lms.preview.video.renew', [$id, $lesson, $block]), $body)->assertStatus(409);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_bunny_preview_rechecks_actual_student_access_and_does_not_accept_foreign_media(): void
    {
        [$actor,$lucy,$course,$lesson] = $this->fixture(true);
        $asset = $this->video($lesson, $course);
        $block = LessonBlock::query()->where('lesson_id', $lesson->id)->where('kind', 'video')->sole();
        $id = $this->begin($actor, $course, $lucy);
        $body = ['request_key' => (string) Str::uuid(), 'lease_token' => bin2hex(random_bytes(32))];
        $this->postJson(route('admin.lms.preview.video', [$id, $lesson, $block]), $body)->assertOk();
        $foreign = LessonBlock::factory()->create();
        $this->postJson(route('admin.lms.preview.video', [$id, $lesson, $foreign]), $body)->assertNotFound();
        $grant = AccessGrant::query()->where('student_id', $lucy->id)->sole();
        app(LmsAccessOperations::class)->change($actor, $grant, 'revoke', [], $grant->lock_version, 'preview-revoke-'.Str::uuid());
        $this->postJson(route('admin.lms.preview.video.renew', [$id, $lesson, $block]), $body)->assertNotFound();
    }

    public function test_bunny_processing_and_protection_change_fail_closed_without_student_side_effects(): void
    {
        [$actor,$lucy,$course,$lesson] = $this->fixture(true);
        $this->video($lesson, $course, 2);
        $block = LessonBlock::query()->where('lesson_id', $lesson->id)->where('kind', 'video')->sole();
        $before = $this->snapshot();
        $id = $this->begin($actor, $course, $lucy);
        $body = ['request_key' => (string) Str::uuid(), 'lease_token' => bin2hex(random_bytes(32))];
        $this->postJson(route('admin.lms.preview.video', [$id, $lesson, $block]), $body)->assertStatus(503);
        $this->assertSame($before, $this->snapshot());
    }
}
