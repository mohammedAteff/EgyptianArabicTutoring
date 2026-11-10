<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\AdminNotification;
use App\Domains\Students\Models\FeedbackConversation;
use App\Domains\Students\Models\FeedbackEvent;
use App\Domains\Students\Models\FeedbackOperation;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\FeedbackService;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FeedbackConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    private function payload(array $extra = []): array
    {
        return $extra + ['request_key' => (string) Str::uuid(), 'type' => 'question', 'area' => 'portal', 'subject' => 'Concurrent synthetic question', 'body' => 'Synthetic message', 'action' => 'reply'];
    }

    private function job(Student $student, array $payload, ?int $conversation = null): array
    {
        return ['operation' => $conversation ? 'mutate' : 'create', 'actor_type' => 'student', 'actor_id' => $student->id, 'conversation_id' => $conversation, 'payload' => $payload];
    }

    /** @param list<array<string,mixed>> $jobs
     * @return list<array<string,mixed>>
     */
    private function concurrently(array $jobs): array
    {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        $barrier = storage_path('framework/testing/feedback-'.Str::uuid());
        $workers = [];
        try {
            foreach ($jobs as $job) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/feedback-worker.php'), json_encode($job, JSON_THROW_ON_ERROR), $barrier], base_path(), [
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
                    $this->fail('Feedback workers did not reach the shared barrier.');
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

    public function test_duplicate_concurrent_creations_commit_once(): void
    {
        $student = Student::factory()->verified()->create();
        $job = $this->job($student, $this->payload());
        $results = $this->concurrently([$job, $job, $job]);
        $this->assertCount(1, array_unique(array_column($results, 'result')));
        $this->assertDatabaseCount('feedback_conversations', 1);
        $this->assertDatabaseCount('feedback_events', 1);
        $this->assertDatabaseCount('feedback_operations', 1);
        $this->assertSame(1, AdminNotification::where('type', 'feedback')->count());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_concurrent_replies_serialize_and_duplicate_retries_commit_once(bool $duplicate): void
    {
        $student = Student::factory()->verified()->create();
        $conversationId = app(FeedbackService::class)->create($student, $this->payload())->conversation_id;
        $first = $this->job($student, $this->payload(['body' => 'First reply']), $conversationId);
        $second = $duplicate ? $first : $this->job($student, $this->payload(['body' => 'Second reply']), $conversationId);
        $results = $this->concurrently([$first, $second]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertSame($duplicate ? [1, 2] : [1, 2, 3], FeedbackEvent::orderBy('sequence')->pluck('sequence')->all());
        $this->assertSame($duplicate ? 2 : 3, FeedbackOperation::count());
        $this->assertSame($duplicate ? 2 : 3, AdminNotification::where('type', 'feedback')->count());
    }

    public function test_reply_and_closure_have_a_valid_serialized_outcome(): void
    {
        $student = Student::factory()->verified()->create();
        $admin = AdministratorFactory::new()->create();
        $id = app(FeedbackService::class)->create($student, $this->payload())->conversation_id;
        FeedbackConversation::findOrFail($id)->update(['status' => 'resolved']);
        $reply = $this->job($student, $this->payload(), $id);
        $close = ['operation' => 'mutate', 'actor_type' => 'administrator', 'actor_id' => $admin->id, 'conversation_id' => $id,
            'payload' => ['action' => 'close', 'version' => 1, 'request_key' => (string) Str::uuid()]];
        $results = $this->concurrently([$reply, $close]);
        $codes = array_column($results, 'status');
        sort($codes);
        $this->assertSame([200, 409], $codes);
        $conversation = FeedbackConversation::findOrFail($id);
        $this->assertContains($conversation->status, ['open', 'closed']);
        $this->assertSame(2, $conversation->version);
    }

    #[TestWith(['merge'])]
    #[TestWith(['erase'])]
    public function test_lifecycle_races_cannot_orphan_feedback_or_restore_erased_content(string $operation): void
    {
        $student = Student::factory()->verified()->create();
        $primary = Student::factory()->verified()->create();
        $admin = AdministratorFactory::new()->create();
        $id = app(FeedbackService::class)->create($student, $this->payload())->conversation_id;
        $lifecycle = ['operation' => $operation, 'actor_type' => 'administrator', 'actor_id' => $admin->id,
            'student_id' => $student->id, 'primary_id' => $primary->id, 'secondary_id' => $student->id];
        $results = $this->concurrently([$this->job($student, $this->payload(), $id), $lifecycle]);
        $this->assertSame(200, $results[1]['status']);
        $this->assertContains($results[0]['status'], [200, 404]);
        $conversation = FeedbackConversation::findOrFail($id);
        if ($operation === 'erase') {
            $this->assertNotNull($conversation->erased_at);
            $this->assertSame(0, FeedbackEvent::whereNotNull('body')->count());
            $this->assertDatabaseCount('feedback_operations', 0);
        } else {
            $this->assertSame($primary->id, $conversation->student_id);
            $this->assertSame(0, FeedbackOperation::where('student_id', $student->id)->count());
        }
    }
}
