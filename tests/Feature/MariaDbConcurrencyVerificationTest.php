<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MariaDbConcurrencyVerificationTest extends TestCase
{
    protected AvailabilityService $availabilityService;

    protected BookingService $bookingService;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->availabilityService = app(AvailabilityService::class);
        $this->bookingService = app(BookingService::class);

        $this->sessionType = SessionType::firstOrCreate(
            ['slug' => 'conversational-arabic'],
            [
                'title' => 'Conversational Arabic',
                'duration_minutes' => 60,
                'price' => 40.00,
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
                    'session_duration_minutes' => 60,
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
        DB::table('bookings')->where('idempotency_key', 'like', 'unique-key-concurrent-%')->delete();
        DB::table('session_types')->where('slug', 'conversational-arabic')->delete();
        DB::table('booking_calendar_locks')->truncate();
        DB::table('contacts')->whereIn('email', ['first@boltlanding.test', 'second@boltlanding.test'])->delete();
        AvailabilityRule::query()->delete();
        parent::tearDown();
    }

    public function test_calendar_date_mutex_locks_are_deterministically_created(): void
    {
        $startUtc = CarbonImmutable::now('UTC')->addDays(2)->setTime(10, 0);
        $endUtc = $startUtc->addMinutes(60);

        DB::beginTransaction();
        $lockedDates = $this->availabilityService->acquireCalendarDateLocks($startUtc, $endUtc);
        DB::commit();

        $this->assertNotEmpty($lockedDates);
        foreach ($lockedDates as $date) {
            $this->assertDatabaseHas('booking_calendar_locks', [
                'lock_date' => $date,
            ]);
        }
    }

    public function test_two_connections_serialize_via_mariadb_innodb_row_lock(): void
    {
        $startUtc = CarbonImmutable::now('UTC')->addDays(3)->setTime(14, 0);
        $endUtc = $startUtc->addMinutes(60);
        $targetDate = $startUtc->setTimezone('Africa/Cairo')->toDateString();

        // 1. Ensure the row exists in booking_calendar_locks
        DB::table('booking_calendar_locks')->insertOrIgnore([
            'lock_date' => $targetDate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pdoConfig = config('database.connections.mysql');
        $dsn = "mysql:host={$pdoConfig['host']};port={$pdoConfig['port']};dbname={$pdoConfig['database']};charset=utf8mb4";

        // 2. Open Connection 1 (PDO session 1) and acquire pessimistic lock on target date
        $conn1Pdo = new \PDO($dsn, $pdoConfig['username'], $pdoConfig['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $conn1Pdo->beginTransaction();
        $stmt1 = $conn1Pdo->prepare('SELECT * FROM booking_calendar_locks WHERE lock_date = ? FOR UPDATE');
        $stmt1->execute([$targetDate]);
        $stmt1->fetch();

        // 3. Open Connection 2 (PDO session 2) with 1s lock wait timeout
        $conn2Pdo = new \PDO($dsn, $pdoConfig['username'], $pdoConfig['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $conn2Pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $conn2Pdo->beginTransaction();

        $timedOut = false;
        try {
            $stmt2 = $conn2Pdo->prepare('SELECT * FROM booking_calendar_locks WHERE lock_date = ? FOR UPDATE');
            $stmt2->execute([$targetDate]);
            $stmt2->fetch();
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), '1205') || str_contains($e->getMessage(), 'Lock wait timeout')) {
                $timedOut = true;
            }
        } finally {
            $conn2Pdo->rollBack();
        }

        $this->assertTrue($timedOut, 'Concurrent Connection 2 must be blocked by Connection 1 row lock on booking_calendar_locks');

        // 4. Release Connection 1 lock by committing
        $conn1Pdo->commit();

        // 5. Now Connection 2 can acquire the lock immediately without timeout
        $conn2Pdo->beginTransaction();
        $stmt2 = $conn2Pdo->prepare('SELECT * FROM booking_calendar_locks WHERE lock_date = ? FOR UPDATE');
        $stmt2->execute([$targetDate]);
        $row = $stmt2->fetch(\PDO::FETCH_ASSOC);
        $conn2Pdo->commit();

        $this->assertNotEmpty($row);
        $this->assertEquals($targetDate, $row['lock_date']);
    }

    public function test_concurrent_booking_collision_is_rejected_authoritatively(): void
    {
        $slotStartUtc = CarbonImmutable::now('UTC')->addDays(5)->setTime(10, 0);
        $slotEndUtc = $slotStartUtc->addMinutes(60);

        // Booking 1 succeeds
        $booking1 = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'customer_name' => 'First Student',
            'customer_email' => 'first@boltlanding.test',
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_timezone' => 'UTC',
            'idempotency_key' => 'unique-key-concurrent-1',
        ], isTrustedAdmin: true);

        $this->assertNotNull($booking1->id);

        // Booking 2 attempting exact same time slot is authoritatively rejected
        $this->expectException(SlotUnavailableException::class);

        $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'customer_name' => 'Second Student',
            'customer_email' => 'second@boltlanding.test',
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_timezone' => 'UTC',
            'idempotency_key' => 'unique-key-concurrent-2',
        ], isTrustedAdmin: true);
    }
}
