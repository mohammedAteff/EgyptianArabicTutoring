<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityException;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\HasPublishedShortForm;
use Tests\TestCase;

class CanonicalAvailabilityValidationTest extends TestCase
{
    use HasPublishedShortForm;
    use RefreshDatabase;

    protected AvailabilityService $availabilityService;

    protected BookingHoldService $holdService;

    protected BookingService $bookingService;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installShortFormFixture();
        $this->availabilityService = app(AvailabilityService::class);
        $this->holdService = app(BookingHoldService::class);
        $this->bookingService = app(BookingService::class);

        $this->sessionType = SessionType::create([
            'title' => 'Standard Arabic 50m',
            'slug' => 'standard-arabic-50m',
            'duration_minutes' => 50,
            'price' => 30.00,
            'currency' => 'USD',
            'active' => true,
        ]);
    }

    public function test_no_rules_configured_rejects_arbitrary_future_slots(): void
    {
        // Zero rules exist in DB
        $this->assertEquals(0, AvailabilityRule::count());

        $futureMonday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::MONDAY)->setTime(10, 0);
        $startUtc = $futureMonday->setTimezone('UTC');
        $endUtc = $startUtc->addMinutes(50);

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('The tutor is not available on this day.');

        $this->availabilityService->validateSlotForBooking(
            sessionType: $this->sessionType,
            startUtc: $startUtc,
            endUtc: $endUtc
        );
    }

    public function test_disabled_only_rules_reject_slots(): void
    {
        $futureMonday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::MONDAY)->setTime(10, 0);

        // Monday rule exists but enabled is false
        AvailabilityRule::create([
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 0,
            'enabled' => false,
        ]);

        $startUtc = $futureMonday->setTimezone('UTC');
        $endUtc = $startUtc->addMinutes(50);

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('The tutor is not available on this day.');

        $this->availabilityService->validateSlotForBooking(
            sessionType: $this->sessionType,
            startUtc: $startUtc,
            endUtc: $endUtc
        );
    }

    public function test_off_grid_start_time_is_rejected(): void
    {
        $futureMonday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::MONDAY)->startOfDay();

        // 09:00 - 13:00, 50m session + 10m buffer = 60m grid step.
        // Valid slots: 09:00, 10:00, 11:00, 12:00.
        AvailabilityRule::create([
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 0,
            'enabled' => true,
        ]);

        // Attempt to book 09:17 (off-grid)
        $offGridStart = $futureMonday->setTime(9, 17)->setTimezone('UTC');
        $offGridEnd = $offGridStart->addMinutes(50);

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('The requested time is outside available tutoring hours or does not align with the schedule grid.');

        $this->availabilityService->validateSlotForBooking(
            sessionType: $this->sessionType,
            startUtc: $offGridStart,
            endUtc: $offGridEnd
        );
    }

    public function test_rule_duration_override_enforces_rule_duration_and_rejects_client_mismatch(): void
    {
        $futureMonday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::MONDAY)->startOfDay();

        // Rule overrides session duration to 30 mins
        AvailabilityRule::create([
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'session_duration_minutes' => 30,
            'buffer_minutes' => 10,
            'min_notice_hours' => 0,
            'enabled' => true,
        ]);

        $slotStart = $futureMonday->setTime(9, 0)->setTimezone('UTC');
        $validEnd = $slotStart->addMinutes(30);

        // 1. Valid 30-min slot succeeds in resolveSlotConfiguration
        $config = $this->availabilityService->resolveSlotConfiguration(
            sessionType: $this->sessionType,
            startUtc: $slotStart,
            endUtc: $validEnd
        );
        $this->assertEquals(30, $config['duration_minutes']);

        // 2. Client sending 50 mins (sessionType duration) is rejected because rule overrides to 30
        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('Slot duration does not match the required session duration (30 mins).');

        $this->availabilityService->validateSlotForBooking(
            sessionType: $this->sessionType,
            startUtc: $slotStart,
            endUtc: $slotStart->addMinutes(50)
        );
    }

    public function test_rule_specific_notice_and_horizon_are_enforced(): void
    {
        $businessTz = 'Africa/Cairo';
        $soonSlot = CarbonImmutable::now('UTC')->addHours(5)->startOfHour();
        $slotWeekday = $soonSlot->setTimezone($businessTz)->dayOfWeek;

        AvailabilityRule::create([
            'weekday' => $slotWeekday,
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 24, // Requires 24 hours advance notice
            'max_horizon_days' => 14, // Allows max 14 days in advance
            'enabled' => true,
        ]);

        // 1. Slot within 24 hours is rejected
        try {
            $this->availabilityService->resolveSlotConfiguration(
                sessionType: $this->sessionType,
                startUtc: $soonSlot
            );
            $this->fail('Expected minimum notice rejection.');
        } catch (SlotUnavailableException $e) {
            $this->assertStringContainsString('at least 24 hours advance notice', $e->getMessage());
        }

        // 2. Slot beyond 14 days is rejected
        $farDay = CarbonImmutable::now($businessTz)->addDays(20);
        AvailabilityRule::create([
            'weekday' => $farDay->dayOfWeek,
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 0,
            'max_horizon_days' => 14,
            'enabled' => true,
        ]);

        $farSlot = $farDay->setTime(10, 0)->setTimezone('UTC');
        try {
            $this->availabilityService->resolveSlotConfiguration(
                sessionType: $this->sessionType,
                startUtc: $farSlot
            );
            $this->fail('Expected maximum horizon rejection.');
        } catch (SlotUnavailableException $e) {
            $this->assertStringContainsString('up to 14 days in advance', $e->getMessage());
        }
    }

    public function test_multiple_weekday_intervals_are_supported(): void
    {
        $futureMonday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::MONDAY)->startOfDay();

        // Interval 1: 09:00 - 11:00 (slots: 09:00)
        AvailabilityRule::create([
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 0,
            'enabled' => true,
        ]);

        // Interval 2: 14:00 - 17:00 (slots: 14:00, 15:00, 16:00)
        AvailabilityRule::create([
            'weekday' => 1,
            'start_time' => '14:00:00',
            'end_time' => '17:00:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 0,
            'enabled' => true,
        ]);

        // Interval 1 slot (09:00) succeeds
        $slot1 = $futureMonday->setTime(9, 0)->setTimezone('UTC');
        $this->availabilityService->validateSlotForBooking($this->sessionType, $slot1, $slot1->addMinutes(50));

        // Interval 2 slot (15:00) succeeds
        $slot2 = $futureMonday->setTime(15, 0)->setTimezone('UTC');
        $this->availabilityService->validateSlotForBooking($this->sessionType, $slot2, $slot2->addMinutes(50));

        // Slot between intervals (12:00) fails
        $slotBetween = $futureMonday->setTime(12, 0)->setTimezone('UTC');
        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('outside available tutoring hours');
        $this->availabilityService->validateSlotForBooking($this->sessionType, $slotBetween, $slotBetween->addMinutes(50));
    }

    public function test_blocked_date_exception_rejects_slot(): void
    {
        $futureMonday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::MONDAY)->startOfDay();

        AvailabilityRule::create([
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 0,
            'enabled' => true,
        ]);

        AvailabilityException::create([
            'date' => $futureMonday->toDateString(),
            'type' => 'blocked',
            'notes' => 'Holiday',
        ]);

        $slot = $futureMonday->setTime(10, 0)->setTimezone('UTC');

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('This date is blocked for appointments.');

        $this->availabilityService->validateSlotForBooking(
            sessionType: $this->sessionType,
            startUtc: $slot,
            endUtc: $slot->addMinutes(50)
        );
    }

    public function test_special_hours_exception_overrides_weekly_rules(): void
    {
        $futureMonday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::MONDAY)->startOfDay();

        // Normal Monday: 09:00 - 12:00
        AvailabilityRule::create([
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 0,
            'enabled' => true,
        ]);

        // Special hours for this date: 18:00 - 21:00
        AvailabilityException::create([
            'date' => $futureMonday->toDateString(),
            'type' => 'special_hours',
            'start_time' => '18:00:00',
            'end_time' => '21:00:00',
            'notes' => 'Evening session',
        ]);

        // 1. Slot during special hours (18:00) succeeds
        $specialSlot = $futureMonday->setTime(18, 0)->setTimezone('UTC');
        $this->availabilityService->validateSlotForBooking($this->sessionType, $specialSlot, $specialSlot->addMinutes(50));

        // 2. Slot during normal Monday hours (09:00) fails because special hours replaced it
        $normalSlot = $futureMonday->setTime(9, 0)->setTimezone('UTC');
        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('outside available tutoring hours');
        $this->availabilityService->validateSlotForBooking($this->sessionType, $normalSlot, $normalSlot->addMinutes(50));
    }

    public function test_exact_interval_boundary_fits_and_exceeding_fails(): void
    {
        $futureMonday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::MONDAY)->startOfDay();

        // 09:00 - 10:00 exactly 60 minutes with 0 buffer
        $customSession = SessionType::create([
            'title' => 'Exact 60m',
            'slug' => 'exact-60m',
            'duration_minutes' => 60,
            'price' => 50.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        AvailabilityRule::create([
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'session_duration_minutes' => 60,
            'buffer_minutes' => 0,
            'min_notice_hours' => 0,
            'enabled' => true,
        ]);

        // Slot 09:00 - 10:00 fits exactly
        $slotStart = $futureMonday->setTime(9, 0)->setTimezone('UTC');
        $slotEnd = $slotStart->addMinutes(60);
        $this->availabilityService->validateSlotForBooking($customSession, $slotStart, $slotEnd);

        // Next candidate slot starting at 10:00 would end at 11:00 > 10:00 interval end -> rejected
        $overSlot = $futureMonday->setTime(10, 0)->setTimezone('UTC');
        $this->expectException(SlotUnavailableException::class);
        $this->availabilityService->validateSlotForBooking($customSession, $overSlot, $overSlot->addMinutes(60));
    }

    public function test_displayed_slot_can_always_be_held_and_finalized(): void
    {
        $futureMonday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::MONDAY)->startOfDay();

        AvailabilityRule::create([
            'weekday' => 1,
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 0,
            'max_horizon_days' => 30,
            'enabled' => true,
        ]);

        // 1. Generate available slots for Monday
        $grouped = $this->availabilityService->getAvailableSlotsGroupedByDate(
            sessionType: $this->sessionType,
            customerTimezone: 'Africa/Cairo',
            fromDate: $futureMonday,
            toDate: $futureMonday
        );

        $dateKey = $futureMonday->toDateString();
        $this->assertArrayHasKey($dateKey, $grouped);
        $this->assertNotEmpty($grouped[$dateKey]);

        // Pick first displayed slot
        $slot = $grouped[$dateKey][0];
        $slotStart = CarbonImmutable::parse($slot['slot_start_utc']);
        $slotEnd = CarbonImmutable::parse($slot['slot_end_utc']);

        // 2. Visitor acquires hold on this displayed slot
        $visitorToken = 'visitor-fidelity-test';
        $sessionToken = 'session-fidelity-test';
        $hold = $this->holdService->acquireHold(
            visitorToken: $visitorToken,
            sessionToken: $sessionToken,
            sessionType: $this->sessionType,
            startUtc: $slotStart,
            endUtc: $slotEnd
        );

        $this->assertInstanceOf(BookingHold::class, $hold);
        $this->assertEquals('active', $hold->status);

        // 3. Visitor completes public booking with hold
        $booking = $this->bookingService->createPublicBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStart,
            'end_at_utc' => $slotEnd,
            'customer_timezone' => 'Africa/Cairo',
            'customer_name' => 'Fidelity Test Student',
            'first_name' => 'Fidelity',
            'last_name' => 'Test Student',
            'customer_email' => 'student@fidelity.test',
            'customer_phone' => '+201000000006',
            'date_of_birth' => '1990-01-01',
            'hold_id' => $hold->id,
            'hold_token' => $hold->hold_token,
            'visitor_token' => $visitorToken,
            'session_token' => $sessionToken,
            'idempotency_key' => 'idemp-fidelity-1',
        ]);

        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertEquals('confirmed', $booking->status);
        $this->assertEquals('converted', $hold->fresh()->status);
    }
}
