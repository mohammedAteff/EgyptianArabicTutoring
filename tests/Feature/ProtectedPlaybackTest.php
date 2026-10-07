<?php

namespace Tests\Feature;

use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\AuthorizedDevice;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\PlaybackLease;
use App\Domains\Lms\Models\ProtectionProfile;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Lms\Models\VideoProviderConnection;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\VideoDeviceService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProtectedPlaybackTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Student,Course,Lesson,LessonBlock,VideoAsset,AccessGrant} */
    private function fixture(): array
    {
        $this->withCredentials();
        $this->freezeTime();
        config(['app.url' => 'http://example.test']);
        $student = Student::factory()->verified()->create();
        $course = Course::factory()->published()->create(['kind' => 'private', 'owner_student_id' => $student->id]);
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id]);
        $connection = VideoProviderConnection::factory()->create(['enabled' => true, 'allowed_domains' => ['example.test'], 'api_key' => 'fixture-api-key-123456',
            'signing_key' => 'fixture-sign-key-123456', 'account_key' => 'fixture-account-key-123456', 'read_only_key' => 'fixture-read-key-123456']);
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'provider_connection_id' => $connection->id, 'status' => 'ready', 'duration_seconds' => 300]);
        $block = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'video', 'status' => 'ready', 'video_asset_id' => $asset->id, 'payload' => null]);
        $grant = app(LmsAccessOperations::class)->grant(AdministratorFactory::new()->create(), $student, $course, [], 'video-'.Str::uuid());
        $this->fakeProvider($asset);

        return [$student, $course, $lesson, $block, $asset, $grant];
    }

    private function fakeProvider(VideoAsset $asset, int $status = 4): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.bunny.net/videolibrary/123' => Http::response(['Id' => 123, 'PullZoneId' => 456, 'PlayerTokenAuthenticationEnabled' => true, 'BlockNoneReferrer' => true,
            'EnableMP4Fallback' => false, 'ExposeOriginals' => false, 'AllowDirectPlay' => false, 'AllowEarlyPlay' => false, 'EnableDRM' => false, 'AllowedReferrers' => ['example.test']]),
            'api.bunny.net/pullzone/456' => Http::response(['Id' => 456, 'Enabled' => true, 'Suspended' => false, 'ZoneSecurityEnabled' => true, 'ZoneSecurityIncludeHashRemoteIP' => false,
                'ZoneSecurityKey' => 'fixture-sign-key-123456', 'Hostnames' => [['Value' => 'test-library.b-cdn.net', 'ForceSSL' => true]], 'BlockNoneReferrer' => true, 'AllowedReferrers' => ['example.test'], 'EdgeRules' => [],
                'EdgeScriptId' => null, 'MiddlewareScriptId' => null, 'EnableAccessControlOriginHeader' => true, 'AccessControlOriginHeaderExtensions' => ['*']]),
            'video.bunnycdn.com/library/123/videos/'.$asset->provider_video_id => Http::response(['guid' => $asset->provider_video_id, 'videoLibraryId' => 123, 'status' => $status, 'length' => 300, 'hasMP4Fallback' => false])]);
    }

    private function signIn(Student $student, ?int $deadline = null): static
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addSeconds($deadline ?? 3600)->toIso8601String()]);
        app('session')->save();

        return $this->withCookie(config('session.cookie'), app('session')->getId());
    }

    private function device(Student $student): AuthorizedDevice
    {
        $token = bin2hex(random_bytes(32));
        $device = AuthorizedDevice::factory()->create(['student_id' => $student->id, 'token_hash' => hash('sha256', $token)]);
        $this->withCookie(VideoDeviceService::COOKIE, $token);

        return $device;
    }

    private function body(): array
    {
        return ['request_key' => (string) Str::uuid(), 'lease_token' => bin2hex(random_bytes(32))];
    }

    public function test_initial_page_and_json_contain_no_provider_identity_or_playback_capability(): void
    {
        [$student, $course, $lesson, $block, $asset] = $this->fixture();
        $this->signIn($student)->get(route('student.learning.lessons.show', [$course, $lesson]))->assertOk()->assertSee('Play video')->assertSee('data-protected-player', false)
            ->assertDontSee($asset->provider_video_id)->assertDontSee('bcdn_token=')->assertDontSee('fixture-sign-key')->assertHeader('Referrer-Policy', 'strict-origin');
        $this->getJson(route('student.lms.lessons.show', [$course, $lesson]))->assertOk()->assertJsonPath('blocks.0.authorization_url', route('student.video.authorize', [$course, $lesson, $block]))
            ->assertDontSee($asset->provider_video_id)->assertDontSee('bcdn_token=');
        $this->assertDatabaseCount('lms_playback_leases', 0);
        Http::assertNothingSent();
    }

    public function test_playback_is_narrow_short_lived_private_and_bound_to_canonical_session_and_browser(): void
    {
        [$student,$course,$lesson,$block,$asset] = $this->fixture();
        $device = $this->device($student);
        $body = $this->body();
        $response = $this->signIn($student)->postJson(route('student.video.authorize', [$course, $lesson, $block]), $body + ['watermark' => false, 'student_id' => 999]);
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('drm', 'not_configured');
        $this->assertStringContainsString('/bcdn_token=HS256-', $response->json('url'));
        $this->assertSame(now('UTC')->addSeconds(120)->toIso8601String(), $response->json('expires_at'));
        $this->assertStringContainsString('Learner ', $response->json('watermark'));
        foreach ([$student->email, $student->name, 'fixture-sign-key', $body['lease_token']] as $secret) {
            $response->assertDontSee($secret);
        }
        $lease = PlaybackLease::query()->firstOrFail();
        $this->assertSame($student->id, $lease->student_id);
        $this->assertSame($device->id, $lease->device_id);
        $this->assertSame(hash('sha256', $body['lease_token']), $lease->lease_token_hash);
        $this->assertDatabaseCount('lms_learning_visits', 0);
        $this->assertDatabaseCount('visitors', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lms_video_playback_started', 'actor_student_id' => $student->id, 'ip_address' => null]);
    }

    public function test_same_request_retries_one_lease_and_cannot_change_target_proof_or_browser(): void
    {
        [$student,$course,$lesson,$block] = $this->fixture();
        $this->device($student);
        $body = $this->body();
        $url = route('student.video.authorize', [$course, $lesson, $block]);
        $first = $this->signIn($student)->postJson($url, $body)->assertOk();
        $this->postJson($url, $body)->assertOk()->assertJsonPath('lease_id', $first->json('lease_id'));
        $this->postJson($url, array_replace($body, ['lease_token' => bin2hex(random_bytes(32))]))->assertNotFound();
        $this->assertDatabaseCount('lms_playback_leases', 1);
    }

    public function test_close_keeps_quota_until_issued_bearer_expires_then_another_player_can_start(): void
    {
        [$student,$course,$lesson,$block] = $this->fixture();
        $this->device($student);
        $body = $this->body();
        $url = route('student.video.authorize', [$course, $lesson, $block]);
        $first = $this->signIn($student)->postJson($url, $body)->assertOk();
        $this->postJson($url, $this->body())->assertConflict();
        $this->postJson($first->json('close_url'), $body)->assertOk();
        $this->postJson($url, $this->body())->assertConflict();
        $this->travel(121)->seconds();
        $this->postJson($url, $this->body())->assertOk();
        $this->assertDatabaseCount('lms_playback_leases', 2);
    }

    public function test_renewal_extends_only_active_owned_proven_lease_and_does_not_log_heartbeats(): void
    {
        [$student,$course,$lesson,$block] = $this->fixture();
        $this->device($student);
        $body = $this->body();
        $first = $this->signIn($student)->postJson(route('student.video.authorize', [$course, $lesson, $block]), $body)->assertOk();
        $count = DB::table('audit_logs')->count();
        $this->travel(30)->seconds();
        $this->postJson($first->json('renew_url'), $body)->assertOk()->assertJsonPath('expires_at', now('UTC')->addSeconds(120)->toIso8601String());
        $this->assertSame($count, DB::table('audit_logs')->count());
        $this->withSession(['student_auth_expires_at' => now('UTC')->subSecond()->toIso8601String()]);
        app('session')->save();
        $this->postJson($first->json('renew_url'), $body)->assertRedirect(route('student.login'));
    }

    public static function invalidCurrentState(): iterable
    {
        foreach (['course_draft', 'course_unpublished', 'lesson_unpublished', 'section_archived', 'block_withdrawn', 'expired', 'future', 'revoked', 'media_failed', 'media_replaced'] as $state) {
            yield $state => [$state];
        }
    }

    #[DataProvider('invalidCurrentState')]
    public function test_issue_and_renewal_recheck_all_current_access_and_publication_states(string $state): void
    {
        [$student,$course,$lesson,$block,$asset,$grant] = $this->fixture();
        $this->device($student);
        $body = $this->body();
        $first = $this->signIn($student)->postJson(route('student.video.authorize', [$course, $lesson, $block]), $body)->assertOk();
        match ($state) {
            'course_draft' => $course->forceFill(['status' => 'draft'])->save(),
            'course_unpublished' => $course->forceFill(['status' => 'unpublished'])->save(),
            'lesson_unpublished' => $lesson->forceFill(['status' => 'unpublished'])->save(),
            'section_archived' => $lesson->section->forceFill(['status' => 'archived'])->save(),
            'block_withdrawn' => $block->forceFill(['status' => 'withdrawn', 'video_asset_id' => null])->save(),
            'expired' => $grant->forceFill(['access_mode' => 'fixed', 'starts_at' => now('UTC')->subHour(), 'expires_at' => now('UTC')->subSecond()])->save(),
            'future' => $grant->forceFill(['starts_at' => now('UTC')->addHour()])->save(),
            'revoked' => $grant->forceFill(['status' => 'revoked', 'revoked_at' => now('UTC')])->save(),
            'media_failed' => $asset->forceFill(['status' => 'failed'])->save(),
            'media_replaced' => $block->forceFill(['kind' => 'rich_text', 'video_asset_id' => null, 'payload' => ['html' => '<p>Replaced</p>']])->save(),
        };
        $this->postJson($first->json('renew_url'), $body)->assertNotFound()->assertDontSee('bcdn_token=');
        $this->postJson(route('student.video.authorize', [$course, $lesson, $block]), $this->body())->assertNotFound()->assertDontSee('bcdn_token=');
    }

    public function test_student_b_cannot_use_student_a_private_media_devices_or_leases_even_with_copied_proof(): void
    {
        [$a,$course,$lesson,$block] = $this->fixture();
        $device = $this->device($a);
        $body = $this->body();
        $first = $this->signIn($a)->postJson(route('student.video.authorize', [$course, $lesson, $block]), $body)->assertOk();
        $b = Student::factory()->verified()->create();
        $this->signIn($b);
        $this->postJson(route('student.video.authorize', [$course, $lesson, $block]), $body + ['student_id' => $a->id])->assertNotFound();
        $this->postJson($first->json('renew_url'), $body)->assertNotFound();
        $this->postJson($first->json('close_url'), $body)->assertNotFound();
        $this->delete(route('student.video.devices.revoke', $device))->assertNotFound();
        $this->patch(route('student.video.devices.update', $device), ['label' => 'stolen'])->assertNotFound();
        $this->get(route('student.video.devices'))->assertOk()->assertViewHas('devices', fn ($rows) => $rows->isEmpty());
        $this->assertSame('authorized', $device->fresh()->status);
    }

    public function test_revoked_device_and_changed_policy_block_renewal_and_auto_reenrollment(): void
    {
        [$student,$course,$lesson,$block] = $this->fixture();
        $device = $this->device($student);
        $body = $this->body();
        $first = $this->signIn($student)->postJson(route('student.video.authorize', [$course, $lesson, $block]), $body)->assertOk();
        $this->delete(route('student.video.devices.revoke', $device))->assertRedirect();
        $this->postJson($first->json('renew_url'), $body)->assertForbidden();
        $this->postJson(route('student.video.authorize', [$course, $lesson, $block]), $this->body())->assertForbidden();
        $this->assertDatabaseCount('lms_authorized_devices', 1);
        $this->assertSame('revoked', PlaybackLease::query()->firstOrFail()->status);
    }

    public function test_required_drm_disabled_provider_and_remote_processing_fail_closed(): void
    {
        [$student,$course,$lesson,$block,$asset] = $this->fixture();
        $this->device($student);
        $this->signIn($student);
        $url = route('student.video.authorize', [$course, $lesson, $block]);
        $profile = ProtectionProfile::query()->where('name', 'Private')->firstOrFail();
        $profile->forceFill(['drm_mode' => 'required'])->save();
        $this->postJson($url, $this->body())->assertStatus(503);
        Http::assertNothingSent();
        $profile->forceFill(['drm_mode' => 'optional'])->save();
        $asset->connection->forceFill(['enabled' => false])->save();
        $this->postJson($url, $this->body())->assertStatus(503);
        $asset->connection->forceFill(['enabled' => true])->save();
        $this->fakeProvider($asset, 3);
        $this->postJson($url, $this->body())->assertStatus(503)->assertDontSee('bcdn_token=');
        $this->assertSame('processing', $asset->fresh()->status);
        $this->assertDatabaseCount('lms_playback_leases', 0);
    }

    public function test_token_expiry_is_bounded_by_access_end_and_absolute_login_deadline(): void
    {
        [$student,$course,$lesson,$block,$asset,$grant] = $this->fixture();
        $this->device($student);
        $grant->forceFill(['access_mode' => 'fixed', 'expires_at' => now('UTC')->addSeconds(80)])->save();
        $first = $this->signIn($student, 40)->postJson(route('student.video.authorize', [$course, $lesson, $block]), $this->body())->assertOk();
        $first->assertJsonPath('expires_at', now('UTC')->addSeconds(40)->toIso8601String());
        $this->assertSame(now('UTC')->addSeconds(40)->timestamp, PlaybackLease::query()->firstOrFail()->authorized_until->timestamp);
    }

    public function test_device_limit_uses_saved_profile_and_abandoned_lease_expires_without_worker(): void
    {
        [$student,$course,$lesson,$block] = $this->fixture();
        ProtectionProfile::query()->where('name', 'Private')->update(['device_limit' => 1]);
        $device = $this->device($student);
        $body = $this->body();
        $url = route('student.video.authorize', [$course, $lesson, $block]);
        $first = $this->signIn($student)->postJson($url, $body)->assertOk();
        $this->travel(151)->seconds();
        $this->postJson($first->json('renew_url'), $body)->assertConflict();
        $this->postJson($url, $this->body())->assertOk();
        $this->withCookie(VideoDeviceService::COOKIE, bin2hex(random_bytes(32)))->postJson($url, $this->body())->assertConflict();
        $this->assertDatabaseCount('lms_authorized_devices', 1);
    }

    public function test_policy_version_change_and_logout_stop_renewal(): void
    {
        [$student,$course,$lesson,$block] = $this->fixture();
        $this->device($student);
        $body = $this->body();
        $first = $this->signIn($student)->postJson(route('student.video.authorize', [$course, $lesson, $block]), $body)->assertOk();
        ProtectionProfile::query()->where('name', 'Private')->increment('lock_version');
        $this->postJson($first->json('renew_url'), $body)->assertConflict();
        $this->post(route('student.logout'))->assertRedirect();
        $this->assertSame('revoked', PlaybackLease::query()->firstOrFail()->status);
    }

    public function test_private_erasure_removes_device_proofs_and_withdraws_media_without_deleting_remote_shared_assets(): void
    {
        [$student,$course,$lesson,$block,$asset] = $this->fixture();
        $this->device($student);
        $this->signIn($student)->postJson(route('student.video.authorize', [$course, $lesson, $block]), $this->body())->assertOk();
        app(StudentPrivacyService::class)->anonymize($student->id, AdministratorFactory::new()->create()->id);
        $this->assertDatabaseCount('lms_playback_leases', 0);
        $this->assertDatabaseCount('lms_authorized_devices', 0);
        $this->assertSame('withdrawn', $asset->fresh()->status);
        $this->assertSame('Redacted private media', $asset->fresh()->label);
        $this->assertNull($block->fresh()->video_asset_id);
        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_cleanup_expires_stale_leases_and_keeps_revoked_cookie_tombstones(): void
    {
        [$student,$course,$lesson,$block] = $this->fixture();
        $device = $this->device($student);
        $this->signIn($student)->postJson(route('student.video.authorize', [$course, $lesson, $block]), $this->body())->assertOk();
        $this->travel(151)->seconds();
        $this->artisan('lms:video:prune-security')->assertSuccessful();
        $this->assertSame('expired', PlaybackLease::query()->firstOrFail()->status);
        $this->assertSame('authorized', $device->fresh()->status);
    }

    public function test_regenerating_the_login_session_cannot_reuse_an_existing_playback_proof(): void
    {
        [$student,$course,$lesson,$block] = $this->fixture();
        $this->device($student);
        $body = $this->body();
        $first = $this->signIn($student)->postJson(route('student.video.authorize', [$course, $lesson, $block]), $body)->assertOk();
        app('session')->migrate();
        app('session')->save();
        $this->withCookie(config('session.cookie'), app('session')->getId())->postJson($first->json('renew_url'), $body)->assertNotFound();
        $this->assertDatabaseCount('lms_playback_leases', 1);
    }

    public function test_raw_foreign_private_media_reference_is_denied_before_provider_authorization(): void
    {
        [$student,$course,$lesson,$block] = $this->fixture();
        $foreignOwner = Student::factory()->verified()->create();
        $foreignCourse = Course::factory()->published()->create(['kind' => 'private', 'owner_student_id' => $foreignOwner->id]);
        $foreignAsset = VideoAsset::factory()->create(['course_id' => $foreignCourse->id, 'provider_connection_id' => VideoProviderConnection::query()->firstOrFail()->id, 'status' => 'ready', 'duration_seconds' => 300]);
        $block->forceFill(['video_asset_id' => $foreignAsset->id])->save();
        $this->signIn($student)->postJson(route('student.video.authorize', [$course, $lesson, $block]), $this->body())->assertNotFound();
        $this->getJson(route('student.lms.lessons.show', [$course, $lesson]))->assertOk()->assertJsonCount(0, 'blocks')->assertDontSee($foreignAsset->provider_video_id);
        $this->assertDatabaseCount('lms_playback_leases', 0);
        Http::assertNothingSent();
    }

    public function test_bunny_bookmarks_are_private_idempotent_and_bounded_by_provider_duration(): void
    {
        [$student,$course,$lesson,$block] = $this->fixture();
        $url = route('student.learning.bookmarks.store', [$course, $lesson]);
        $this->signIn($student)->postJson($url, ['block_id' => $block->id, 'seconds' => 83.456, 'label' => 'Pronunciation'])->assertRedirect();
        $this->postJson($url, ['block_id' => $block->id, 'seconds' => 83.456, 'label' => 'Pronunciation'])->assertRedirect();
        $this->postJson($url, ['block_id' => $block->id, 'seconds' => 301])->assertUnprocessable()->assertJsonValidationErrors('seconds');
        $this->assertDatabaseCount('lms_lesson_bookmarks', 1);
        $this->assertDatabaseHas('lms_lesson_bookmarks', ['student_id' => $student->id, 'block_id' => $block->id, 'position_milliseconds' => 83456]);
        Http::assertNothingSent();
    }

    public function test_merge_revokes_both_browser_identities_and_holds_outstanding_secondary_capability_quota(): void
    {
        [$secondary,$course,$lesson,$block] = $this->fixture();
        $primary = Student::factory()->verified()->create();
        $secondaryDevice = $this->device($secondary);
        $body = $this->body();
        $this->signIn($secondary)->postJson(route('student.video.authorize', [$course, $lesson, $block]), $body)->assertOk();
        app(StudentMergeService::class)->merge($primary->id, $secondary->id, AdministratorFactory::new()->create()->id);
        $lease = PlaybackLease::query()->firstOrFail();
        $this->assertSame($secondary->id, $lease->student_id);
        $this->assertSame('revoked', $lease->status);
        $this->assertSame('revoked', $secondaryDevice->fresh()->status);
        $this->assertSame($primary->id, $course->fresh()->owner_student_id);
        $this->withCookie(VideoDeviceService::COOKIE, bin2hex(random_bytes(32)));
        $this->signIn($primary)->postJson(route('student.video.authorize', [$course, $lesson, $block]), $this->body())->assertConflict();
        $this->travel(121)->seconds();
        $this->postJson(route('student.video.authorize', [$course, $lesson, $block]), $this->body())->assertOk();
        $this->assertDatabaseCount('lms_playback_leases', 2);
    }
}
