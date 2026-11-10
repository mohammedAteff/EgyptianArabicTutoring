<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorTwoFactorService;
use App\Domains\Reporting\Models\TutorWorkEntry;
use App\Domains\Reporting\Services\TutorWorkService;
use App\Domains\Reporting\Services\TutorWorkWorkspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TutorWorkConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected array $exceptTables = ['tutor_work_workspaces'];

    private function concurrently(array $jobs): array
    {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        $barrier = storage_path('framework/testing/tutor-work-'.Str::uuid());
        $workers = [];
        try {
            foreach ($jobs as $job) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/tutor-work-worker.php'), json_encode($job, JSON_THROW_ON_ERROR), $barrier], base_path(), [
                    'APP_ENV' => 'testing', 'DB_DATABASE' => 'bolt_landing_test', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307',
                    'DB_USERNAME' => 'root', 'DB_PASSWORD' => '', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
                ]);
                $worker->setTimeout(30);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 15;
            while (count(glob($barrier.'.*.ready')) < count($jobs)) {
                foreach ($workers as $worker) {
                    if (! $worker->isRunning()) {
                        $this->fail('Worker exited before barrier: '.$worker->getErrorOutput().$worker->getOutput());
                    }
                }
                if (microtime(true) > $deadline) {
                    $this->fail('Tutor work workers did not reach the shared barrier.');
                }
                usleep(10000);
            }
            file_put_contents($barrier, 'release');
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput().$worker->getOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach (glob($barrier.'*') as $file) {
                unlink($file);
            }
        }
    }

    private function job(Administrator $owner, string $action, ?int $id = null, int $version = 1, array $extra = []): array
    {
        return ['owner' => $owner->id, 'action' => $action, 'id' => $id, 'authority' => app(AdministratorTwoFactorService::class)->fingerprint($owner), 'payload' => $extra + ['action' => $action, 'version' => $version, 'operation_id' => (string) Str::uuid(), 'category' => 'feedback', 'timezone' => 'UTC']];
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_simultaneous_starts_commit_one_timer_and_duplicate_receipt_once(bool $duplicate): void
    {
        $owner = Administrator::factory()->create();
        $first = $this->job($owner, 'start');
        $results = $this->concurrently([$first, $duplicate ? $first : $this->job(Administrator::factory()->create(), 'start')]);
        $codes = array_column($results, 'status');
        sort($codes);
        $this->assertSame($duplicate ? [200, 200] : [200, 409], $codes);
        $this->assertDatabaseCount('tutor_work_entries', 1);
        $this->assertDatabaseCount('tutor_work_segments', 1);
        $this->assertDatabaseCount('tutor_work_operations', 1);
        $this->assertSame((int) DB::table('tutor_work_workspaces')->sole()->id, TutorWorkEntry::sole()->active_workspace);
    }

    #[TestWith(['pause', 'pause', 'running'])]
    #[TestWith(['pause', 'resume', 'running'])]
    #[TestWith(['resume', 'stop', 'paused'])]
    #[TestWith(['stop', 'stop', 'running'])]
    public function test_simultaneous_timer_commands_have_one_valid_versioned_outcome(string $firstAction, string $secondAction, string $initial): void
    {
        $owner = Administrator::factory()->create();
        $service = app(TutorWorkService::class);
        $id = $service->mutate($owner->id, 'start', null, $this->job($owner, 'start')['payload'])['result']['entry_id'];
        $version = 1;
        if ($initial === 'paused') {
            $service->mutate($owner->id, 'pause', $id, $this->job($owner, 'pause', $id)['payload']);
            $version++;
        }
        $results = $this->concurrently([$this->job($owner, $firstAction, $id, $version), $this->job(Administrator::factory()->create(), $secondAction, $id, $version)]);
        $codes = array_column($results, 'status');
        sort($codes);
        $this->assertSame([200, 409], $codes);
        $entry = TutorWorkEntry::findOrFail($id);
        $this->assertSame($version + 1, $entry->version);
        $this->assertSame($entry->lifecycle === 'running' ? 1 : 0, DB::table('tutor_work_segments')->whereNotNull('open_entry')->count());
        $this->assertSame($entry->lifecycle === 'stopped' ? null : (int) $entry->workspace_id, $entry->active_workspace);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_simultaneous_correction_and_correction_or_exclusion_cannot_overwrite_version(bool $exclude): void
    {
        $owner = Administrator::factory()->create();
        $entry = TutorWorkEntry::factory()->create(['created_by' => $owner->id]);
        $extra = ['placement' => 'unplaced', 'work_date' => now('UTC')->toDateString(), 'hours' => 2, 'minutes' => 0, 'reason' => 'Synthetic correction'];
        $first = $this->job($owner, 'correct', $entry->id, extra: $extra);
        $second = $this->job(Administrator::factory()->create(), $exclude ? 'exclude' : 'correct', $entry->id, extra: $extra + ['description' => 'Other synthetic value']);
        $results = $this->concurrently([$first, $second]);
        $codes = array_column($results, 'status');
        sort($codes);
        $this->assertSame([200, 409], $codes);
        $this->assertSame(2, $entry->fresh()->version);
        $this->assertDatabaseCount('tutor_work_revisions', 1);
        $this->assertDatabaseCount('tutor_work_operations', 1);
    }

    public function test_password_revocation_serializes_with_commands_and_rejects_stale_authority(): void
    {
        $owner = Administrator::factory()->create();
        $id = app(TutorWorkService::class)->mutate($owner->id, 'start', null, $this->job($owner, 'start')['payload'])['result']['entry_id'];
        $results = $this->concurrently([$this->job($owner, 'pause', $id), ['owner' => $owner->id, 'kind' => 'revoke']]);
        $this->assertContains($results[0]['status'], [200, 403]);
        $this->assertSame(200, $results[1]['status']);
        $entry = TutorWorkEntry::findOrFail($id);
        $this->assertSame($results[0]['status'] === 200 ? 'paused' : 'stopped', $entry->lifecycle);
        $this->assertSame($results[0]['status'] === 200 ? 'provisional' : 'needs_review', $entry->acceptance);
        $this->assertSame($results[0]['status'] === 200 ? (int) $entry->workspace_id : null, $entry->active_workspace);
        $this->assertSame(0, DB::table('tutor_work_segments')->whereNotNull('open_entry')->count());
    }

    public function test_database_refuses_second_active_slot_even_without_command_service(): void
    {
        $owner = Administrator::factory()->create();
        app(TutorWorkService::class)->mutate($owner->id, 'start', null, $this->job($owner, 'start')['payload']);
        try {
            TutorWorkEntry::factory()->create(['created_by' => $owner->id, 'active_workspace' => (int) app(TutorWorkWorkspace::class)->ready()->id, 'origin' => 'timer', 'lifecycle' => 'paused']);
            $this->fail('Database accepted a second open timer.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', (string) $exception->getCode());
        }
        $this->assertDatabaseCount('tutor_work_entries', 1);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_revocation_with_an_older_outer_snapshot_uses_the_committed_segment_opener_and_all_raw_segments(bool $revokeController): void
    {
        $a = Administrator::factory()->create();
        $b = Administrator::factory()->create();
        $this->travel(-120)->seconds();
        $id = app(TutorWorkService::class)->mutate($a->id, 'start', null, $this->job($a, 'start')['payload'])['result']['entry_id'];
        $this->travelBack();
        $results = $this->concurrently([
            ['kind' => 'stale_revoke', 'owner' => $revokeController ? $b->id : $a->id],
            ['kind' => 'takeover'] + $this->job($b, 'resume', $id, 2),
        ]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $entry = TutorWorkEntry::findOrFail($id);
        if ($revokeController) {
            $this->assertSame('stopped', $entry->lifecycle);
            $this->assertSame('needs_review', $entry->acceptance);
            $this->assertGreaterThanOrEqual(120, app(TutorWorkService::class)->seconds($entry));
            $this->assertDatabaseCount('tutor_work_segments', 2);
            $this->assertDatabaseHas('tutor_work_revisions', ['entry_id' => $id, 'action' => 'security_revocation', 'subject_administrator_id' => $b->id]);
        } else {
            $this->assertSame('running', $entry->lifecycle);
            $this->assertSame(3, $entry->version);
            $this->assertDatabaseHas('tutor_work_segments', ['entry_id' => $id, 'open_entry' => $id, 'opened_by' => $b->id]);
            $this->assertDatabaseMissing('tutor_work_revisions', ['entry_id' => $id, 'action' => 'security_revocation']);
        }
    }
}
