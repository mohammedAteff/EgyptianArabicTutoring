<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Availability\Services\SlotResolver;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\CancellationService;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentBookingService;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentCreditBookingTest extends TestCase
{
    use RefreshDatabase;

    private SessionType $sessionType;

    private CarbonImmutable $availableDate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionType = SessionType::query()->create([
            'title' => 'Student Arabic Lesson',
            'slug' => 'student-arabic-lesson',
            'duration_minutes' => 60,
            'price' => '40.00',
            'currency' => 'USD',
            'active' => true,
        ]);
        $this->availableDate = CarbonImmutable::now('Africa/Cairo')->addDays(7)->startOfDay();
        AvailabilityRule::query()->create([
            'weekday' => $this->availableDate->dayOfWeek,
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
            'session_duration_minutes' => 60,
            'buffer_minutes' => 0,
            'min_notice_hours' => 0,
            'max_horizon_days' => 60,
            'enabled' => true,
        ]);
    }

    public function test_confirmed_student_booking_consumes_one_fifo_credit_and_replays_idempotently(): void
    {
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $earlierPackage = $ledger->createPackage($student, 'Soonest expiry', 2, '80.00', '0.00', 'USD', now('Africa/Cairo')->addDays(3)->toDateString(), 'grant-earlier');
        $laterPackage = $ledger->createPackage($student, 'Later expiry', 5, '200.00', '0.00', 'USD', now('Africa/Cairo')->addDays(10)->toDateString(), 'grant-later');
        [$slotId, $slot, $ownerToken] = $this->slotForStudent($student);

        $service = app(StudentBookingService::class);
        $booking = $service->create($student, $slotId, $this->sessionType->id, 'Africa/Cairo', 'student-booking-key-000000000000000000000001', $ownerToken);

        $this->assertSame($student->id, $booking->student_id);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame($slot['slot_start_utc'], $booking->start_at_utc->toDateTimeString());
        $this->assertSame($earlierPackage->id, SessionLedgerEntry::query()->where('booking_id', $booking->id)->where('entry_type', 'session_consumed')->value('student_package_id'));
        $this->assertSame(1, (int) $ledger->summary($earlierPackage->fresh())['remaining_credits']);
        $this->assertSame(5, (int) $ledger->summary($laterPackage->fresh())['remaining_credits']);

        $replay = $service->create($student, 'expired-or-different-token-is-ignored-on-replay', $this->sessionType->id, 'Africa/Cairo', 'student-booking-key-000000000000000000000001', $ownerToken);
        $this->assertSame($booking->id, $replay->id);
        $this->assertSame(1, SessionLedgerEntry::query()->where('booking_id', $booking->id)->where('entry_type', 'session_consumed')->count());
        $this->assertSame(1, Booking::query()->where('idempotency_key', 'student-booking-key-000000000000000000000001')->count());
    }

    public function test_student_booking_acquires_lock_tiers_in_canonical_order(): void
    {
        $student = Student::factory()->verified()->create();
        app(StudentLedgerService::class)->createPackage($student, 'Lock-order package', 2, '80.00', '0.00', 'USD', null, 'lock-order-booking-grant');
        [$slotId, , $ownerToken] = $this->slotForStudent($student);
        $lockedTiers = [];
        $tierTables = ['booking_calendar_locks', 'students', 'bookings', 'student_packages', 'session_ledger_entries'];

        DB::listen(function (QueryExecuted $query) use (&$lockedTiers, $tierTables): void {
            $sql = strtolower($query->sql);
            if (! str_contains($sql, 'for update')) {
                return;
            }

            foreach ($tierTables as $table) {
                if (preg_match('/\\bfrom\\s+[`"]?'.preg_quote($table, '/').'[`"]?(?:\\s|$)/', $sql) === 1) {
                    $lockedTiers[] = $table;
                    break;
                }
            }
        });

        $booking = app(StudentBookingService::class)->create(
            $student,
            $slotId,
            $this->sessionType->id,
            'Africa/Cairo',
            'student-lock-order-idempotency-key',
            $ownerToken,
        );

        $this->assertSame($tierTables, array_values(array_unique($lockedTiers)));
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_credit_failure_rolls_back_the_booking_contact_and_booking_event(): void
    {
        $student = Student::factory()->verified()->create();
        [$slotId, , $ownerToken] = $this->slotForStudent($student);

        try {
            app(StudentBookingService::class)->create($student, $slotId, $this->sessionType->id, 'Africa/Cairo', 'student-booking-key-000000000000000000000002', $ownerToken);
            $this->fail('A student without credits must not receive a confirmed booking.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('No available session credits.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('bookings', ['idempotency_key' => 'student-booking-key-000000000000000000000002']);
        $this->assertDatabaseMissing('contacts', ['email' => 'student-'.$student->id.'@internal.invalid']);
        $this->assertSame(0, BookingEvent::query()->count());
        $this->assertSame(0, SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->count());
    }

    public function test_student_cancellation_restores_credit_once_and_replays_as_an_idempotent_state(): void
    {
        $student = Student::factory()->verified()->create();
        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage($student, 'Cancellation package', 2, '80.00', '0.00', 'USD', null, 'grant-cancel-once');
        [$slotId, , $ownerToken] = $this->slotForStudent($student);
        $booking = app(StudentBookingService::class)->create(
            $student,
            $slotId,
            $this->sessionType->id,
            'Africa/Cairo',
            'student-cancellation-idempotency-key',
            $ownerToken,
        );

        $cancellations = app(CancellationService::class);
        $cancelled = $cancellations->cancel($booking, performedBy: 'customer', reason: 'Student changed plans');
        $replayed = $cancellations->cancel($booking, performedBy: 'customer', reason: 'Student changed plans');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame($cancelled->id, $replayed->id);
        $this->assertSame(2, $ledger->summary($package->fresh())['remaining_credits']);
        $this->assertSame(1, SessionLedgerEntry::query()
            ->where('booking_id', $booking->id)
            ->where('entry_type', 'cancellation_restore')
            ->count());
        $this->assertSame(1, BookingEvent::query()->where('booking_id', $booking->id)->where('event_type', 'cancelled')->count());
    }

    public function test_tampered_encrypted_slot_identity_is_rejected_before_booking_or_credit_mutation(): void
    {
        $student = Student::factory()->verified()->create();
        app(StudentLedgerService::class)->createPackage($student, 'Eight lessons', 8, '320.00', '0.00', 'USD', null, 'grant-tamper');
        [$slotId, , $ownerToken] = $this->slotForStudent($student);

        try {
            app(StudentBookingService::class)->create($student, $slotId.'tamper', $this->sessionType->id, 'Africa/Cairo', 'student-booking-key-000000000000000000000003', $ownerToken);
            $this->fail('Tampering with a server-issued slot token must be rejected.');
        } catch (SlotUnavailableException) {
            $this->assertTrue(true);
        }

        $this->assertSame(0, Booking::query()->count());
        $this->assertSame(0, SessionLedgerEntry::query()->where('entry_type', 'session_consumed')->count());
    }

    /** @return array{string, array<string, mixed>, string} */
    private function slotForStudent(Student $student): array
    {
        $ownerToken = hash_hmac('sha256', 'student-booking:'.$student->id.':test-session', (string) config('app.key'));
        $available = app(AvailabilityService::class)->getAvailableSlotsGroupedByDate(
            sessionType: $this->sessionType,
            customerTimezone: 'Africa/Cairo',
            fromDate: $this->availableDate,
            toDate: $this->availableDate,
            currentVisitorToken: $ownerToken,
        );
        $slot = $available[$this->availableDate->toDateString()][0];

        return [app(SlotResolver::class)->issue($this->sessionType, $slot, 'Africa/Cairo', $ownerToken), $slot, $ownerToken];
    }
}
