<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\AdminNotification;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingPolicyDecision;
use App\Domains\Booking\Models\BookingWaitlist;
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
use App\Domains\Students\Models\StudentNotification;
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

    private function transferTarget(): StudentPackage
    {
        return app(StudentLedgerService::class)->createPackage($this->student, 'Transfer target', 1, '80.00', '0.00', 'USD', null, Str::uuid()->toString(), entitlementCode: 'one_hour');
    }

    private function transferPayload(StudentPackage $target, string $key): array
    {
        return ['action' => 'transfer_credits', 'package_id' => $this->package->id, 'target_package_id' => $target->id,
            'administrator_id' => $this->administratorId, 'idempotency_key' => $key];
    }

    public function test_identical_transfer_race_creates_one_pair_and_one_audit_even_when_source_is_drained(): void
    {
        $target = $this->transferTarget();
        $payload = $this->transferPayload($target, 'same-transfer');
        $results = $this->race([$payload, $payload]);
        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results));
        $this->assertSame(2, SessionLedgerEntry::query()->whereIn('entry_type', ['transfer_out', 'transfer_in'])->count());
        $this->assertSame(0, app(StudentLedgerService::class)->summary($this->package)['remaining_credits']);
        $this->assertSame(2, app(StudentLedgerService::class)->summary($target)['remaining_credits']);
        $this->assertSame(1, AuditLog::query()->where('action', 'credits_transferred')->count());
    }

    public function test_distinct_transfers_racing_for_the_last_credit_cannot_overdraw(): void
    {
        $a = $this->transferTarget();
        $b = $this->transferTarget();
        $results = $this->race([$this->transferPayload($a, 'transfer-a'), $this->transferPayload($b, 'transfer-b')]);
        $codes = array_column($results, 'exit_code');
        sort($codes);
        $this->assertSame([0, 2], $codes, json_encode($results));
        $this->assertSame(2, SessionLedgerEntry::query()->whereIn('entry_type', ['transfer_out', 'transfer_in'])->count());
        $this->assertSame(0, app(StudentLedgerService::class)->summary($this->package)['remaining_credits']);
        $this->assertSame(3, (int) SessionLedgerEntry::query()->sum('credit_change'));
    }

    public function test_pause_and_booking_race_cannot_leave_a_future_funded_booking_on_a_paused_package(): void
    {
        $plan = $this->plan();
        $results = $this->race([$this->recurringPayload($plan), ['action' => 'pause_package', 'package_id' => $this->package->id, 'administrator_id' => $this->administratorId]]);
        $this->assertContains($results[1]['exit_code'], [0, 2], json_encode($results));
        $this->assertSame(0, $results[0]['exit_code'], json_encode($results));
        if ($this->package->fresh()->isPaused()) {
            $this->assertSame(0, Booking::query()->count());
            $this->assertSame(1, (int) SessionLedgerEntry::query()->where('student_package_id', $this->package->id)->sum('credit_change'));
        } else {
            $this->assertSame(1, Booking::query()->count());
            $this->assertSame(0, (int) SessionLedgerEntry::query()->where('student_package_id', $this->package->id)->sum('credit_change'));
        }
    }

    public function test_simultaneous_resume_extends_expiry_only_once(): void
    {
        $this->package->update(['expires_at' => now('UTC')->addDays(60), 'paused_at' => now('UTC')->subHours(2)]);
        $before = $this->package->fresh()->expires_at;
        $payload = ['action' => 'resume_package', 'package_id' => $this->package->id, 'administrator_id' => $this->administratorId];
        $results = $this->race([$payload, $payload]);
        $codes = array_column($results, 'exit_code');
        sort($codes);
        $this->assertSame([0, 2], $codes, json_encode($results));
        $this->assertSame(1, AuditLog::query()->where('action', 'package_resumed')->count());
        $this->assertEqualsWithDelta(7200, $this->package->fresh()->expires_at->timestamp - $before->timestamp, 10);
    }

    public function test_parallel_partial_receipts_keep_the_earliest_payment_clock_without_unlocking_credits(): void
    {
        $package = app(StudentLedgerService::class)->createPackage($this->student, 'Foundation', 8, '280', '0', 'USD', null, 'partial-race', presetKey: 'foundation_track');
        $payload = ['action' => 'record_payment', 'package_id' => $package->id, 'amount' => '100.00', 'administrator_id' => $this->administratorId];

        $results = $this->race([
            $payload + ['idempotency_key' => 'earlier-receipt', 'received_at' => '2026-01-01 10:00:00'],
            $payload + ['idempotency_key' => 'later-receipt', 'received_at' => '2026-01-02 10:00:00'],
        ]);

        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results));
        $this->assertSame(2, $package->payments()->count());
        $this->assertSame('200.00', (string) $package->payments()->sum('amount_paid'));
        $this->assertSame('2026-01-01 10:00:00', $package->fresh()->validity_started_at->toDateTimeString());
        $this->assertSame('2026-03-17 10:00:00', $package->fresh()->expires_at->toDateTimeString());
        $this->assertNull($package->fresh()->funding_activated_at);
        $this->assertSame(8, (int) $package->ledgerEntries()->sum('credit_change'));
    }

    public function test_parallel_purchases_can_claim_a_completed_diagnostic_credit_only_once(): void
    {
        $this->package->update(['status' => 'cancelled']);
        $ledger = app(StudentLedgerService::class);
        $diagnostic = $ledger->createPackage($this->student, 'Diagnostic', 1, '25', '0', 'USD', null, 'diagnostic-race', presetKey: 'diagnostic_roadmap');
        $ledger->recordPayment($diagnostic, '25', 'diagnostic-payment', $this->administratorId);
        $booking = DB::transaction(function () use ($ledger): Booking {
            $booking = Booking::factory()->create(['student_id' => $this->student->id, 'session_type_id' => $this->type->id]);
            $ledger->consumeForBooking($booking, 'diagnostic-consumption');

            return $booking;
        });
        $booking->update(['status' => 'completed', 'completed_at' => now('UTC')->subHour()]);
        $payload = ['action' => 'purchase_package', 'student_id' => $this->student->id, 'administrator_id' => $this->administratorId];

        $results = $this->race([$payload + ['idempotency_key' => 'purchase-one'], $payload + ['idempotency_key' => 'purchase-two']]);

        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results));
        $purchases = StudentPackage::query()->where('offering_key', 'foundation_track')->get();
        $this->assertSame(['0.00', '25.00'], $purchases->pluck('discount_amount')->sort()->values()->all());
        $this->assertSame(1, $diagnostic->payments()->count());
        $this->assertNull($ledger->checkDiagnosticCreditEligibility($this->student->id));
    }

    public function test_parallel_waitlist_scans_create_one_opening_notice_without_booking_or_debit(): void
    {
        $this->type->update(['student_discoverable' => true]);
        $date = now('UTC')->addWeek()->toDateString();
        $interest = BookingWaitlist::factory()->create(['student_id' => $this->student->id, 'session_type_id' => $this->type->id,
            'date_from' => $date, 'date_to' => $date, 'timezone' => 'UTC', 'preferred_time_from' => '09:00', 'preferred_time_to' => '09:00']);

        $results = $this->race([['action' => 'waitlist_notify_openings'], ['action' => 'waitlist_notify_openings']]);

        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results));
        $this->assertSame(1, StudentNotification::query()->where('student_id', $this->student->id)->where('type', 'waitlist')->count());
        $this->assertSame(1, AdminNotification::query()->where('type', 'waitlist_opening')->count());
        $this->assertSame($date.' 09:00:00', $interest->fresh()->last_offered_start_at->toDateTimeString());
        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame(0, SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->count());
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
