<?php

namespace Tests\Feature;

use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\AuthorizedDevice;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonProgress;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Lms\Models\VideoProgress;
use App\Domains\Lms\Models\VideoProviderConnection;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\VideoDeviceService;
use App\Domains\Students\Models\Student;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LmsWatchProgressTest extends TestCase
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
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id,
            'learning_rules' => ['required' => true, 'methods' => ['video'], 'video_threshold' => 95, 'prerequisite_key' => null, 'drip_mode' => 'immediate', 'drip_days' => null, 'drip_at' => null]]);
        $connection = VideoProviderConnection::factory()->create(['enabled' => true, 'allowed_domains' => ['example.test'], 'api_key' => 'fixture-api-key-123456',
            'signing_key' => 'fixture-sign-key-123456', 'account_key' => 'fixture-account-key-123456', 'read_only_key' => 'fixture-read-key-123456']);
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'provider_connection_id' => $connection->id, 'status' => 'ready', 'duration_seconds' => 100]);
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
            'video.bunnycdn.com/library/123/videos/'.$asset->provider_video_id => Http::response(['guid' => $asset->provider_video_id, 'videoLibraryId' => 123, 'status' => $status, 'length' => 100, 'hasMP4Fallback' => false])]);
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

    private function begin(array $fixture): array
    {
        [$student,$course,$lesson,$block] = $fixture;
        $this->device($student);
        $proof = $this->body();
        $this->signIn($student);
        $lease = $this->postJson(route('student.video.authorize', [$course, $lesson, $block]), $proof)->assertOk()->json();
        $proof['lease_id'] = $lease['lease_id'];
        $watch = $this->postJson(route('student.evidence.watch.begin', [$course, $lesson, $block]), $proof)->assertOk()->json();

        return [$proof + ['watch_token' => $watch['watch_token']], $watch, route('student.evidence.watch.sample', [$course, $lesson, $block, $watch['id']])];
    }

    private function sample(string $url, array $proof, int $sequence, float $position, string $mode = 'playing'): TestResponse
    {
        return $this->postJson($url, $proof + ['position' => $position, 'mode' => $mode, 'sequence' => $sequence, 'watched_percent' => 100, 'watched_ranges' => [[0, 100000]]]);
    }

    public function test_foreign_student_device_and_expired_security_cannot_change_watch_evidence(): void
    {
        $fixture = $this->fixture();
        [$student,$course,$lesson,$block] = $fixture;
        [$proof,$watch,$url] = $this->begin($fixture);
        $before = VideoProgress::query()->firstOrFail()->getRawOriginal();
        $this->signIn(Student::factory()->verified()->create());
        $this->sample($url, $proof, 1, 100)->assertNotFound();
        $this->assertSame($before, VideoProgress::query()->firstOrFail()->getRawOriginal());
        $this->signIn($student);
        AuthorizedDevice::query()->where('student_id', $student->id)->update(['status' => 'revoked']);
        $this->sample($url, $proof, 1, 10)->assertForbidden();
        $this->assertSame($before, VideoProgress::query()->firstOrFail()->getRawOriginal());
    }

    public function test_security_capability_expiry_blocks_samples_and_client_duration_cannot_expand_ranges(): void
    {
        $fixture = $this->fixture();
        [$proof,$watch,$url] = $this->begin($fixture);
        $this->sample($url, $proof, 1, 101)->assertUnprocessable();
        $this->travel(121)->seconds();
        $this->sample($url, $proof, 1, 10)->assertNotFound();
        $row = VideoProgress::query()->firstOrFail();
        $this->assertSame([], $row->watched_ranges);
        $this->assertSame(0, $row->sequence);
    }

    public static function unavailableMedia(): array
    {
        return [
            'processing asset' => ['processing'],
            'withdrawn asset' => ['withdrawn'],
            'unsafe fallback' => ['fallback'],
            'withdrawn block' => ['block'],
        ];
    }

    #[DataProvider('unavailableMedia')]
    public function test_current_media_unavailability_blocks_begin_and_samples_despite_an_active_lease(string $state): void
    {
        $fixture = $this->fixture();
        [$student,$course,$lesson,$block,$asset] = $fixture;
        [$proof,$watch,$url] = $this->begin($fixture);
        $this->sample($url, $proof, 1, 0)->assertOk();
        $before = VideoProgress::query()->firstOrFail()->getRawOriginal();
        $progressBefore = LessonProgress::query()->firstOrFail()->getRawOriginal();
        $this->travel(10)->seconds();
        match ($state) {
            'block' => $block->forceFill(['status' => 'withdrawn', 'video_asset_id' => null])->save(),
            'fallback' => $asset->forceFill(['has_mp4_fallback' => true])->save(),
            default => $asset->forceFill(['status' => $state])->save(),
        };

        $this->sample($url, $proof, 2, 10)->assertNotFound();
        $this->postJson(route('student.evidence.watch.begin', [$course, $lesson, $block]), $proof)->assertNotFound();
        $this->assertSame($before, VideoProgress::query()->firstOrFail()->getRawOriginal());
        $this->assertSame($progressBefore, LessonProgress::query()->firstOrFail()->getRawOriginal());
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'lms_lesson_completed')->count());
    }

    public function test_continuous_watch_completes_once_and_resumes_without_duplicate_coverage(): void
    {
        $fixture = $this->fixture();
        [$student,$course,$lesson,$block] = $fixture;
        [$proof,$watch,$url] = $this->begin($fixture);
        $this->sample($url, $proof, 1, 0)->assertOk()->assertJsonPath('percent', 0);
        for ($step = 1; $step <= 9; $step++) {
            $this->travel(10)->seconds();
            $this->sample($url, $proof, $step + 1, $step * 10)->assertOk();
        }
        $this->assertNull(LessonProgress::query()->firstOrFail()->completed_at);
        $this->travel(5)->seconds();
        $this->sample($url, $proof, 11, 95)->assertOk()->assertJsonPath('completed', true)->assertJsonPath('percent', 95);
        $this->sample($url, $proof, 11, 95)->assertOk()->assertJsonPath('percent', 95);
        $this->assertSame([[0, 95000]], VideoProgress::query()->firstOrFail()->watched_ranges);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'lms_lesson_completed')->count());
        $this->postJson(route('student.evidence.watch.begin', [$course, $lesson, $block]), $proof)->assertOk()->assertJsonPath('resume_seconds', 95)->assertJsonPath('percent', 95);
        $this->assertDatabaseCount('lms_video_progress', 1);
    }

    public function test_seek_to_end_credits_only_the_subsequently_watched_tail(): void
    {
        [$proof,$watch,$url] = $this->begin($this->fixture());
        $this->sample($url, $proof, 1, 0)->assertOk();
        $this->travel(10)->seconds();
        $this->sample($url, $proof, 2, 95)->assertOk()->assertJsonPath('percent', 0);
        $this->travel(5)->seconds();
        $this->sample($url, $proof, 3, 100)->assertOk()->assertJsonPath('percent', 5)->assertJsonPath('completed', false);
        $this->assertSame([[95000, 100000]], VideoProgress::query()->firstOrFail()->watched_ranges);
    }

    public function test_synthetic_fast_samples_cannot_accumulate_jitter_allowance_or_client_percent(): void
    {
        [$proof,$watch,$url] = $this->begin($this->fixture());
        for ($i = 1; $i <= 10; $i++) {
            $this->sample($url, $proof, $i, $i / 2)->assertOk()->assertJsonPath('percent', 0);
        }
        $this->assertSame([], VideoProgress::query()->firstOrFail()->watched_ranges);
        $this->assertNull(LessonProgress::query()->firstOrFail()->completed_at);
    }

    public function test_pause_replay_stale_batches_and_out_of_order_requests_do_not_inflate_coverage(): void
    {
        [$proof,$watch,$url] = $this->begin($this->fixture());
        $this->sample($url, $proof, 1, 0)->assertOk();
        $this->travel(10)->seconds();
        $this->sample($url, $proof, 2, 10)->assertOk()->assertJsonPath('percent', 10);
        $this->sample($url, $proof, 3, 0, 'anchor')->assertOk();
        $this->sample($url, $proof, 4, 0)->assertOk();
        $this->travel(10)->seconds();
        $this->sample($url, $proof, 5, 10)->assertOk()->assertJsonPath('percent', 10);
        $this->travel(30)->seconds();
        $this->sample($url, $proof, 6, 20)->assertOk()->assertJsonPath('percent', 10);
        $this->sample($url, $proof, 8, 20)->assertStatus(409);
    }

    public function test_heartbeat_is_not_progress_and_tampered_proofs_or_revoked_access_are_denied(): void
    {
        $fixture = $this->fixture();
        [$student,$course,$lesson,$block,$asset,$grant] = $fixture;
        [$proof,$watch,$url] = $this->begin($fixture);
        $this->postJson(route('student.video.renew', [$course, $lesson, $block, $proof['lease_id']]), $proof + ['watched_percent' => 100])->assertOk();
        $this->assertSame([], VideoProgress::query()->firstOrFail()->watched_ranges);
        $this->sample($url, array_replace($proof, ['watch_token' => str_repeat('0', 64)]), 1, 0)->assertNotFound();
        $grant->forceFill(['status' => 'revoked', 'revoked_at' => now('UTC')])->save();
        $this->sample($url, $proof, 1, 0)->assertNotFound();
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'lms_lesson_completed')->count());
    }

    public function test_media_version_change_resets_current_evidence_and_retains_previous_coverage(): void
    {
        $fixture = $this->fixture();
        [$student,$course,$lesson,$block,$asset] = $fixture;
        [$proof,$watch,$url] = $this->begin($fixture);
        $this->sample($url, $proof, 1, 0)->assertOk();
        $this->travel(10)->seconds();
        $this->sample($url, $proof, 2, 10)->assertOk()->assertJsonPath('percent', 10);
        $asset->forceFill(['duration_seconds' => 200])->save();
        $this->sample($url, $proof, 3, 20)->assertNotFound();
        $this->postJson(route('student.evidence.watch.begin', [$course, $lesson, $block]), $proof)->assertOk()->assertJsonPath('resume_seconds', 0)->assertJsonPath('percent', 0);
        $this->assertDatabaseCount('lms_video_progress', 2);
    }
}
