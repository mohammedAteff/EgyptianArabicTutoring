<?php

namespace Tests\Feature;

use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonNote;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\StudentLearningStateService;
use App\Domains\Students\Models\Student;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StudentLearningConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $exceptTables = ['entitlement_types', 'meeting_providers', 'settings'];

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new \RuntimeException('Student learning concurrency tests require the dedicated test database.');
        }
    }

    /** @return array{Student,Lesson,array<string,mixed>} */
    private function target(): array
    {
        $student = Student::factory()->verified()->create();
        $course = Course::factory()->published()->create();
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id]);
        app(LmsAccessOperations::class)->grant(AdministratorFactory::new()->create(), $student, $course, [], 'race-access');

        return [$student, $lesson, ['student_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id]];
    }

    public function test_simultaneous_views_keep_one_last_view_without_duplicate_rows(): void
    {
        [$student, $lesson, $target] = $this->target();
        $payload = $target + ['action' => 'learning_visit'];
        $results = $this->race([$payload, $payload]);
        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results));
        $this->assertDatabaseCount('lms_learning_visits', 1);
        $this->assertDatabaseHas('lms_learning_visits', ['student_id' => $student->id, 'lesson_id' => $lesson->id]);
    }

    public function test_simultaneous_same_timestamp_saves_one_private_bookmark(): void
    {
        [$student, $lesson, $target] = $this->target();
        $video = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'external_video', 'status' => 'ready', 'payload' => ['url' => 'https://example.test/video.mp4']]);
        $payload = $target + ['action' => 'learning_bookmark', 'block_id' => $video->id, 'seconds' => 83.456, 'label' => 'Useful moment'];
        $results = $this->race([$payload, $payload]);
        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results));
        $this->assertDatabaseCount('lms_lesson_bookmarks', 1);
        $this->assertDatabaseHas('lms_lesson_bookmarks', ['student_id' => $student->id, 'position_milliseconds' => 83456]);
    }

    public function test_simultaneous_note_edits_return_one_conflict_and_preserve_latest_body(): void
    {
        [$student, $lesson, $target] = $this->target();
        $note = app(StudentLearningStateService::class)->saveNote($student, $lesson->course, $lesson, 'Original');
        $payload = $target + ['action' => 'learning_note', 'note_id' => $note->id, 'version' => 1];
        $results = $this->race([$payload + ['body' => 'First editor'], $payload + ['body' => 'Second editor']]);
        $codes = array_column($results, 'exit_code');
        sort($codes);
        $this->assertSame([0, 2], $codes, json_encode($results));
        $this->assertSame(2, $note->fresh()->lock_version);
        $this->assertContains($note->fresh()->body, ['First editor', 'Second editor']);
        $this->assertDatabaseCount('lms_lesson_notes', 1);
    }

    public function test_privacy_race_cannot_restore_erased_private_notes(): void
    {
        [$student, $lesson, $target] = $this->target();
        $actor = AdministratorFactory::new()->create();
        $results = $this->race([$target + ['action' => 'learning_note', 'body' => 'Sensitive private body'], ['action' => 'learning_privacy', 'student_id' => $student->id, 'administrator_id' => $actor->id]]);
        $this->assertSame(0, $results[1]['exit_code'], json_encode($results));
        $this->assertContains($results[0]['exit_code'], [0, 2], json_encode($results));
        $this->assertDatabaseCount('lms_lesson_notes', 0);
        $this->assertSame('revoked', AccessGrant::query()->where('student_id', $student->id)->firstOrFail()->status);
    }

    public function test_revocation_race_cannot_save_a_note_after_access_is_withdrawn(): void
    {
        [$student, $lesson, $target] = $this->target();
        $grant = AccessGrant::query()->firstOrFail();
        $actor = AdministratorFactory::new()->create();
        $results = $this->race([$target + ['action' => 'learning_note', 'body' => 'Saved before withdrawal'], ['action' => 'lms_change', 'administrator_id' => $actor->id, 'grant_id' => $grant->id, 'change' => 'revoke', 'version' => 1, 'key' => 'race-revoke']]);
        $this->assertSame(0, $results[1]['exit_code'], json_encode($results));
        $this->assertContains($results[0]['exit_code'], [0, 2], json_encode($results));
        $this->assertSame('revoked', $grant->fresh()->status);
        $this->assertSame($results[0]['exit_code'] === 0 ? 1 : 0, LessonNote::query()->count());
        $this->expectException(HttpException::class);
        app(StudentLearningStateService::class)->saveNote($student, $lesson->course, $lesson, 'Forbidden later write');
    }

    /** @param list<array<string,mixed>> $payloads
     * @return list<array{exit_code:?int,output:string}> */
    private function race(array $payloads): array
    {
        $gate = sys_get_temp_dir().DIRECTORY_SEPARATOR.'learning-race-'.Str::uuid();
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
