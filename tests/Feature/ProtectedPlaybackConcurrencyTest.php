<?php

namespace Tests\Feature;

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
use App\Domains\Students\Models\Student;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProtectedPlaybackConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected array $exceptTables = ['entitlement_types', 'meeting_providers', 'settings'];

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new \RuntimeException('Video concurrency requires the dedicated test database.');
        }
    }

    private function payload(): array
    {
        $actor = AdministratorFactory::new()->create();
        $student = Student::factory()->verified()->create();
        $profile = ProtectionProfile::query()->where('name', 'Private')->first() ?? ProtectionProfile::factory()->create(['name' => 'Private', 'device_limit' => 2, 'stream_limit' => 1]);
        $course = Course::factory()->published()->create(['kind' => 'private', 'owner_student_id' => $student->id, 'protection_profile_id' => $profile->id]);
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id]);
        $connection = VideoProviderConnection::factory()->create(['enabled' => true, 'api_key' => 'fixture-api-key-123456', 'signing_key' => 'fixture-sign-key-123456', 'account_key' => 'fixture-account-key-123456', 'allowed_domains' => ['example.test']]);
        $asset = VideoAsset::factory()->create(['course_id' => $course->id, 'provider_connection_id' => $connection->id, 'status' => 'ready', 'duration_seconds' => 300]);
        $block = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'video', 'video_asset_id' => $asset->id, 'status' => 'ready', 'payload' => null]);
        $grant = app(LmsAccessOperations::class)->grant($actor, $student, $course, [], 'race-video-'.Str::uuid());
        $token = bin2hex(random_bytes(32));
        $device = AuthorizedDevice::factory()->create(['student_id' => $student->id, 'token_hash' => hash('sha256', $token)]);

        return ['action' => 'video_issue', 'administrator_id' => $actor->id, 'student_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id, 'block_id' => $block->id, 'asset_id' => $asset->id,
            'grant_id' => $grant->id, 'device_id' => $device->id, 'device_token' => $token, 'request_key' => (string) Str::uuid(), 'lease_token' => bin2hex(random_bytes(32)), 'session_id' => str_repeat('a', 40)];
    }

    public function test_simultaneous_last_stream_requests_have_one_winner_and_one_controlled_denial(): void
    {
        $first = $this->payload();
        $second = array_replace($first, ['request_key' => (string) Str::uuid(), 'lease_token' => bin2hex(random_bytes(32))]);
        $this->assertCodes([0, 2], $this->race([$first, $second]));
        $this->assertDatabaseCount('lms_playback_leases', 1);
    }

    public function test_simultaneous_same_request_retries_create_exactly_one_lease(): void
    {
        $payload = $this->payload();
        $results = $this->race([$payload, $payload]);
        $this->assertCodes([0, 0], $results);
        $this->assertSame($results[0]['output'], $results[1]['output']);
        $this->assertDatabaseCount('lms_playback_leases', 1);
    }

    public function test_simultaneous_last_browser_registrations_cannot_exceed_saved_device_limit(): void
    {
        $payload = $this->payload();
        DB::table('lms_authorized_devices')->delete();
        ProtectionProfile::query()->where('name', 'Private')->update(['device_limit' => 1]);
        $payload['action'] = 'video_register';
        $payload['device_token'] = '';
        $this->assertCodes([0, 2], $this->race([$payload, $payload]));
        $this->assertDatabaseCount('lms_authorized_devices', 1);
    }

    public function test_device_revocation_racing_issue_stops_all_later_authorizations(): void
    {
        $payload = $this->payload();
        $revoke = array_replace($payload, ['action' => 'video_device_revoke']);
        $results = $this->race([$payload, $revoke]);
        $this->assertContains($results[0]['exit_code'], [0, 2], json_encode($results));
        $this->assertSame(0, $results[1]['exit_code'], json_encode($results));
        $this->assertSame('revoked', AuthorizedDevice::query()->firstOrFail()->status);
        $this->assertSame(0, PlaybackLease::query()->where('status', 'active')->count());
        $this->assertCodes([2], $this->race([array_replace($payload, ['request_key' => (string) Str::uuid()])]));
    }

    public function test_entitlement_revocation_racing_renewal_cannot_authorize_after_revocation_commits(): void
    {
        $payload = $this->payload();
        $this->assertCodes([0], $this->race([$payload]));
        $renew = array_replace($payload, ['action' => 'video_renew', 'lease_id' => PlaybackLease::query()->firstOrFail()->id]);
        $revoke = array_replace($payload, ['action' => 'video_grant_revoke']);
        $results = $this->race([$renew, $revoke]);
        $this->assertContains($results[0]['exit_code'], [0, 2], json_encode($results));
        $this->assertSame(0, $results[1]['exit_code'], json_encode($results));
        $this->assertCodes([2], $this->race([$renew]));
        $this->assertDatabaseCount('lms_playback_leases', 1);
    }

    public function test_slow_older_provider_poll_cannot_overwrite_later_ready_reconciliation(): void
    {
        $payload = $this->payload();
        $gate = sys_get_temp_dir().DIRECTORY_SEPARATOR.'video-provider-'.Str::uuid();
        try {
            $older = array_replace($payload, ['action' => 'video_reconcile', 'provider_status' => 2, 'read_gate' => $gate]);
            $newer = array_replace($payload, ['action' => 'video_reconcile', 'provider_status' => 4, 'wait_read_gate' => $gate]);
            $this->assertCodes([0, 0], $this->race([$older, $newer]));
            $this->assertSame('ready', VideoAsset::query()->firstOrFail()->status);
            $this->assertSame(4, VideoAsset::query()->firstOrFail()->provider_status);
        } finally {
            if (is_file($gate)) {
                unlink($gate);
            }
        }
    }

    private function assertCodes(array $expected, array $results): void
    {
        $codes = array_column($results, 'exit_code');
        sort($codes);
        sort($expected);
        $this->assertSame($expected, $codes, json_encode($results));
    }

    private function race(array $payloads): array
    {
        $gate = sys_get_temp_dir().DIRECTORY_SEPARATOR.'video-race-'.Str::uuid();
        $processes = [];
        $ready = [];
        try {
            foreach ($payloads as $index => $payload) {
                $worker = 'worker-'.$index;
                $ready[] = $gate.'.ready.'.$worker;
                $payload['start_gate'] = $gate;
                $payload['worker_id'] = $worker;
                $process = new Process([PHP_BINARY, base_path('tests/Feature/Concurrency/booking_worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)]);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            while (count(array_filter($ready, 'is_file')) !== count($ready) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertCount(count($ready), array_filter($ready, 'is_file'), 'All workers must reach the simultaneous start gate.');
            file_put_contents($gate, 'start', LOCK_EX);
            foreach ($processes as $process) {
                $process->wait();
            }

            return array_map(fn (Process $process) => ['exit_code' => $process->getExitCode(), 'output' => trim($process->getOutput().$process->getErrorOutput())], $processes);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            foreach ([$gate, ...$ready] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
