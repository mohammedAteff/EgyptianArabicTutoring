<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingPolicyDecision;
use App\Domains\Booking\Models\RecurringLessonOccurrence;
use App\Domains\Booking\Models\RecurringLessonPlan;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\RecurringLessonService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\PackageRenewal;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\InstallmentScheduleService;
use App\Domains\Students\Services\StudentLedgerService;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BusinessLifecycleConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    /** @var array<int, string> */
    protected array $exceptTables = ['entitlement_types', 'settings'];

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new \RuntimeException('Concurrency fixtures may only reset the dedicated test database.');
        }
    }

    private Student $student;

    private StudentPackage $package;

    private SessionType $type;

    private int $administratorId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('bolt_landing_test', DB::connection()->getDatabaseName());
        $this->assertStringContainsString('MariaDB', DB::selectOne('SELECT VERSION() AS version')->version);
        $this->assertSame(0, DB::transactionLevel(), 'Worker tests require committed MariaDB fixtures.');
        Setting::set('business_timezone', 'UTC');
        $this->administratorId = AdministratorFactory::new()->create(['role' => 'admin'])->id;
        $this->student = Student::factory()->verified()->create(['preferred_timezone' => 'UTC']);
        $this->type = SessionType::factory()->create(['funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::query()->where('code', 'one_hour')->value('id'), 'required_entitlement_units' => 1]);
        $this->package = app(StudentLedgerService::class)->createPackage($this->student, 'Race purchase', 1, '80.00', '0.00', 'USD', null, 'race-purchase', entitlementCode: 'one_hour');
        for ($day = 0; $day < 7; $day++) {
            AvailabilityRule::query()->create(['weekday' => $day, 'start_time' => '08:00:00', 'end_time' => '20:00:00', 'session_duration_minutes' => 60, 'buffer_minutes' => 0, 'min_notice_hours' => 0, 'max_horizon_days' => 90, 'enabled' => true]);
        }
    }

    private function plan(string $time = '09:00'): RecurringLessonPlan
    {
        return app(RecurringLessonService::class)->create($this->student, ['session_type_id' => $this->type->id, 'cadence' => 'weekly',
            'start_date' => now('UTC')->addWeek()->toDateString(), 'occurrence_count' => 1, 'preferred_time' => $time,
            'timezone' => 'UTC', 'fold_policy' => 'reject', 'idempotency_key' => Str::uuid()->toString()], $this->administratorId);
    }

    private function recurringPayload(RecurringLessonPlan $plan): array
    {
        return ['action' => 'recurring_generate', 'plan_id' => $plan->id, 'administrator_id' => $this->administratorId];
    }

    public function test_two_generators_create_only_one_booking_and_one_typed_debit_for_the_same_occurrence(): void
    {
        $payload = $this->recurringPayload($this->plan());
        $results = $this->race([$payload, $payload]);
        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results));
        $this->assertSame(1, Booking::query()->count());
        $this->assertSame(1, RecurringLessonOccurrence::query()->whereNotNull('booking_id')->count());
        $this->assertSame(1, SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->count());
        $this->assertSame(0, app(StudentLedgerService::class)->summary($this->package)['remaining_credits']);
    }

    public function test_two_recurring_plans_race_for_one_typed_credit_without_overdraw(): void
    {
        $results = $this->race([$this->recurringPayload($this->plan('09:00')), $this->recurringPayload($this->plan('11:00'))]);
        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results));
        $this->assertSame(1, Booking::query()->count());
        $this->assertSame(1, RecurringLessonOccurrence::query()->where('status', 'booked')->count());
        $this->assertSame(1, RecurringLessonOccurrence::query()->where('status', 'blocked')->count());
        $this->assertSame(-1, (int) SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->sum('credit_change'));
    }

    public function test_cancellation_and_no_show_race_has_one_policy_consequence(): void
    {
        app(RecurringLessonService::class)->generate($this->plan(), $this->administratorId, 1, 1);
        $booking = Booking::query()->sole();
        $results = $this->race([['action' => 'cancel', 'booking_id' => $booking->id, 'performed_by' => 'admin'], ['action' => 'mark_no_show', 'booking_id' => $booking->id, 'administrator_id' => $this->administratorId]]);
        $codes = array_column($results, 'exit_code');
        sort($codes);
        $this->assertSame([0, 2], $codes, json_encode($results));
        $this->assertSame(1, BookingPolicyDecision::query()->count());
        $this->assertSame(1, SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->count());
        $this->assertSame($booking->refresh()->status === 'cancelled' ? 1 : 0, SessionLedgerEntry::query()->where('entry_type', 'cancellation_restore')->count());
    }

    public function test_replayed_renewal_race_creates_one_purchase_and_preserves_source(): void
    {
        $payload = ['action' => 'renew_package', 'package_id' => $this->package->id, 'administrator_id' => $this->administratorId, 'renewal_date' => now('UTC')->toDateString(), 'idempotency_key' => 'same-renewal'];
        $before = $this->package->refresh()->getAttributes();
        $results = $this->race([$payload, $payload]);
        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results));
        $this->assertSame(1, PackageRenewal::query()->count());
        $this->assertSame(2, StudentPackage::query()->count());
        $this->assertSame(2, SessionLedgerEntry::query()->where('entry_type', 'package_grant')->count());
        $this->assertSame($before, $this->package->refresh()->getAttributes());
    }

    public function test_replayed_payment_race_records_one_actual_payment_and_satisfies_forecast_once(): void
    {
        app(InstallmentScheduleService::class)->create($this->package, [['expected_amount' => '80.00', 'due_date' => now('UTC')->toDateString()]], 'race-schedule', $this->administratorId);
        $payload = ['action' => 'record_payment', 'package_id' => $this->package->id, 'amount' => '80.00', 'administrator_id' => $this->administratorId, 'idempotency_key' => 'same-payment'];
        $results = $this->race([$payload, $payload]);
        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results));
        $this->assertSame(1, PaymentRecord::query()->count());
        $this->assertSame('0.00', app(StudentLedgerService::class)->summary($this->package)['balance_due']);
        $this->assertSame('paid', app(InstallmentScheduleService::class)->projection($this->package->fresh())[0]['status']);
    }

    public function test_payment_and_renewal_race_keep_money_on_the_original_purchase(): void
    {
        $results = $this->race([
            ['action' => 'record_payment', 'package_id' => $this->package->id, 'amount' => '80.00', 'administrator_id' => $this->administratorId, 'idempotency_key' => 'original-payment'],
            ['action' => 'renew_package', 'package_id' => $this->package->id, 'administrator_id' => $this->administratorId, 'renewal_date' => now('UTC')->toDateString(), 'idempotency_key' => 'new-renewal'],
        ]);
        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results));
        $renewal = PackageRenewal::query()->sole();
        $this->assertSame($this->package->id, PaymentRecord::query()->sole()->student_package_id);
        $this->assertSame('80.00', app(StudentLedgerService::class)->summary(StudentPackage::query()->findOrFail($renewal->new_package_id))['balance_due']);
        $this->assertSame('0.00', app(StudentLedgerService::class)->summary($this->package)['balance_due']);
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
