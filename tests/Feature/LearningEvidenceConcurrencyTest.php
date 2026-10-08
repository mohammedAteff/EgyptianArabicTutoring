<?php

namespace Tests\Feature;

use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Lms\Models\AssignmentSubmission;
use App\Domains\Lms\Models\AuthorizedDevice;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonProgress;
use App\Domains\Lms\Models\PlaybackLease;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Lms\Models\VideoProgress;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsAssignmentService;
use App\Domains\Lms\Services\LmsLearningDefinition;
use App\Domains\Lms\Services\LmsQuizService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentPrivacyService;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LearningEvidenceConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected array $exceptTables = ['entitlement_types', 'meeting_providers', 'settings'];

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new \RuntimeException('Learning races require the dedicated test database.');
        }
    }

    private function fixture(string $method = 'manual'): array
    {
        $student = Student::factory()->verified()->create();
        $actor = AdministratorFactory::new()->create();
        $course = Course::factory()->published()->create();
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id,
            'learning_rules' => ['required' => true, 'methods' => [$method], 'video_threshold' => 95, 'prerequisite_key' => null, 'drip_mode' => 'immediate', 'drip_days' => null, 'drip_at' => null]]);
        app(LmsAccessOperations::class)->grant($actor, $student, $course, [], 'race-'.Str::uuid());

        return ['student_id' => $student->id, 'administrator_id' => $actor->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id, 'request_key' => (string) Str::uuid()];
    }

    public function test_private_submission_erasure_deletes_bytes_after_commit(): void
    {
        Storage::fake('local');
        [$p,$row] = $this->privateSubmission();
        $path = $row->path;
        app(StudentPrivacyService::class)->anonymize($p['student_id'], $p['administrator_id']);
        Storage::disk('local')->assertMissing($path);
        $this->assertSame('erased', $row->fresh()->status);
        $this->assertNull($row->fresh()->path);
    }

    public function test_failed_erasure_cleanup_retains_only_protected_recovery_metadata(): void
    {
        Storage::fake('local');
        [$p,$row] = $this->privateSubmission();
        $this->partialMock(LessonMaterialService::class)->shouldReceive('discardLearningSubmission')->once()->andReturn(false);
        app(StudentPrivacyService::class)->anonymize($p['student_id'], $p['administrator_id']);
        $this->assertSame('erased', $row->fresh()->status);
        $this->assertSame($row->path, $row->fresh()->path);
        $this->assertNull($row->fresh()->body);
        $this->assertSame([], $row->fresh()->definition);
        Storage::disk('local')->assertExists($row->path);
    }

    private function privateSubmission(): array
    {
        $p = $this->fixture('assignment_submit');
        $block = LessonBlock::factory()->create(['lesson_id' => $p['lesson_id'], 'kind' => 'assignment', 'status' => 'ready', 'payload' => ['title' => 'Private response', 'instructions' => 'Upload', 'types' => ['file']]]);
        $row = app(LmsAssignmentService::class)->submit(Student::findOrFail($p['student_id']), Course::findOrFail($p['course_id']), Lesson::findOrFail($p['lesson_id']), $block,
            ['kind' => 'file', 'request_key' => $p['request_key']], UploadedFile::fake()->createWithContent('response.pdf', "%PDF-1.4\nPrivate response"));

        return [$p, $row];
    }

    public function test_duplicate_completion_has_one_persisted_transition_and_one_audit(): void
    {
        $p = $this->fixture();
        $p['action'] = 'evidence_manual';
        $this->assertCodes([0, 0], $this->race([$p, $p]));
        $this->assertDatabaseCount('lms_lesson_progress', 1);
        $this->assertNotNull(LessonProgress::query()->firstOrFail()->completed_at);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'lms_lesson_completed')->count());
    }

    private function quizPayload(): array
    {
        $p = $this->fixture('quiz_pass');
        $block = LessonBlock::factory()->create(['lesson_id' => $p['lesson_id'], 'kind' => 'quiz', 'status' => 'ready', 'payload' => [
            'title' => 'Race quiz', 'passing_score' => 80, 'attempt_limit' => 1, 'review_policy' => 'never',
            'questions' => [['type' => 'choice', 'prompt' => 'Pick', 'points' => 1, 'options' => ['A', 'B'], 'answer' => 0]]]]);

        return $p + ['block_id' => $block->id];
    }

    public function test_duplicate_quiz_submission_scores_once_and_cannot_duplicate_completion(): void
    {
        $p = $this->quizPayload();
        $attempt = app(LmsQuizService::class)->begin(Student::findOrFail($p['student_id']), Course::findOrFail($p['course_id']), Lesson::findOrFail($p['lesson_id']), LessonBlock::findOrFail($p['block_id']), $p['request_key']);
        $p += ['action' => 'evidence_quiz_submit', 'attempt_id' => $attempt->id, 'answers' => [0]];
        $this->assertCodes([0, 0], $this->race([$p, $p]));
        $this->assertDatabaseCount('lms_quiz_attempts', 1);
        $this->assertSame(100.0, $attempt->fresh()->score);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'lms_quiz_submitted')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'lms_lesson_completed')->count());
    }

    public function test_last_quiz_attempt_is_reserved_once_under_simultaneous_start(): void
    {
        $p = $this->quizPayload() + ['action' => 'evidence_quiz_begin'];
        $this->assertCodes([0, 0], $this->race([$p, array_replace($p, ['request_key' => (string) Str::uuid()])]));
        $this->assertDatabaseCount('lms_quiz_attempts', 1);
        $this->assertSame(1, QuizAttempt::query()->firstOrFail()->number);
    }

    public function test_simultaneous_assignment_decisions_have_one_winner_and_a_stale_denial(): void
    {
        $p = $this->fixture('assignment_approve');
        $block = LessonBlock::factory()->create(['lesson_id' => $p['lesson_id'], 'kind' => 'assignment', 'status' => 'ready', 'payload' => ['title' => 'Race response', 'instructions' => 'Write', 'types' => ['text']]]);
        $row = app(LmsAssignmentService::class)->submit(Student::findOrFail($p['student_id']), Course::findOrFail($p['course_id']), Lesson::findOrFail($p['lesson_id']), $block, ['kind' => 'text', 'body' => 'Initial', 'request_key' => $p['request_key']], null);
        $p += ['action' => 'evidence_assignment_review', 'block_id' => $block->id, 'submission_id' => $row->id, 'version' => 1, 'status' => 'approved', 'feedback' => 'Accepted'];
        $this->assertCodes([0, 2], $this->race([$p, array_replace($p, ['status' => 'needs_revision', 'feedback' => 'Revise'])]));
        $this->assertContains($row->fresh()->status, ['approved', 'needs_revision']);
        $this->assertSame(2, $row->fresh()->lock_version);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'lms_assignment_reviewed')->count());
    }

    public function test_duplicate_assignment_submission_has_one_revision(): void
    {
        $p = $this->fixture('assignment_submit');
        $block = LessonBlock::factory()->create(['lesson_id' => $p['lesson_id'], 'kind' => 'assignment', 'status' => 'ready', 'payload' => ['title' => 'Race response', 'instructions' => 'Write', 'types' => ['text']]]);
        $p += ['action' => 'evidence_assignment_submit', 'block_id' => $block->id, 'submission' => ['kind' => 'text', 'body' => 'Initial', 'request_key' => $p['request_key']]];
        $this->assertCodes([0, 0], $this->race([$p, $p]));
        $this->assertDatabaseCount('lms_assignment_submissions', 1);
        $this->assertSame(1, AssignmentSubmission::query()->firstOrFail()->number);
    }

    public function test_simultaneous_video_threshold_crossing_credits_once_and_completes_once(): void
    {
        $p = $this->fixture('video');
        $asset = VideoAsset::factory()->create(['course_id' => $p['course_id'], 'status' => 'ready', 'duration_seconds' => 100]);
        $block = LessonBlock::factory()->create(['lesson_id' => $p['lesson_id'], 'kind' => 'video', 'status' => 'ready', 'video_asset_id' => $asset->id, 'payload' => null]);
        $deviceToken = str_repeat('d', 64);
        $leaseToken = str_repeat('e', 64);
        $watchToken = str_repeat('f', 64);
        $sessionId = str_repeat('a', 40);
        $device = AuthorizedDevice::factory()->create(['student_id' => $p['student_id'], 'token_hash' => hash('sha256', $deviceToken)]);
        $lease = PlaybackLease::factory()->create(['student_id' => $p['student_id'], 'device_id' => $device->id, 'course_id' => $p['course_id'], 'lesson_id' => $p['lesson_id'], 'block_id' => $block->id,
            'video_asset_id' => $asset->id, 'session_hash' => hash_hmac('sha256', $sessionId, config('app.key')), 'lease_token_hash' => hash('sha256', $leaseToken),
            'authorized_until' => now('UTC')->addMinute(), 'expires_at' => now('UTC')->addMinute()]);
        $watch = new VideoProgress;
        $watch->forceFill(['student_id' => $p['student_id'], 'block_id' => $block->id, 'media_hash' => app(LmsLearningDefinition::class)->mediaHash($block),
            'duration_milliseconds' => 100000, 'watched_ranges' => [[0, 90000]], 'position_milliseconds' => 90000, 'playing' => true, 'sampled_at' => now('UTC')->subSeconds(10),
            'watch_token_hash' => hash('sha256', $watchToken), 'lease_id' => $lease->id, 'sequence' => 0])->save();
        $p += ['action' => 'evidence_watch', 'block_id' => $block->id, 'watch_id' => $watch->id, 'session_id' => $sessionId, 'device_token' => $deviceToken,
            'sample' => ['lease_id' => $lease->id, 'lease_token' => $leaseToken, 'watch_token' => $watchToken, 'position' => 100, 'mode' => 'playing', 'sequence' => 1]];
        $this->assertCodes([0, 0], $this->race([$p, $p]));
        $this->assertSame([[0, 100000]], $watch->fresh()->watched_ranges);
        $this->assertNotNull(LessonProgress::query()->firstOrFail()->completed_at);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'lms_lesson_completed')->count());
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
