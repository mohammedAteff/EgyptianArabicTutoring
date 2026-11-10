<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Reporting\Services\EstimatedActivityReport;
use App\Domains\Reporting\Services\TutorWorkContext;
use App\Domains\Reporting\Services\TutorWorkReport;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\Process;
use Tests\Support\EstimatedActivityFixtures;
use Tests\TestCase;

class EstimatedActivityConcurrencyTest extends TestCase
{
    use DatabaseTruncation, EstimatedActivityFixtures;

    protected array $exceptTables = ['tutor_work_workspaces'];

    private function job(Student|Administrator $principal, array $anchor, string $action = 'checkpoint'): array
    {
        return ['action' => $action, 'domain' => $principal instanceof Student ? 'student' : 'admin', 'principal' => $principal->id,
            'run' => config('estimated_activity.run_id'), 'now' => '2026-11-04 12:00:15 UTC', 'session_id' => session()->getId(), 'session' => session()->all(),
            'body' => ['permit' => $anchor['permit'], 'sequence' => 1, 'window_ms' => 15000]];
    }

    private function race(array $jobs): array
    {
        $workers = [];
        $files = [];
        try {
            foreach ($jobs as $job) {
                $path = storage_path(Str::uuid().'.job.json');
                file_put_contents($path, json_encode($job, JSON_THROW_ON_ERROR), LOCK_EX);
                $files[] = $path;
                $worker = new Process([PHP_BINARY, base_path('tests/Support/estimated-activity-worker.php'), $path], base_path(), [
                    'APP_ENV' => 'testing', 'LARAVEL_STORAGE_PATH' => storage_path(), 'DB_DATABASE' => 'bolt_landing_test', 'DB_HOST' => '127.0.0.1',
                    'DB_PORT' => '3307', 'DB_USERNAME' => 'root', 'DB_PASSWORD' => '', 'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array',
                ]);
                $worker->setTimeout(35)->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 20;
            while (count(glob(storage_path('concurrent-release.*.ready'))) < count($jobs)) {
                foreach ($workers as $worker) {
                    if (! $worker->isRunning()) {
                        file_put_contents($this->acceptanceOutputPath('worker-bootstrap-failure.txt'), $worker->getOutput().$worker->getErrorOutput());
                        $this->fail('Activity worker stopped before its barrier. Private bootstrap evidence retained.');
                    }
                }
                if (microtime(true) >= $deadline) {
                    $this->fail('Activity workers did not reach their barrier.');
                }
                usleep(10000);
            }
            file_put_contents(storage_path('concurrent-release'), 'release');
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            file_put_contents($this->acceptanceOutputPath('concurrency-latest.json'), json_encode($results, JSON_THROW_ON_ERROR));

            return $results;
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach ([...$files, ...glob(storage_path('concurrent-release*'))] as $file) {
                @unlink($file);
            }
        }
    }

    public function test_actual_concurrent_duplicates_persist_one_interval_and_one_receipt(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student);
        $this->activityStudent($student);
        $job = $this->job($student, $this->activityAnchor($block));
        $results = $this->race([$job, $job]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $duplicates = array_column(array_column($results, 'result'), 'duplicate');
        sort($duplicates);
        $this->assertSame([false, true], $duplicates);
        $this->assertDatabaseCount('estimated_activity_intervals', 1);
        $this->assertDatabaseCount('estimated_activity_receipts', 1);
    }

    #[TestWith(['revoke'])]
    #[TestWith(['merge'])]
    #[TestWith(['erase'])]
    public function test_ingestion_racing_lifecycle_preserves_the_final_authorization_and_canonical_evidence(string $action): void
    {
        $student = Student::factory()->verified()->create();
        $primary = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student, $primary);
        $this->activityStudent($student);
        $job = $this->job($student, $this->activityAnchor($block));
        $other = array_replace($job, ['action' => $action, 'primary' => $primary->id]);
        $results = $this->race([$job, $other]);
        $this->assertSame(200, $results[1]['status']);
        $this->assertContains($results[0]['status'], [200, 403, 404, 409]);
        $this->assertSame(0, DB::table('estimated_activity_streams')->count());
        $this->assertSame(0, DB::table('estimated_activity_grants')->where('principal_id', $student->id)->whereNull('revoked_at')->count());
        if ($action === 'erase') {
            $this->assertDatabaseCount('estimated_activity_intervals', 0);
        } elseif ($action === 'merge') {
            $this->assertSame(0, DB::table('estimated_activity_intervals')->where('student_id', $student->id)->count());
        } else {
            $this->assertLessThanOrEqual(1, DB::table('estimated_activity_intervals')->count());
        }
        $this->postJson(route('student.estimated-activity.checkpoint'), $job['body'])->assertForbidden();
    }

    public function test_concurrent_student_tabs_union_independently_and_two_actors_share_one_business_projection(): void
    {
        $student = Student::factory()->verified()->create();
        $first = Administrator::factory()->create();
        $second = Administrator::factory()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student, $first, $second);
        $this->activityStudent($student);
        $one = $this->job($student, $this->activityAnchor($block));
        $two = $this->job($student, $this->activityAnchor($block));
        $results = $this->race([$one, $two]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->travel(20)->seconds();
        $this->assertSame(15, app(EstimatedActivityReport::class)->student($this->activityPeriod($student))['seconds']);
        $this->travelBack();
        $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00:00 UTC'));
        $jobs = [];
        foreach ([$first, $second] as $admin) {
            $this->activityAdmin($admin);
            $anchor = $this->postJson(route('admin.estimated-activity.anchor'), ['kind' => 'admin_course_create', 'record' => 0, 'stream' => (string) Str::uuid()])->assertOk()->json();
            $jobs[] = $this->job($admin, $anchor);
        }
        $this->assertSame([200, 200], array_column($this->race($jobs), 'status'));
        $this->travel(20)->seconds();
        $context = TutorWorkContext::fromRequest(request()->duplicate(['range' => 'today', 'timezone' => 'UTC']), $first->id);
        $this->assertSame(15, app(TutorWorkReport::class)->summary($context)['estimated_shared']);
    }
}
