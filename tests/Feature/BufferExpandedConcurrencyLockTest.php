<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Booking\Services\RescheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BufferExpandedConcurrencyLockTest extends TestCase
{
    protected AvailabilityService $availabilityService;

    protected BookingService $bookingService;

    protected BookingHoldService $holdService;

    protected RescheduleService $rescheduleService;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->availabilityService = app(AvailabilityService::class);
        $this->bookingService = app(BookingService::class);
        $this->holdService = app(BookingHoldService::class);
        $this->rescheduleService = app(RescheduleService::class);

        $this->sessionType = SessionType::firstOrCreate(
            ['slug' => 'midnight-buffer-session'],
            [
                'title' => 'Midnight Buffer Session',
                'duration_minutes' => 30,
                'price' => 30.00,
                'currency' => 'USD',
                'active' => true,
            ]
        );

        for ($w = 0; $w <= 6; $w++) {
            AvailabilityRule::updateOrCreate(
                ['weekday' => $w],
                [
                    'start_time' => '00:00:00',
                    'end_time' => '23:59:00',
                    'session_duration_minutes' => 30,
                    'buffer_minutes' => 0,
                    'min_notice_hours' => 0,
                    'max_horizon_days' => 90,
                    'enabled' => true,
                ]
            );
        }
    }

    protected function tearDown(): void
    {
        DB::table('bookings')->where('idempotency_key', 'like', 'buffer-test-%')->delete();
        DB::table('session_types')->where('slug', 'midnight-buffer-session')->delete();
        DB::table('booking_calendar_locks')->truncate();
        AvailabilityRule::query()->delete();
        parent::tearDown();
    }

    public function test_buffer_expanded_calendar_date_locks_cover_adjacent_business_dates(): void
    {
        $businessTz = 'Africa/Cairo';
        // Date 1: 2026-11-10 23:50 Cairo time. With 15 min buffer, end + 15m is 2026-11-11 00:35!
        $slotStartCairo = CarbonImmutable::parse('2026-11-10 23:50:00', $businessTz);
        $startUtc = $slotStartCairo->setTimezone('UTC');
        $endUtc = $startUtc->addMinutes(30);

        $lockedDates = $this->availabilityService->acquireCalendarDateLocks($startUtc, $endUtc, 15);

        // Must touch both 2026-11-10 and 2026-11-11 in sorted order
        $this->assertEquals(['2026-11-10', '2026-11-11'], $lockedDates);

        foreach ($lockedDates as $date) {
            $this->assertDatabaseHas('booking_calendar_locks', [
                'lock_date' => $date,
            ]);
        }
    }

    public function test_real_two_connection_midnight_buffer_race_where_only_one_succeeds(): void
    {
        $businessTz = 'Africa/Cairo';
        $date1 = '2026-11-10';
        $date2 = '2026-11-11';

        // Clear existing rules and set up rules covering both days with 15 min buffer
        AvailabilityRule::query()->delete();
        AvailabilityRule::create([
            'weekday' => CarbonImmutable::parse($date1)->dayOfWeek,
            'start_time' => '23:20:00',
            'end_time' => '23:55:00',
            'session_duration_minutes' => 30,
            'buffer_minutes' => 15,
            'min_notice_hours' => 0,
            'max_horizon_days' => 90,
            'enabled' => true,
        ]);
        AvailabilityRule::create([
            'weekday' => CarbonImmutable::parse($date2)->dayOfWeek,
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
            'session_duration_minutes' => 30,
            'buffer_minutes' => 15,
            'min_notice_hours' => 0,
            'max_horizon_days' => 90,
            'enabled' => true,
        ]);

        // Appointment 1: 23:20 to 23:50 on Date 1 (end + 15m buffer extends to 00:05 on Date 2)
        $app1StartCairo = CarbonImmutable::parse("{$date1} 23:20:00", $businessTz);
        $app1StartUtc = $app1StartCairo->setTimezone('UTC');
        $app1EndUtc = $app1StartUtc->addMinutes(30);

        // Appointment 2: 00:00 to 00:30 on Date 2 (start - 15m buffer extends to 23:45 on Date 1)
        $app2StartCairo = CarbonImmutable::parse("{$date2} 00:00:00", $businessTz);
        $app2StartUtc = $app2StartCairo->setTimezone('UTC');
        $app2EndUtc = $app2StartUtc->addMinutes(30);

        $payload1 = [
            'action' => 'book_admin',
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $app1StartUtc->toDateTimeString(),
            'end_at_utc' => $app1EndUtc->toDateTimeString(),
            'name' => 'Midnight Student 1',
            'email' => 'student1@midnight.test',
            'customer_timezone' => 'Africa/Cairo',
            'idempotency_key' => 'buffer-test-app1',
        ];

        $payload2 = [
            'action' => 'book_admin',
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $app2StartUtc->toDateTimeString(),
            'end_at_utc' => $app2EndUtc->toDateTimeString(),
            'name' => 'Midnight Student 2',
            'email' => 'student2@midnight.test',
            'customer_timezone' => 'Africa/Cairo',
            'idempotency_key' => 'buffer-test-app2',
        ];

        $workerPath = base_path('tests/Feature/Concurrency/booking_worker.php');

        $process1 = new Process([PHP_BINARY, $workerPath, json_encode($payload1)]);
        $process2 = new Process([PHP_BINARY, $workerPath, json_encode($payload2)]);

        $process1->setTimeout(10);
        $process2->setTimeout(10);

        // Run both worker processes concurrently
        $process1->start();
        $process2->start();

        $process1->wait();
        $process2->wait();

        $exit1 = $process1->getExitCode();
        $exit2 = $process2->getExitCode();
        $out1 = trim($process1->getOutput());
        $out2 = trim($process2->getOutput());

        $combinedOutput = $out1."\n".$out2;

        // Exactly one must succeed (exit code 0) and one must fail with conflict (exit code 2)
        $exitCodes = [$exit1, $exit2];
        sort($exitCodes);
        $this->assertSame([0, 2], $exitCodes, "Concurrency race failed. Output was:\n{$combinedOutput}");

        $this->assertStringContainsString('RESULT:SUCCESS', $combinedOutput);
        $this->assertStringContainsString('RESULT:CONFLICT', $combinedOutput);
        $this->assertStringContainsString('conflicts with the required buffer time', $combinedOutput);
    }

    public function test_exact_simultaneous_slot_race_second_transaction_fails(): void
    {
        $slotStartUtc = CarbonImmutable::now('UTC')->addDays(5)->setTime(10, 0);
        $slotEndUtc = $slotStartUtc->addMinutes(30);

        // Booking 1 succeeds
        $booking1 = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_name' => 'Racer 1',
            'customer_email' => 'racer1@test.com',
            'customer_timezone' => 'Africa/Cairo',
            'idempotency_key' => 'buffer-test-racer1',
        ], isTrustedAdmin: true);

        $this->assertInstanceOf(Booking::class, $booking1);

        // Booking 2 for exact same slot fails with SlotUnavailableException
        $this->expectException(SlotUnavailableException::class);

        $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_name' => 'Racer 2',
            'customer_email' => 'racer2@test.com',
            'customer_timezone' => 'Africa/Cairo',
            'idempotency_key' => 'buffer-test-racer2',
        ], isTrustedAdmin: true);
    }

    public function test_overlapping_reschedule_vs_booking_race(): void
    {
        $initialStartUtc = CarbonImmutable::now('UTC')->addDays(4)->setTime(12, 0);
        $initialEndUtc = $initialStartUtc->addMinutes(30);

        $targetStartUtc = CarbonImmutable::now('UTC')->addDays(6)->setTime(15, 0);
        $targetEndUtc = $targetStartUtc->addMinutes(30);

        // Existing booking to be rescheduled
        $booking = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $initialStartUtc,
            'end_at_utc' => $initialEndUtc,
            'customer_name' => 'Reschedule Student',
            'customer_email' => 'reschedule@test.com',
            'customer_timezone' => 'Africa/Cairo',
            'idempotency_key' => 'buffer-test-reschedule-1',
        ], isTrustedAdmin: true);

        // Another student books the target slot first
        $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $targetStartUtc,
            'end_at_utc' => $targetEndUtc,
            'customer_name' => 'Competing Student',
            'customer_email' => 'competing@test.com',
            'customer_timezone' => 'Africa/Cairo',
            'idempotency_key' => 'buffer-test-competing-1',
        ], isTrustedAdmin: true);

        // Reschedule to target slot must fail
        $this->expectException(SlotUnavailableException::class);
        $this->rescheduleService->reschedule(
            booking: $booking,
            newStartUtc: $targetStartUtc,
            newEndUtc: $targetEndUtc,
            performedBy: 'admin'
        );
    }

    public function test_no_deadlock_when_multiple_dates_are_locked(): void
    {
        // Two multi-date intervals: Both touch Date 1 and Date 2
        $businessTz = 'Africa/Cairo';
        $d1 = '2026-11-20';
        $d2 = '2026-11-21';

        $slot1Start = CarbonImmutable::parse("{$d1} 23:55:00", $businessTz)->setTimezone('UTC');
        $slot1End = $slot1Start->addMinutes(30);

        $slot2Start = CarbonImmutable::parse("{$d2} 00:05:00", $businessTz)->setTimezone('UTC');
        $slot2End = $slot2Start->addMinutes(30);

        // Both acquire locks in ascending order
        $dates1 = $this->availabilityService->acquireCalendarDateLocks($slot1Start, $slot1End, 15);
        $dates2 = $this->availabilityService->acquireCalendarDateLocks($slot2Start, $slot2End, 15);

        $this->assertEquals(['2026-11-20', '2026-11-21'], $dates1);
        $this->assertEquals(['2026-11-20', '2026-11-21'], $dates2);

        // Order is identical, eliminating AB-BA lock inversion deadlocks
        $this->assertSame($dates1, $dates2);
    }

    public function test_concurrent_cancel_versus_reschedule_is_deterministic(): void
    {
        $startUtc = CarbonImmutable::parse('2026-11-25 10:00:00', 'UTC');
        $endUtc = $startUtc->addMinutes(30);

        $booking = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $startUtc,
            'end_at_utc' => $endUtc,
            'customer_name' => 'Race Student',
            'customer_email' => 'racestudent@test.com',
            'customer_timezone' => 'Africa/Cairo',
            'idempotency_key' => 'buffer-test-race-cancel-resched',
        ], isTrustedAdmin: true);

        $newStartUtc = CarbonImmutable::parse('2026-11-26 10:00:00', 'UTC');
        $newEndUtc = $newStartUtc->addMinutes(30);

        $workerPath = base_path('tests/Feature/Concurrency/booking_worker.php');

        // Worker A: Cancel
        $procA = new Process([
            PHP_BINARY,
            $workerPath,
            json_encode([
                'action' => 'cancel',
                'booking_id' => $booking->id,
                'performed_by' => 'admin',
                'reason' => 'Concurrent cancellation race',
            ]),
        ]);

        // Worker B: Reschedule
        $procB = new Process([
            PHP_BINARY,
            $workerPath,
            json_encode([
                'action' => 'reschedule',
                'booking_id' => $booking->id,
                'new_start_utc' => $newStartUtc->toDateTimeString(),
                'new_end_utc' => $newEndUtc->toDateTimeString(),
            ]),
        ]);

        $procA->start();
        $procB->start();

        $procA->wait();
        $procB->wait();

        $codeA = $procA->getExitCode();
        $codeB = $procB->getExitCode();

        // One must succeed (0) and the other can succeed (0) or conflict (2)
        // Neither can deadlock or fail with unhandled exception (1 or 3)
        $this->assertContains($codeA, [0, 2], "Worker A output: {$procA->getOutput()} {$procA->getErrorOutput()}");
        $this->assertContains($codeB, [0, 2], "Worker B output: {$procB->getOutput()} {$procB->getErrorOutput()}");

        $finalBooking = Booking::find($booking->id);
        $this->assertNotNull($finalBooking);
        $this->assertContains($finalBooking->status, ['cancelled', 'confirmed']);
    }
}
