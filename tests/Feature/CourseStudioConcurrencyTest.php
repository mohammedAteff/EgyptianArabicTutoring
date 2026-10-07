<?php

namespace Tests\Feature;

use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Services\CourseStudioService;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CourseStudioConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $exceptTables = ['entitlement_types', 'meeting_providers', 'settings'];

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new \RuntimeException('Course Studio concurrency tests require the dedicated test database.');
        }
    }

    public function test_simultaneous_create_with_same_slug_has_one_safe_draft_and_controlled_loser(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $payload = ['action' => 'course_studio_create', 'administrator_id' => $actor->id, 'values' => ['title' => 'Concurrent course', 'slug' => 'same-course', 'kind' => 'catalog']];
        $results = $this->race([$payload, $payload]);
        $codes = array_column($results, 'exit_code');
        sort($codes);
        $this->assertSame([0, 2], $codes, json_encode($results));
        $this->assertDatabaseCount('lms_courses', 1);
        $this->assertDatabaseCount('content_revisions', 1);
        $this->assertSame('draft', Course::query()->firstOrFail()->status);
    }

    public function test_simultaneous_edit_rejects_stale_editor_without_duplicate_drafts(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $studio = app(CourseStudioService::class);
        $course = $studio->create($actor, ['title' => 'Initial', 'slug' => 'initial', 'kind' => 'catalog']);
        $payload = ['action' => 'course_studio_write', 'administrator_id' => $actor->id, 'course_id' => $course->id, 'version' => 1];
        $results = $this->race([$payload + ['values' => ['title' => 'First editor', 'slug' => 'first']], $payload + ['values' => ['title' => 'Second editor', 'slug' => 'second']]]);
        $codes = array_column($results, 'exit_code');
        sort($codes);
        $this->assertSame([0, 2], $codes, json_encode($results));
        $this->assertDatabaseCount('content_revisions', 1);
        $this->assertSame(2, $course->fresh()->lock_version);
        $this->assertContains($studio->draft($actor, $course)['title'], ['First editor', 'Second editor']);
    }

    public function test_simultaneous_publication_creates_one_owned_release_and_preserves_selected_states(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $studio = app(CourseStudioService::class);
        $course = $studio->create($actor, ['title' => 'Concurrent publication', 'slug' => 'concurrent-publication', 'kind' => 'catalog']);
        $course = $studio->write($actor, $course, 'access', ['audience' => 'public'], $course->lock_version);
        $course = $studio->write($actor, $course, 'add_section', ['title' => 'Section', 'status' => 'published'], $course->lock_version);
        $section = $studio->draft($actor, $course)['sections'][0];
        $course = $studio->write($actor, $course, 'add_lesson', ['parent_key' => $section['key'], 'title' => 'Lesson', 'slug' => 'lesson', 'status' => 'published'], $course->lock_version);
        $lesson = $studio->draft($actor, $course)['sections'][0]['lessons'][0];
        $course = $studio->write($actor, $course, 'add_block', ['parent_key' => $lesson['key'], 'kind' => 'rich_text', 'html' => '<p>Concurrent content</p>'], $course->lock_version);
        $payload = ['action' => 'course_studio_publish', 'administrator_id' => $actor->id, 'course_id' => $course->id, 'version' => $course->lock_version];
        $results = $this->race([$payload, $payload]);
        $codes = array_column($results, 'exit_code');
        sort($codes);
        $this->assertSame([0, 2], $codes, json_encode($results));
        $this->assertDatabaseCount('lms_course_releases', 1);
        $this->assertDatabaseCount('content_revisions', 1);
        $this->assertDatabaseCount('lms_lessons', 1);
        $this->assertSame('published', $course->fresh()->status);
        $this->assertNotNull($course->fresh()->current_release_id);
    }

    public function test_double_duplicate_from_same_editor_version_creates_one_copy_without_runtime_data(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $studio = app(CourseStudioService::class);
        $course = $studio->create($actor, ['title' => 'Copy source', 'slug' => 'copy-source', 'kind' => 'catalog']);
        $payload = ['action' => 'course_studio_duplicate', 'administrator_id' => $actor->id, 'course_id' => $course->id, 'version' => 1];
        $results = $this->race([$payload, $payload]);
        $codes = array_column($results, 'exit_code');
        sort($codes);
        $this->assertSame([0, 2], $codes, json_encode($results));
        $this->assertDatabaseCount('lms_courses', 2);
        $this->assertSame(2, $course->fresh()->lock_version);
        foreach (['lms_enrollments', 'lms_access_grants', 'lms_learning_assignments'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    /** @param list<array<string,mixed>> $payloads
     * @return list<array{exit_code:?int,output:string}> */
    private function race(array $payloads): array
    {
        $gate = sys_get_temp_dir().DIRECTORY_SEPARATOR.'studio-race-'.Str::uuid();
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
