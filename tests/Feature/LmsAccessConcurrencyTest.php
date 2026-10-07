<?php

namespace Tests\Feature;

use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Students\Models\Student;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LmsAccessConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $exceptTables = ['entitlement_types', 'meeting_providers', 'settings'];

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new \RuntimeException('LMS concurrency tests require the dedicated test database.');
        }
    }

    public function test_simultaneous_duplicate_grant_has_one_grant_enrollment_and_event(): void
    {
        $this->assertSame(0, DB::transactionLevel());
        $actor = AdministratorFactory::new()->create();
        $student = Student::factory()->verified()->create();
        $course = Course::factory()->published()->create();
        $payload = ['action' => 'lms_grant', 'administrator_id' => $actor->id, 'student_id' => $student->id, 'course_id' => $course->id, 'key' => 'same-operation'];
        $results = $this->race([$payload, $payload]);
        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results));
        $this->assertDatabaseCount('lms_access_grants', 1);
        $this->assertDatabaseCount('lms_enrollments', 1);
        $this->assertDatabaseCount('lms_access_events', 1);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'lms_access_grant')->count());
    }

    public function test_simultaneous_same_source_with_different_keys_preserves_one_fact(): void
    {
        $actor = AdministratorFactory::new()->create();
        $student = Student::factory()->verified()->create();
        $course = Course::factory()->published()->create();
        $payload = ['action' => 'lms_grant', 'administrator_id' => $actor->id, 'student_id' => $student->id, 'course_id' => $course->id, 'terms' => ['source_key' => 'same-source']];
        $results = $this->race([$payload + ['key' => 'first'], $payload + ['key' => 'second']]);
        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results));
        $this->assertDatabaseCount('lms_access_grants', 1);
        $this->assertDatabaseCount('lms_enrollments', 1);
        $this->assertDatabaseCount('lms_access_events', 2);
    }

    public function test_simultaneous_revoke_and_extend_rejects_stale_writer(): void
    {
        $actor = AdministratorFactory::new()->create();
        $student = Student::factory()->verified()->create();
        $course = Course::factory()->published()->create();
        $grant = app(LmsAccessOperations::class)->grant($actor, $student, $course, ['access_mode' => 'relative', 'relative_days' => 2], 'initial');
        $payload = ['action' => 'lms_change', 'administrator_id' => $actor->id, 'grant_id' => $grant->id, 'version' => 1];
        $results = $this->race([$payload + ['change' => 'revoke', 'key' => 'revoke'], $payload + ['change' => 'extend', 'key' => 'extend', 'terms' => ['expires_at' => now('UTC')->addDays(4)->toIso8601String()]]]);
        $codes = array_column($results, 'exit_code');
        sort($codes);
        $this->assertSame([0, 2], $codes, json_encode($results));
        $this->assertSame(2, $grant->fresh()->lock_version);
        $this->assertDatabaseCount('lms_access_events', 2);
        $this->assertContains($grant->fresh()->status, ['active', 'revoked']);
    }

    public function test_grant_racing_canonical_student_merge_never_leaves_a_secondary_owner(): void
    {
        $actor = AdministratorFactory::new()->create();
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $course = Course::factory()->published()->create();
        $results = $this->race([
            ['action' => 'lms_grant', 'administrator_id' => $actor->id, 'student_id' => $secondary->id, 'course_id' => $course->id, 'key' => 'secondary'],
            ['action' => 'merge_students', 'primary_student_id' => $primary->id, 'secondary_student_id' => $secondary->id],
        ]);
        $codes = array_column($results, 'exit_code');
        $this->assertContains(0, $codes, json_encode($results));
        $this->assertSame([], array_diff($codes, [0, 2]), json_encode($results));
        $this->assertSame(0, AccessGrant::query()->where('student_id', $secondary->id)->count());
        $this->assertSame($primary->id, Student::withTrashed()->findOrFail($secondary->id)->merged_into_student_id);
        foreach (AccessGrant::query()->get() as $grant) {
            $this->assertSame($primary->id, $grant->student_id);
        }
    }

    public function test_same_operation_racing_different_owners_and_courses_rolls_back_loser_cleanly(): void
    {
        $actor = AdministratorFactory::new()->create();
        $payloads = [];
        for ($i = 0; $i < 2; $i++) {
            $payloads[] = ['action' => 'lms_grant', 'administrator_id' => $actor->id, 'student_id' => Student::factory()->verified()->create()->id,
                'course_id' => Course::factory()->published()->create()->id, 'key' => 'global-operation', 'terms' => ['source_key' => 'global-source']];
        }
        $results = $this->race($payloads);
        $codes = array_column($results, 'exit_code');
        sort($codes);
        $this->assertSame([0, 2], $codes, json_encode($results));
        $this->assertDatabaseCount('lms_access_grants', 1);
        $this->assertDatabaseCount('lms_enrollments', 1);
        $this->assertDatabaseCount('lms_access_events', 1);
    }

    /** @param list<array<string,mixed>> $payloads
     * @return list<array{exit_code:?int,output:string}> */
    private function race(array $payloads): array
    {
        $gate = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lms-race-'.Str::uuid();
        $processes = [];
        $readyFiles = [];
        try {
            foreach ($payloads as $index => $payload) {
                $worker = 'worker-'.$index;
                $readyFiles[] = $gate.'.ready.'.$worker;
                $payload['start_gate'] = $gate;
                $payload['worker_id'] = $worker;
                $process = new Process([PHP_BINARY, base_path('tests/Feature/Concurrency/booking_worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)]);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            while (count(array_filter($readyFiles, 'is_file')) !== count($readyFiles) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertCount(count($readyFiles), array_filter($readyFiles, 'is_file'), 'All workers must reach the simultaneous start gate.');
            file_put_contents($gate, 'start', LOCK_EX);
            foreach ($processes as $process) {
                $process->wait();
            }

            return array_map(fn (Process $process): array => ['exit_code' => $process->getExitCode(), 'output' => trim($process->getOutput().$process->getErrorOutput())], $processes);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            foreach ([$gate, ...$readyFiles] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
