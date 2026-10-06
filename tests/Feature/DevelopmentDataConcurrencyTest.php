<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\System\Models\DevelopmentDataOperation;
use App\Domains\System\Services\DevelopmentDataReconciler;
use App\Domains\System\Services\DevelopmentToolsService;
use Database\Factories\AdministratorFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DevelopmentDataConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    /** @var array<int, string> */
    protected array $exceptTables = ['entitlement_types', 'meeting_providers', 'settings'];

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new \RuntimeException('Development concurrency tests require the dedicated test database.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['development_tools.enabled' => true, 'development_tools.require_snapshot' => false]);
        $this->assertSame(0, DB::transactionLevel());
    }

    private function actor(): Administrator
    {
        return AdministratorFactory::new()->create(['role' => 'super_admin', 'password' => Hash::make('Stage5RacePass!')]);
    }

    public function test_simultaneous_confirmation_cannot_apply_student_reset_twice(): void
    {
        $actor = $this->actor();
        Student::factory()->verified()->create();
        $preview = app(DevelopmentToolsService::class)->preview($actor, 'race-session', 'reset', 'students', []);
        $payload = ['action' => 'development_confirm', 'administrator_id' => $actor->id, 'session_id' => 'race-session',
            'token' => $preview['token'], 'phrase' => 'DELETE ALL STUDENTS'];
        $results = $this->race([$payload, $payload]);
        $codes = array_column($results, 'exit_code');
        $this->assertContains(0, $codes, json_encode($results));
        $this->assertSame([], array_diff($codes, [0, 2]), json_encode($results));
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('development_data_operations', 1);
        $this->assertSame('completed', $preview['operation']->fresh()->status);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'development_data_reset_completed')->count());
        $summary = app(DevelopmentToolsService::class)->confirm($actor, 'race-session', $preview['token'], '', null, null, '');
        $this->assertSame(1, $summary['deleted_records']['students']);
    }

    public function test_competing_previews_for_same_student_graph_cannot_both_apply(): void
    {
        $actor = $this->actor();
        Student::factory()->verified()->create();
        $tools = app(DevelopmentToolsService::class);
        $payloads = [];
        for ($index = 0; $index < 2; $index++) {
            $preview = $tools->preview($actor, 'race-session', 'reset', 'students', []);
            $payloads[] = ['action' => 'development_confirm', 'administrator_id' => $actor->id, 'session_id' => 'race-session',
                'token' => $preview['token'], 'phrase' => 'DELETE ALL STUDENTS'];
        }
        $results = $this->race($payloads);
        $codes = array_column($results, 'exit_code');
        sort($codes);
        $this->assertSame([0, 2], $codes, json_encode($results));
        $this->assertDatabaseCount('students', 0);
        $this->assertSame(1, DevelopmentDataOperation::query()->where('status', 'completed')->count());
    }

    public function test_financial_reset_and_new_payment_leave_a_coherent_graph(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        $package = app(StudentLedgerService::class)->createPackage($student, 'Competing payment purchase', 1, '30.00', '0.00', 'USD', null, 'competing-purchase', $actor->id, null, null, 'one_hour');
        $preview = app(DevelopmentToolsService::class)->preview($actor, 'race-session', 'reset', 'financial', []);
        $results = $this->race([
            ['action' => 'development_confirm', 'administrator_id' => $actor->id, 'session_id' => 'race-session', 'token' => $preview['token'], 'phrase' => 'RESET FINANCIAL TEST HISTORY'],
            ['action' => 'record_payment', 'administrator_id' => $actor->id, 'package_id' => $package->id, 'amount' => '30.00', 'idempotency_key' => 'competing-payment'],
        ]);
        $this->assertContains(0, array_column($results, 'exit_code'), json_encode($results));
        $this->assertSame([], array_diff(array_column($results, 'exit_code'), [0, 2]), json_encode($results));
        $this->assertModelExists($student);
        $reconciliation = app(DevelopmentDataReconciler::class)->verify(true);
        $this->assertSame(0, $reconciliation['orphan_count']);
        $this->assertSame(0, $reconciliation['financial_discrepancies']);
    }

    public function test_unknown_future_student_dependency_refuses_preview_with_foreign_keys_enforced(): void
    {
        $actor = $this->actor();
        $student = Student::factory()->verified()->create();
        Schema::create('stage5_unreviewed_child', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
        });
        try {
            DB::table('stage5_unreviewed_child')->insert(['student_id' => $student->id]);
            try {
                app(DevelopmentToolsService::class)->preview($actor, 'race-session', 'reset', 'students', []);
                $this->fail('Unknown dependent tables must require explicit review.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('unreviewed dependent', $exception->errors()['scope'][0]);
            }
            $this->assertModelExists($student);
            $this->assertSame(1, (int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled);
        } finally {
            Schema::dropIfExists('stage5_unreviewed_child');
        }
    }

    /** @param array<int, array<string, mixed>> $payloads
     * @return array<int, array{exit_code: int|null, output: string}> */
    private function race(array $payloads): array
    {
        $gate = sys_get_temp_dir().DIRECTORY_SEPARATOR.'stage4-race-'.Str::uuid();
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
