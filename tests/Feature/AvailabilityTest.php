<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityException;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Contacts\Models\Contact;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected AvailabilityService $availabilityService;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->availabilityService = app(AvailabilityService::class);

        $this->sessionType = SessionType::create([
            'title' => '1-on-1 Tutoring',
            'slug' => 'one-on-one',
            'duration_minutes' => 60,
            'price' => 35.00,
            'currency' => 'USD',
            'active' => true,
        ]);
    }

    public function test_recurring_weekly_rules_generate_slots_in_customer_timezone(): void
    {
        // Sunday (weekday 0 in Carbon) 09:00 - 11:00 Cairo time
        AvailabilityRule::create([
            'weekday' => 0,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'session_duration_minutes' => 60,
            'buffer_minutes' => 0,
            'min_notice_hours' => 0,
            'enabled' => true,
        ]);

        $nextSunday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::SUNDAY)->setTime(0, 0);

        $slots = $this->availabilityService->getAvailableSlotsGroupedByDate(
            $this->sessionType,
            'Africa/Cairo',
            $nextSunday,
            $nextSunday
        );

        $dateKey = $nextSunday->toDateString();
        $this->assertArrayHasKey($dateKey, $slots);
        $this->assertCount(2, $slots[$dateKey]);

        // In Cairo: 09:00 - 10:00 and 10:00 - 11:00
        $this->assertEquals('09:00', $slots[$dateKey][0]['customer_start_time']);
        $this->assertEquals('10:00', $slots[$dateKey][0]['customer_end_time']);
        $this->assertEquals('10:00', $slots[$dateKey][1]['customer_start_time']);
        $this->assertEquals('11:00', $slots[$dateKey][1]['customer_end_time']);

        // Now query the same Sunday from London (Europe/London is UTC+1 during BST, or UTC+0 during GMT)
        $londonSlots = $this->availabilityService->getAvailableSlotsGroupedByDate(
            $this->sessionType,
            'Europe/London',
            $nextSunday,
            $nextSunday
        );

        $this->assertNotEmpty($londonSlots);
        $firstLondonDay = array_values($londonSlots)[0];
        $this->assertCount(2, $firstLondonDay);
        // Cairo is UTC+3 (standard time) or UTC+2. The UTC times must match between both queries.
        $this->assertEquals($slots[$dateKey][0]['slot_start_utc'], $firstLondonDay[0]['slot_start_utc']);
    }

    public function test_blocked_date_exception_prevents_slot_generation(): void
    {
        AvailabilityRule::create([
            'weekday' => 0,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'session_duration_minutes' => 60,
            'buffer_minutes' => 0,
            'min_notice_hours' => 0,
            'enabled' => true,
        ]);

        $nextSunday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::SUNDAY)->setTime(0, 0);

        // Block this Sunday
        AvailabilityException::create([
            'date' => $nextSunday->toDateString(),
            'type' => 'blocked',
            'reason' => 'National Holiday',
        ]);

        $slots = $this->availabilityService->getAvailableSlotsGroupedByDate(
            $this->sessionType,
            'Africa/Cairo',
            $nextSunday,
            $nextSunday
        );

        $this->assertEmpty($slots);
    }

    public function test_special_hours_exception_overrides_weekly_rules(): void
    {
        AvailabilityRule::create([
            'weekday' => 0,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'session_duration_minutes' => 60,
            'buffer_minutes' => 0,
            'min_notice_hours' => 0,
            'enabled' => true,
        ]);

        $nextSunday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::SUNDAY)->setTime(0, 0);

        // Override with custom evening hours 18:00 - 20:00
        AvailabilityException::create([
            'date' => $nextSunday->toDateString(),
            'type' => 'special_hours',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => 'Evening only',
        ]);

        $slots = $this->availabilityService->getAvailableSlotsGroupedByDate(
            $this->sessionType,
            'Africa/Cairo',
            $nextSunday,
            $nextSunday
        );

        $dateKey = $nextSunday->toDateString();
        $this->assertArrayHasKey($dateKey, $slots);
        $this->assertCount(2, $slots[$dateKey]);
        $this->assertEquals('18:00', $slots[$dateKey][0]['customer_start_time']);
        $this->assertEquals('19:00', $slots[$dateKey][1]['customer_start_time']);
    }

    public function test_buffer_time_between_slots_is_respected(): void
    {
        AvailabilityRule::create([
            'weekday' => 1, // Monday
            'start_time' => '09:00',
            'end_time' => '12:00',
            'session_duration_minutes' => 60,
            'buffer_minutes' => 15,
            'min_notice_hours' => 0,
            'enabled' => true,
        ]);

        $nextMonday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::MONDAY)->setTime(0, 0);

        $slots = $this->availabilityService->getAvailableSlotsGroupedByDate(
            $this->sessionType,
            'Africa/Cairo',
            $nextMonday,
            $nextMonday
        );

        $dateKey = $nextMonday->toDateString();
        $this->assertArrayHasKey($dateKey, $slots);
        // 09:00 - 10:00 (ends 10:00, next starts 10:15)
        // 10:15 - 11:15 (ends 11:15, next would start 11:30 and end 12:30 > 12:00, so only 2 slots fit)
        $this->assertCount(2, $slots[$dateKey]);
        $this->assertEquals('09:00', $slots[$dateKey][0]['customer_start_time']);
        $this->assertEquals('10:00', $slots[$dateKey][0]['customer_end_time']);
        $this->assertEquals('10:15', $slots[$dateKey][1]['customer_start_time']);
        $this->assertEquals('11:15', $slots[$dateKey][1]['customer_end_time']);
    }

    public function test_active_booking_collision_removes_slot(): void
    {
        AvailabilityRule::create([
            'weekday' => 2, // Tuesday
            'start_time' => '10:00',
            'end_time' => '12:00',
            'session_duration_minutes' => 60,
            'buffer_minutes' => 0,
            'min_notice_hours' => 0,
            'enabled' => true,
        ]);

        $nextTuesday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::TUESDAY)->setTime(0, 0);

        $contact = Contact::create([
            'name' => 'Test Student',
            'email' => 'student@test.com',
            'normalized_email' => 'student@test.com',
        ]);

        $bookingStartCairo = CarbonImmutable::parse($nextTuesday->toDateString().' 10:00:00', 'Africa/Cairo');
        $bookingEndCairo = CarbonImmutable::parse($nextTuesday->toDateString().' 11:00:00', 'Africa/Cairo');

        app(BookingService::class)->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $bookingStartCairo->setTimezone('UTC'),
            'end_at_utc' => $bookingEndCairo->setTimezone('UTC'),
            'customer_timezone' => 'Africa/Cairo',
            'customer_name' => 'Test Student',
            'customer_email' => 'student@test.com',
            'idempotency_key' => 'idemp-1',
        ], isTrustedAdmin: true);

        $slots = $this->availabilityService->getAvailableSlotsGroupedByDate(
            $this->sessionType,
            'Africa/Cairo',
            $nextTuesday,
            $nextTuesday
        );

        $dateKey = $nextTuesday->toDateString();
        $this->assertArrayHasKey($dateKey, $slots);
        // Only 11:00 - 12:00 should remain!
        $this->assertCount(1, $slots[$dateKey]);
        $this->assertEquals('11:00', $slots[$dateKey][0]['customer_start_time']);
    }

    public function test_active_hold_collision_hides_slot_from_other_visitors(): void
    {
        AvailabilityRule::create([
            'weekday' => 3, // Wednesday
            'start_time' => '14:00',
            'end_time' => '16:00',
            'session_duration_minutes' => 60,
            'buffer_minutes' => 0,
            'min_notice_hours' => 0,
            'enabled' => true,
        ]);

        $nextWed = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::WEDNESDAY)->setTime(0, 0);
        $holdStartCairo = CarbonImmutable::parse($nextWed->toDateString().' 14:00:00', 'Africa/Cairo');
        $holdEndCairo = CarbonImmutable::parse($nextWed->toDateString().' 15:00:00', 'Africa/Cairo');

        // Create active hold by Visitor A
        BookingHold::create([
            'session_type_id' => $this->sessionType->id,
            'slot_start_utc' => $holdStartCairo->setTimezone('UTC'),
            'slot_end_utc' => $holdEndCairo->setTimezone('UTC'),
            'visitor_token' => 'visitor-a-token',
            'status' => 'active',
            'expires_at' => CarbonImmutable::now('UTC')->addMinutes(10),
        ]);

        // Visitor B queries slots
        $visitorBSlots = $this->availabilityService->getAvailableSlotsGroupedByDate(
            $this->sessionType,
            'Africa/Cairo',
            $nextWed,
            $nextWed,
            'visitor-b-token'
        );

        $dateKey = $nextWed->toDateString();
        $this->assertCount(1, $visitorBSlots[$dateKey]);
        $this->assertEquals('15:00', $visitorBSlots[$dateKey][0]['customer_start_time']);

        // Visitor A queries slots (passing their own token)
        $visitorASlots = $this->availabilityService->getAvailableSlotsGroupedByDate(
            $this->sessionType,
            'Africa/Cairo',
            $nextWed,
            $nextWed,
            'visitor-a-token'
        );

        // Visitor A should see BOTH slots (their held slot + remaining slot)
        $this->assertCount(2, $visitorASlots[$dateKey]);
    }
}
