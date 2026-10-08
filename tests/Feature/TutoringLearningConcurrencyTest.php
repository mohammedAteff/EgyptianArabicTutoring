<?php

namespace Tests\Feature;

use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LearningAssignment;
use App\Domains\Lms\Models\ProtectionProfile;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Students\Models\Student;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TutoringLearningConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected array $exceptTables = ['entitlement_types', 'meeting_providers', 'settings'];

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new \RuntimeException('Tutoring learning races require the dedicated test database.');
        }
    }

    private function payload(): array
    {
        $student = Student::factory()->verified()->create();
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $course = Course::factory()->published()->create();

        return ['action' => 'tutoring_assign', 'student_id' => $student->id, 'administrator_id' => $actor->id,
            'assignment' => ['kind' => 'course', 'target_id' => $course->id, 'request_key' => (string) Str::uuid(), 'access_mode' => 'relative', 'relative_days' => 2]];
    }

    public function test_simultaneous_identical_and_different_request_keys_share_one_assignment(): void
    {
        $payload = $this->payload();
        $second = $payload;
        $second['assignment']['request_key'] = (string) Str::uuid();
        $this->assertCodes([0, 0, 0], $this->race([$payload, $payload, $second]));
        $this->assertDatabaseCount('lms_learning_assignments', 1);
        $this->assertDatabaseCount('lms_access_grants', 1);
        $this->assertDatabaseCount('lms_enrollments', 1);
        $this->assertDatabaseCount('lms_access_events', 1);
    }

    public function test_simultaneous_private_creation_has_one_course_release_and_grant(): void
    {
        $payload = $this->payload();
        Course::query()->whereKey($payload['assignment']['target_id'])->delete();
        ProtectionProfile::factory()->create(['name' => 'Private', 'secure_playback' => true, 'downloads_allowed' => false, 'watermark' => true, 'device_limit' => 2, 'stream_limit' => 1]);
        $payload['action'] = 'tutoring_private';
        $payload['assignment'] = ['title' => 'Private simultaneous follow-up', 'kind' => 'rich_text', 'html' => '<p>Practice safely.</p>', 'share_now' => true, 'request_key' => (string) Str::uuid()];
        $this->assertCodes([0, 0], $this->race([$payload, $payload]));
        $this->assertDatabaseCount('lms_courses', 1);
        $this->assertDatabaseCount('lms_course_releases', 1);
        $this->assertDatabaseCount('lms_learning_assignments', 1);
        $this->assertDatabaseCount('lms_access_grants', 1);
        $this->assertDatabaseCount('lms_lessons', 1);
    }

    public function test_overlapping_reversed_bulk_lists_lock_students_in_the_same_order(): void
    {
        $payload = $this->payload();
        $other = Student::factory()->verified()->create();
        $payload['action'] = 'tutoring_bulk';
        $payload['student_ids'] = [$payload['student_id'], $other->id];
        $reverse = $payload;
        $reverse['student_ids'] = array_reverse($payload['student_ids']);
        $reverse['assignment']['request_key'] = (string) Str::uuid();
        $this->assertCodes([0, 0], $this->race([$payload, $reverse]));
        $this->assertDatabaseCount('lms_learning_assignments', 2);
        $this->assertSame([$payload['student_id'], $other->id], AccessGrant::query()->orderBy('student_id')->pluck('student_id')->all());
        $this->assertDatabaseCount('lms_enrollments', 2);
        $this->assertDatabaseCount('lms_access_events', 2);
    }

    public function test_assignment_and_student_merge_preserve_one_owner_or_reject_the_old_student(): void
    {
        $payload = $this->payload();
        $other = Student::factory()->verified()->create();
        $merge = ['action' => 'merge_students', 'primary_student_id' => $other->id, 'secondary_student_id' => $payload['student_id']];
        $results = $this->race([$payload, $merge]);
        $codes = array_column($results, 'exit_code');
        sort($codes);
        $this->assertTrue(in_array($codes, [[0, 0], [0, 2]], true), json_encode($results));
        $this->assertSame($other->id, Student::withTrashed()->findOrFail($payload['student_id'])->merged_into_student_id);
        $this->assertSame(0, AccessGrant::query()->where('student_id', $payload['student_id'])->count());
        $this->assertLessThanOrEqual(1, LearningAssignment::query()->count());
        if ($grant = AccessGrant::query()->first()) {
            $this->assertSame($other->id, $grant->student_id);
            $this->assertTrue(app(LmsAccessService::class)->canAccess($other, $grant->course));
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
        $gate = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tutoring-learning-race-'.Str::uuid();
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
