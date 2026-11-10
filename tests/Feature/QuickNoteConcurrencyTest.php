<?php

namespace Tests\Feature;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class QuickNoteConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public function test_concurrent_creation_retries_return_the_same_canonical_note(): void
    {
        $student = Student::factory()->verified()->create();
        $key = (string) Str::uuid();
        $workers = [];
        try {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/quick-note-worker.php'), (string) $student->id, $key], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'bolt_landing_test', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_USERNAME' => 'root', 'DB_PASSWORD' => '', 'DB_URL' => '']);
                $worker->start();
                $workers[] = $worker;
            }
            $ids = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput().$worker->getOutput());
                $ids[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR)['note']['id'];
            }
            $this->assertCount(1, array_unique($ids));
            $this->assertDatabaseCount('student_bins', 1);
            $this->assertSame('Concurrent draft', StudentBin::query()->sole()->body);
            $this->assertDatabaseCount('audit_logs', 1);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
    }
}
