<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Lms\Models\AuthorizedDevice;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\ProtectionProfile;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Lms\Models\VideoProviderConnection;
use App\Domains\Lms\Services\CourseStudioService;
use App\Domains\Lms\Services\LmsContentService;
use App\Domains\Lms\Services\LmsVideoProfiles;
use App\Domains\Lms\Services\LmsVideoService;
use App\Domains\Students\Models\Student;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LmsVideoStudioTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEO = '657bb740-a71b-4529-a012-528021c31a92';

    /** @return array{Administrator,Course,VideoProviderConnection} */
    private function fixture(): array
    {
        Http::preventStrayRequests();
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $course = app(CourseStudioService::class)->create($actor, ['title' => 'Media authoring', 'slug' => 'media-authoring', 'kind' => 'catalog']);
        $connection = VideoProviderConnection::factory()->create(['enabled' => true, 'api_key' => 'fixture-api-key-123456', 'read_only_key' => 'fixture-callback-key-123456']);

        return [$actor, $course, $connection];
    }

    public function test_upload_creates_one_owned_resumable_attempt_and_keeps_secrets_out_of_response_audit_and_page(): void
    {
        [$actor,$course] = $this->fixture();
        Http::fake(['video.bunnycdn.com/library/123/videos' => Http::response(['guid' => self::VIDEO])]);
        $data = ['label' => 'Teacher video', 'request_key' => (string) Str::uuid(), 'version' => $course->lock_version];
        $first = $this->actingAs($actor)->postJson(route('admin.lms.videos.store', $course), $data)->assertOk()->assertJsonPath('status', 'uploading');
        $this->postJson(route('admin.lms.videos.store', $course), $data)->assertOk()->assertJsonPath('asset_id', $first->json('asset_id'));
        Http::assertSentCount(1);
        $this->assertDatabaseCount('lms_video_assets', 1);
        $this->postJson($first->json('authorization_url'))->assertOk()->assertJsonPath('endpoint', 'https://video.bunnycdn.com/tusupload')->assertDontSee('fixture-api-key');
        $this->get(route('admin.lms.courses.edit', $course))->assertOk()->assertSee('Protected video media')->assertDontSee('fixture-api-key')->assertDontSee(self::VIDEO);
        $this->assertStringNotContainsString('fixture-api-key', DB::table('audit_logs')->get()->toJson());
        Http::assertSent(fn ($request) => $request['title'] === 'LMS media '.$first->json('asset_id'));
    }

    public function test_failed_create_is_unconfirmed_and_retry_never_creates_duplicate_remote_video(): void
    {
        [$actor,$course] = $this->fixture();
        Http::fake(['video.bunnycdn.com/library/123/videos' => Http::failedConnection()]);
        $data = ['label' => 'Interrupted video', 'request_key' => (string) Str::uuid(), 'version' => $course->lock_version];
        $this->actingAs($actor)->postJson(route('admin.lms.videos.store', $course), $data)->assertStatus(503)->assertDontSee('AccessKey');
        $asset = VideoAsset::query()->firstOrFail();
        $this->assertSame('failed', $asset->status);
        $this->assertSame('create_unconfirmed', $asset->failure_code);
        $this->postJson(route('admin.lms.videos.store', $course), $data)->assertConflict();
        Http::assertSentCount(1);
    }

    public static function providerStates(): iterable
    {
        yield 'created' => [0, 'uploading'];
        yield 'uploaded' => [1, 'processing'];
        yield 'transcoding' => [3, 'processing'];
        yield 'finished' => [4, 'ready'];
        yield 'provider error' => [5, 'failed'];
        yield 'failed upload' => [6, 'failed'];
        yield 'jit playlists' => [8, 'processing'];
    }

    #[DataProvider('providerStates')]
    public function test_provider_get_state_not_upload_completion_or_callback_hint_controls_readiness(int $providerStatus, string $expected): void
    {
        [$actor,$course,$connection] = $this->fixture();
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'provider_connection_id' => $connection->id, 'provider_video_id' => self::VIDEO]);
        Http::fake(['video.bunnycdn.com/library/123/videos/'.self::VIDEO => Http::response(['guid' => self::VIDEO, 'videoLibraryId' => 123, 'status' => $providerStatus, 'length' => 300, 'hasMP4Fallback' => false])]);
        $this->actingAs($actor)->postJson(route('admin.lms.videos.status', [$course, $asset]))->assertOk()->assertJsonPath('status', $expected)->assertJsonPath('referenced', false);
        $this->assertSame($expected, $asset->fresh()->status);
    }

    public function test_webhook_authenticates_exact_bytes_known_library_and_guid_and_reconciles_replays(): void
    {
        [$actor,$course,$connection] = $this->fixture();
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'provider_connection_id' => $connection->id, 'provider_video_id' => self::VIDEO]);
        Http::fake(['video.bunnycdn.com/library/123/videos/'.self::VIDEO => Http::response(['guid' => self::VIDEO, 'videoLibraryId' => 123, 'status' => 2, 'length' => 0, 'hasMP4Fallback' => false])]);
        $body = json_encode(['VideoLibraryId' => 123, 'VideoGuid' => self::VIDEO, 'Status' => 3], JSON_THROW_ON_ERROR);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_BUNNYSTREAM_SIGNATURE_VERSION' => 'v1', 'HTTP_X_BUNNYSTREAM_SIGNATURE_ALGORITHM' => 'hmac-sha256', 'HTTP_X_BUNNYSTREAM_SIGNATURE' => hash_hmac('sha256', $body, $connection->read_only_key)];
        $this->call('POST', route('bunny-stream.webhook'), [], [], [], $headers, $body)->assertNoContent();
        $this->assertSame('processing', $asset->fresh()->status);
        $this->assertDatabaseCount('lms_video_webhook_receipts', 1);
        $this->call('POST', route('bunny-stream.webhook'), [], [], [], $headers, $body)->assertNoContent();
        Http::assertSentCount(1);
        $this->call('POST', route('bunny-stream.webhook'), [], [], [], $headers, $body.' ')->assertUnauthorized();
        $headers['HTTP_X_BUNNYSTREAM_SIGNATURE'] = str_repeat('0', 64);
        $this->call('POST', route('bunny-stream.webhook'), [], [], [], $headers, $body)->assertUnauthorized();
        $this->assertDatabaseCount('lms_video_webhook_receipts', 1);
        $this->assertSame('processing', $asset->fresh()->status);
        $this->assertStringNotContainsString(self::VIDEO, DB::table('lms_video_webhook_receipts')->get()->toJson());
    }

    public function test_out_of_order_signed_callbacks_use_fresh_provider_get_and_never_regress_from_callback_status(): void
    {
        [$actor,$course,$connection] = $this->fixture();
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'provider_connection_id' => $connection->id, 'provider_video_id' => self::VIDEO]);
        Http::fake(['video.bunnycdn.com/library/123/videos/'.self::VIDEO => Http::response(['guid' => self::VIDEO, 'videoLibraryId' => 123, 'status' => 4, 'length' => 300, 'hasMP4Fallback' => false])]);
        foreach ([3, 1, 4, 1] as $hint) {
            $raw = json_encode(['VideoLibraryId' => 123, 'VideoGuid' => self::VIDEO, 'Status' => $hint], JSON_THROW_ON_ERROR);
            app(LmsVideoService::class)->webhook($connection, $raw, 'v1', 'hmac-sha256', hash_hmac('sha256', $raw, $connection->read_only_key));
            $this->assertSame('ready', $asset->fresh()->status);
        }
        $this->assertDatabaseCount('lms_video_webhook_receipts', 3);
        Http::assertSentCount(3);
    }

    public function test_foreign_nested_course_upload_and_media_status_ids_are_denied_and_assistant_cannot_author(): void
    {
        [$actor,$course,$connection] = $this->fixture();
        $other = Course::factory()->create();
        $asset = VideoAsset::factory()->create(['course_id' => $other->id, 'provider_connection_id' => $connection->id]);
        $this->actingAs($actor)->postJson(route('admin.lms.videos.upload', [$course, $asset]))->assertNotFound();
        $this->postJson(route('admin.lms.videos.status', [$course, $asset]))->assertNotFound();
        $assistant = AdministratorFactory::new()->create(['role' => 'assistant']);
        $this->actingAs($assistant)->postJson(route('admin.lms.videos.store', $course), ['version' => 1, 'label' => 'No', 'request_key' => (string) Str::uuid()])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_local_orphan_can_be_deleted_remotely_but_shared_live_draft_and_retained_revisions_pin_media(): void
    {
        [$actor,$course,$connection] = $this->fixture();
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'provider_connection_id' => $connection->id, 'provider_video_id' => self::VIDEO, 'status' => 'ready', 'duration_seconds' => 300]);
        Http::fake(['video.bunnycdn.com/library/123/videos/'.self::VIDEO => Http::response('', 404)]);
        $studio = app(CourseStudioService::class);
        $course = $studio->write($actor, $course, 'add_section', ['title' => 'Section', 'status' => 'published'], $course->lock_version);
        $section = $studio->draft($actor, $course)['sections'][0];
        $course = $studio->write($actor, $course, 'add_lesson', ['parent_key' => $section['key'], 'title' => 'Lesson', 'status' => 'published'], $course->lock_version);
        $lesson = $studio->draft($actor, $course)['sections'][0]['lessons'][0];
        $course = $studio->write($actor, $course, 'add_block', ['parent_key' => $lesson['key'], 'kind' => 'video', 'video_asset_id' => $asset->id], $course->lock_version);
        $this->actingAs($actor)->delete(route('admin.lms.videos.destroy', [$course, $asset]))->assertConflict();
        Http::assertNothingSent();
        $course = $studio->publish($actor, $course, $course->lock_version);
        $block = $studio->draft($actor, $course)['sections'][0]['lessons'][0]['blocks'][0];
        $course = $studio->write($actor, $course, 'remove_block', ['key' => $block['key']], $course->lock_version);
        $this->delete(route('admin.lms.videos.destroy', [$course, $asset]))->assertConflict();
        $orphan = VideoAsset::factory()->create(['course_id' => $course->id, 'provider_connection_id' => $connection->id]);
        Http::fake(['video.bunnycdn.com/library/123/videos/'.$orphan->provider_video_id => Http::response('', 404)]);
        $this->assertFalse(app(LmsVideoService::class)->hasReferences($orphan));
        $this->delete(route('admin.lms.videos.destroy', [$course, $orphan]))->assertRedirect();
        $this->assertSame('deleted', $orphan->fresh()->status);
        $this->assertSame('ready', $asset->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_ready_unreferenced_remote_delete_handles_provider_failure_without_claiming_success(): void
    {
        [$actor,$course,$connection] = $this->fixture();
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'provider_connection_id' => $connection->id, 'provider_video_id' => self::VIDEO]);
        Http::fake(['video.bunnycdn.com/library/123/videos/'.self::VIDEO => Http::response('', 500)]);
        $this->actingAs($actor)->deleteJson(route('admin.lms.videos.destroy', [$course, $asset]))->assertStatus(503);
        $this->assertSame('delete_failed', $asset->fresh()->status);
        $this->assertSame('delete_unconfirmed', $asset->fresh()->failure_code);
    }

    public function test_private_owner_scope_blocks_copying_another_students_media_into_catalog_or_private_course(): void
    {
        [$actor,$course,$connection] = $this->fixture();
        $owner = Student::factory()->verified()->create();
        $private = Course::factory()->create(['kind' => 'private', 'owner_student_id' => $owner->id]);
        $asset = VideoAsset::factory()->create(['course_id' => $private->id, 'provider_connection_id' => $connection->id, 'status' => 'ready', 'duration_seconds' => 300]);
        $this->expectException(ValidationException::class);
        app(LmsContentService::class)->normalize($course, ['kind' => 'video', 'video_asset_id' => $asset->id], [], [$asset->id]);
    }

    public function test_super_only_secret_settings_and_profiles_never_render_stored_values_or_flash_submitted_keys(): void
    {
        [$actor,$course,$connection] = $this->fixture();
        $this->actingAs($actor)->get(route('admin.lms.video.settings'))->assertForbidden();
        $super = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $this->actingAs($super)->get(route('admin.lms.video.settings'))->assertOk()->assertSee('Configured — leave blank to keep')->assertDontSee('fixture-api-key');
        $this->post(route('admin.lms.video.settings.update'), ['version' => 1, 'domains' => 'example.test', 'library_id' => 123, 'cdn_hostname' => 'bad', 'enabled' => 1, 'api_key' => 'SUPER-SECRET-FIXTURE-KEY'])->assertSessionHasErrors('cdn_hostname');
        $this->assertArrayNotHasKey('api_key', session()->getOldInput());
        $profile = ProtectionProfile::query()->where('name', 'Private')->firstOrFail();
        $this->actingAs($actor)->patch(route('admin.lms.video.profiles.update', $profile), ['version' => 1])->assertForbidden();
        $this->assertNull($connection->fresh()->last_verified_at);
    }

    public function test_profile_inheritance_is_deterministic_and_only_super_can_change_draft_override(): void
    {
        [$actor,$course] = $this->fixture();
        $lesson = Lesson::factory()->create(['course_id' => $course->id, 'section_id' => Section::factory()->create(['course_id' => $course->id])->id]);
        $profiles = app(LmsVideoProfiles::class);
        $this->assertSame('Member', $profiles->effective($course, $lesson)->name);
        $premium = ProtectionProfile::query()->where('name', 'Premium')->firstOrFail();
        $private = ProtectionProfile::query()->where('name', 'Private')->firstOrFail();
        $course->forceFill(['protection_profile_id' => $premium->id])->save();
        $this->assertSame('Premium', $profiles->effective($course, $lesson)->name);
        $lesson->forceFill(['protection_profile_id' => $private->id])->save();
        $this->assertSame('Private', $profiles->effective($course, $lesson)->name);
        $this->actingAs($actor)->post(route('admin.lms.courses.update', $course), ['operation' => 'protection', 'version' => $course->lock_version, 'protection_profile_id' => $private->id])->assertForbidden();
    }

    public function test_generic_youtube_asset_has_safe_canonical_link_and_no_bunny_protection_claim(): void
    {
        [$actor,$course] = $this->fixture();
        $asset = app(LmsVideoService::class)->external($actor, $course, ['provider' => 'youtube', 'label' => 'Public pronunciation', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'request_key' => (string) Str::uuid()], $course->lock_version);
        $this->assertSame('youtube', $asset->provider);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $asset->external_url);
        $this->assertSame($asset->id, app(LmsContentService::class)->normalize($course, ['kind' => 'video', 'video_asset_id' => $asset->id])['video_asset_id']);
        Http::assertNothingSent();
    }

    public function test_staff_device_management_scopes_student_and_assistant_is_denied(): void
    {
        [$actor] = $this->fixture();
        $a = Student::factory()->verified()->create();
        $b = Student::factory()->verified()->create();
        $device = AuthorizedDevice::factory()->create(['student_id' => $a->id]);
        $this->actingAs($actor)->get(route('admin.lms.video.devices', $a))->assertOk()->assertSee($device->label);
        $this->delete(route('admin.lms.video.devices.revoke', [$b, $device]))->assertNotFound();
        $this->delete(route('admin.lms.video.devices.revoke', [$a, $device]))->assertRedirect();
        $this->assertSame('revoked', $device->fresh()->status);
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']))->get(route('admin.lms.video.devices', $a))->assertForbidden();
    }

    public static function rejectedCallbacks(): iterable
    {
        yield 'malformed JSON' => ['{broken', 422];
        yield 'unknown library' => ['{"VideoLibraryId":999,"VideoGuid":"657bb740-a71b-4529-a012-528021c31a92","Status":3}', 404];
        yield 'unknown video' => ['{"VideoLibraryId":123,"VideoGuid":"657bb740-a71b-4529-a012-528021c31a93","Status":3}', 404];
        yield 'oversized signed body' => [str_repeat('x', 4097), 413];
    }

    #[DataProvider('rejectedCallbacks')]
    public function test_malformed_or_unknown_signed_callbacks_cannot_poll_or_change_media(string $raw, int $status): void
    {
        [$actor,$course,$connection] = $this->fixture();
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'provider_connection_id' => $connection->id, 'provider_video_id' => self::VIDEO]);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_BUNNYSTREAM_SIGNATURE_VERSION' => 'v1', 'HTTP_X_BUNNYSTREAM_SIGNATURE_ALGORITHM' => 'hmac-sha256', 'HTTP_X_BUNNYSTREAM_SIGNATURE' => hash_hmac('sha256', $raw, $connection->read_only_key)];
        $this->call('POST', route('bunny-stream.webhook'), [], [], [], $headers, $raw)->assertStatus($status);
        $this->assertSame($asset->status, $asset->fresh()->status);
        $this->assertDatabaseCount('lms_video_webhook_receipts', 0);
        Http::assertNothingSent();
    }

    public function test_unknown_remote_creation_cannot_be_reported_as_deleted(): void
    {
        [$actor,$course,$connection] = $this->fixture();
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'provider_connection_id' => $connection->id, 'provider_video_id' => null, 'status' => 'failed', 'failure_code' => 'create_unconfirmed']);
        $this->actingAs($actor)->deleteJson(route('admin.lms.videos.destroy', [$course, $asset]))->assertConflict();
        $this->assertSame('failed', $asset->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_super_profile_draft_override_publishes_and_inactive_profile_prevents_republication(): void
    {
        [$actor,$course] = $this->fixture();
        $super = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $profile = ProtectionProfile::query()->where('name', 'Premium')->firstOrFail();
        $studio = app(CourseStudioService::class);
        $course = $studio->write($super, $course, 'protection', ['protection_profile_id' => $profile->id], $course->lock_version);
        $this->assertNull($course->protection_profile_id);
        $course = $studio->write($actor, $course, 'add_section', ['title' => 'Section', 'status' => 'published'], $course->lock_version);
        $section = $studio->draft($actor, $course)['sections'][0];
        $course = $studio->write($actor, $course, 'add_lesson', ['parent_key' => $section['key'], 'title' => 'Lesson', 'status' => 'published'], $course->lock_version);
        $lesson = $studio->draft($actor, $course)['sections'][0]['lessons'][0];
        $course = $studio->write($actor, $course, 'add_block', ['parent_key' => $lesson['key'], 'kind' => 'rich_text', 'html' => '<p>Lesson</p>'], $course->lock_version);
        $course = $studio->publish($actor, $course, $course->lock_version);
        $this->assertSame($profile->id, $course->protection_profile_id);
        $profile->forceFill(['active' => false])->save();
        $this->expectException(ValidationException::class);
        $studio->publish($actor, $course, $course->lock_version);
    }
}
