<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Booking\Services\CancellationService;
use App\Domains\Booking\Services\IcsGenerator;
use App\Domains\Booking\Services\RescheduleService;
use App\Domains\Contacts\Models\Contact;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected BookingService $bookingService;

    protected BookingHoldService $holdService;

    protected RescheduleService $rescheduleService;

    protected CancellationService $cancellationService;

    protected IcsGenerator $icsGenerator;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookingService = app(BookingService::class);
        $this->holdService = app(BookingHoldService::class);
        $this->rescheduleService = app(RescheduleService::class);
        $this->cancellationService = app(CancellationService::class);
        $this->icsGenerator = app(IcsGenerator::class);

        $this->sessionType = SessionType::create([
            'title' => '1-on-1 Tutoring',
            'slug' => 'one-on-one',
            'duration_minutes' => 60,
            'price' => 35.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        for ($w = 0; $w <= 6; $w++) {
            AvailabilityRule::create([
                'weekday' => $w,
                'start_time' => '00:00:00',
                'end_time' => '23:59:00',
                'session_duration_minutes' => 60,
                'buffer_minutes' => 0,
                'min_notice_hours' => 0,
                'max_horizon_days' => 60,
                'enabled' => true,
            ]);
        }
    }

    public function test_creates_booking_with_complete_immutable_timezone_snapshots_and_booking_event(): void
    {
        $slotStartUtc = CarbonImmutable::now('UTC')->addDays(2)->setTime(12, 0, 0);
        $slotEndUtc = $slotStartUtc->addHours(1);

        $booking = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_timezone' => 'America/New_York',
            'customer_name' => 'Alice Miller',
            'customer_email' => 'Alice.Miller@Example.com',
            'customer_phone' => '+1 555 123 4567',
            'notes' => 'Beginner looking to learn Egyptian conversational basics.',
            'idempotency_key' => 'unique-key-1001',
        ], isTrustedAdmin: true);

        $this->assertNotNull($booking->id);
        $this->assertEquals('confirmed', $booking->status);
        $this->assertNotEmpty($booking->confirmation_token);

        // Verify Contact was normalized & resolved
        $contact = $booking->contact;
        $this->assertNotNull($contact);
        $this->assertEquals('alice.miller@example.com', $contact->email);
        $this->assertEquals('Alice.Miller@Example.com', $contact->display_email);

        // Verify Timezone Snapshots
        $this->assertEquals('America/New_York', $booking->customer_timezone);
        $this->assertEquals('Africa/Cairo', $booking->business_timezone);
        $this->assertNotNull($booking->customer_local_date_at_booking);
        $this->assertNotNull($booking->customer_local_start_time_at_booking);
        $this->assertNotNull($booking->customer_local_end_time_at_booking);
        $this->assertNotNull($booking->business_local_date_at_booking);
        $this->assertNotNull($booking->business_local_start_time_at_booking);
        $this->assertNotNull($booking->business_local_end_time_at_booking);

        // Verify BookingEvent
        $this->assertDatabaseHas('booking_events', [
            'booking_id' => $booking->id,
            'event_type' => 'created',
        ]);
    }

    public function test_booking_idempotency_returns_existing_booking_without_duplicate(): void
    {
        $slotStartUtc = CarbonImmutable::now('UTC')->addDays(3)->setTime(14, 0, 0);
        $slotEndUtc = $slotStartUtc->addHours(1);

        $payload = [
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_timezone' => 'Europe/Berlin',
            'customer_name' => 'Hans Weber',
            'customer_email' => 'hans.weber@example.de',
            'idempotency_key' => 'idemp-duplicate-test-1',
        ];

        $firstBooking = $this->bookingService->createBooking($payload, isTrustedAdmin: true);
        $secondBooking = $this->bookingService->createBooking($payload, isTrustedAdmin: true);

        $this->assertEquals($firstBooking->id, $secondBooking->id);
        $this->assertEquals(1, Booking::query()->where('idempotency_key', 'idemp-duplicate-test-1')->count());
    }

    public function test_concurrent_collision_throws_slot_unavailable_exception(): void
    {
        $slotStartUtc = CarbonImmutable::now('UTC')->addDays(4)->setTime(10, 0, 0);
        $slotEndUtc = $slotStartUtc->addHours(1);

        // First user books successfully
        $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_timezone' => 'Europe/London',
            'customer_name' => 'User One',
            'customer_email' => 'user1@example.com',
            'idempotency_key' => 'user1-key',
        ], isTrustedAdmin: true);

        // Second user attempts to book the overlapping slot
        $this->expectException(SlotUnavailableException::class);

        $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_timezone' => 'Europe/London',
            'customer_name' => 'User Two',
            'customer_email' => 'user2@example.com',
            'idempotency_key' => 'user2-key',
        ], isTrustedAdmin: true);
    }

    public function test_hold_acquisition_and_auto_conversion(): void
    {
        $slotStartUtc = CarbonImmutable::now('UTC')->addDays(5)->setTime(11, 0, 0);
        $slotEndUtc = $slotStartUtc->addHours(1);
        $visitorToken = 'visitor-hold-token-123';

        // 1. Acquire hold
        $hold = $this->holdService->acquireHold(
            visitorToken: $visitorToken,
            sessionToken: 'session-token-abc',
            sessionType: $this->sessionType,
            startUtc: $slotStartUtc,
            endUtc: $slotEndUtc,
            durationMinutes: 10
        );

        $this->assertDatabaseHas('booking_holds', [
            'id' => $hold->id,
            'visitor_token' => $visitorToken,
            'status' => 'active',
        ]);

        // Another visitor attempting to acquire the same slot should fail
        $this->expectException(SlotUnavailableException::class);
        $this->holdService->acquireHold(
            visitorToken: 'different-visitor-token',
            sessionToken: null,
            sessionType: $this->sessionType,
            startUtc: $slotStartUtc,
            endUtc: $slotEndUtc
        );
    }

    public function test_hold_converts_to_converted_when_booking_completes(): void
    {
        $slotStartUtc = CarbonImmutable::now('UTC')->addDays(6)->setTime(13, 0, 0);
        $slotEndUtc = $slotStartUtc->addHours(1);
        $visitorToken = 'visitor-convert-token';

        $hold = $this->holdService->acquireHold(
            visitorToken: $visitorToken,
            sessionToken: 'session-convert-123',
            sessionType: $this->sessionType,
            startUtc: $slotStartUtc,
            endUtc: $slotEndUtc
        );

        $booking = $this->bookingService->createPublicBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_timezone' => 'Africa/Cairo',
            'first_name' => 'Converting',
            'last_name' => 'User',
            'customer_name' => 'Converting User',
            'email' => 'convert@example.com',
            'customer_email' => 'convert@example.com',
            'phone' => '+201000000000',
            'date_of_birth' => '1990-01-01',
            'visitor_token' => $visitorToken,
            'session_token' => 'session-convert-123',
            'hold_id' => $hold->id,
            'hold_token' => $hold->hold_token,
            'idempotency_key' => 'convert-key-99',
        ]);

        $this->assertDatabaseHas('booking_holds', [
            'id' => $hold->id,
            'status' => 'converted',
        ]);
    }

    public function test_rescheduling_service_updates_slot_and_records_event(): void
    {
        $origStartUtc = CarbonImmutable::now('UTC')->addDays(7)->setTime(10, 0, 0);
        $origEndUtc = $origStartUtc->addHours(1);

        $booking = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $origStartUtc,
            'end_at_utc' => $origEndUtc,
            'customer_timezone' => 'Africa/Cairo',
            'customer_name' => 'Reschedule User',
            'customer_email' => 'resched@example.com',
            'idempotency_key' => 'resched-key-1',
        ], isTrustedAdmin: true);

        $newStartUtc = CarbonImmutable::now('UTC')->addDays(8)->setTime(15, 0, 0);
        $newEndUtc = $newStartUtc->addHours(1);

        $rescheduled = $this->rescheduleService->reschedule(
            booking: $booking,
            newStartUtc: $newStartUtc,
            newEndUtc: $newEndUtc,
            performedBy: 'customer',
            reason: 'Schedule conflict on original date'
        );

        $this->assertEquals($newStartUtc->toDateTimeString(), $rescheduled->start_at_utc->toDateTimeString());
        $this->assertEquals($newEndUtc->toDateTimeString(), $rescheduled->end_at_utc->toDateTimeString());

        // Verify BookingEvent was recorded
        $this->assertDatabaseHas('booking_events', [
            'booking_id' => $booking->id,
            'event_type' => 'rescheduled',
            'performed_by' => 'customer',
        ]);
    }

    public function test_cancellation_service_updates_status_and_frees_slot(): void
    {
        $slotStartUtc = CarbonImmutable::now('UTC')->addDays(9)->setTime(12, 0, 0);
        $slotEndUtc = $slotStartUtc->addHours(1);

        $booking = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_timezone' => 'Africa/Cairo',
            'customer_name' => 'Cancel User',
            'customer_email' => 'cancel@example.com',
            'idempotency_key' => 'cancel-key-1',
        ], isTrustedAdmin: true);

        $cancelled = $this->cancellationService->cancel(
            booking: $booking,
            performedBy: 'customer',
            reason: 'No longer needed'
        );

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertEquals('No longer needed', $cancelled->cancellation_reason);

        // Verify event
        $this->assertDatabaseHas('booking_events', [
            'booking_id' => $booking->id,
            'event_type' => 'cancelled',
            'performed_by' => 'customer',
        ]);
    }

    public function test_ics_calendar_generator_produces_rfc5545_compliant_vcalendar(): void
    {
        $slotStartUtc = CarbonImmutable::now('UTC')->addDays(10)->setTime(16, 0, 0);
        $slotEndUtc = $slotStartUtc->addHours(1);

        $booking = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_timezone' => 'America/New_York',
            'customer_name' => 'Calendar User',
            'customer_email' => 'cal@example.com',
            'idempotency_key' => 'cal-key-1',
        ], isTrustedAdmin: true);

        $icsContent = $this->icsGenerator->generate($booking);

        $this->assertStringContainsString('BEGIN:VCALENDAR', $icsContent);
        $this->assertStringContainsString('VERSION:2.0', $icsContent);
        $this->assertStringContainsString('BEGIN:VEVENT', $icsContent);
        $this->assertStringContainsString('UID:booking-'.$booking->confirmation_token, $icsContent);
        $this->assertStringContainsString('DTSTART:'.$slotStartUtc->format('Ymd\THis\Z'), $icsContent);
        $this->assertStringContainsString('DTEND:'.$slotEndUtc->format('Ymd\THis\Z'), $icsContent);
        $this->assertStringContainsString('SUMMARY:1-on-1 Tutoring', $icsContent);
        $this->assertStringContainsString('END:VEVENT', $icsContent);
        $this->assertStringContainsString('END:VCALENDAR', $icsContent);
    }

    public function test_booking_creation_enforces_buffer_between_sessions(): void
    {
        $slotStartUtc = CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0, 0);
        $slotEndUtc = $slotStartUtc->addMinutes(60);

        // Configure rule with 15m buffer and two intervals so both 10:00 and 11:00 UTC align on grid
        AvailabilityRule::query()->delete();
        $startBusiness = $slotStartUtc->setTimezone('Africa/Cairo');
        $weekday = $startBusiness->dayOfWeek;
        $splitTime = $startBusiness->addMinutes(60)->format('H:i:s');

        AvailabilityRule::create([
            'weekday' => $weekday,
            'start_time' => $startBusiness->format('H:i:s'),
            'end_time' => $splitTime,
            'session_duration_minutes' => 60,
            'buffer_minutes' => 15,
            'min_notice_hours' => 0,
            'max_horizon_days' => 60,
            'enabled' => true,
        ]);
        AvailabilityRule::create([
            'weekday' => $weekday,
            'start_time' => $splitTime,
            'end_time' => $startBusiness->addHours(4)->format('H:i:s'),
            'session_duration_minutes' => 60,
            'buffer_minutes' => 15,
            'min_notice_hours' => 0,
            'max_horizon_days' => 60,
            'enabled' => true,
        ]);

        // First booking: 10:00 - 11:00 UTC
        $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_timezone' => 'UTC',
            'customer_name' => 'First User',
            'customer_email' => 'first@example.com',
            'idempotency_key' => 'buffer-user-1',
        ], isTrustedAdmin: true);

        // Second booking immediately adjacent: 11:00 - 12:00 UTC (violates 15-minute buffer)
        $adjacentStart = $slotEndUtc;
        $adjacentEnd = $adjacentStart->addMinutes(60);

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('conflicts with the required buffer time between sessions');

        $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $adjacentStart,
            'end_at_utc' => $adjacentEnd,
            'customer_timezone' => 'UTC',
            'customer_name' => 'Second User',
            'customer_email' => 'second@example.com',
            'idempotency_key' => 'buffer-user-2',
        ], isTrustedAdmin: true);
    }

    public function test_booking_creation_rejects_tampered_duration(): void
    {
        $slotStartUtc = CarbonImmutable::now('UTC')->addDays(3)->setTime(14, 0, 0);
        // Attempt to book 30 minutes instead of the required 60 minutes
        $tamperedEndUtc = $slotStartUtc->addMinutes(30);

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('Slot duration does not match the required session duration (60 mins)');

        $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $tamperedEndUtc,
            'customer_timezone' => 'UTC',
            'customer_name' => 'Tamper User',
            'customer_email' => 'tamper@example.com',
            'idempotency_key' => 'tamper-duration-1',
        ], isTrustedAdmin: true);
    }

    public function test_booking_creation_rejects_mismatched_hold_token(): void
    {
        $slotStartUtc = CarbonImmutable::now('UTC')->addDays(6)->setTime(12, 0, 0);
        $slotEndUtc = $slotStartUtc->addMinutes(60);

        $hold = $this->holdService->acquireHold(
            visitorToken: 'vis-token-real',
            sessionToken: 'sess-real',
            sessionType: $this->sessionType,
            startUtc: $slotStartUtc,
            endUtc: $slotEndUtc
        );

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('Invalid reservation hold authentication');

        $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_timezone' => 'UTC',
            'customer_name' => 'Spoof User',
            'customer_email' => 'spoof@example.com',
            'idempotency_key' => 'spoof-hold-key',
            'hold_id' => $hold->id,
            'hold_token' => 'spoofed-fake-token-value',
            'visitor_token' => 'vis-token-real',
            'session_token' => 'sess-real',
        ]);
    }

    public function test_booking_creation_rejects_off_hours_when_rules_exist(): void
    {
        AvailabilityRule::query()->delete();

        // Add weekly rule for Monday: 09:00 - 13:00 Cairo time
        AvailabilityRule::create([
            'weekday' => 1, // Monday
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'enabled' => true,
        ]);

        // Next Monday at 15:00 Cairo time (outside 09:00-13:00)
        $nextMonday = CarbonImmutable::now('Africa/Cairo')->next(CarbonImmutable::MONDAY)->setTime(15, 0, 0);
        $startUtc = $nextMonday->setTimezone('UTC');
        $endUtc = $startUtc->addMinutes(60);

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('The requested time is outside available tutoring hours');

        $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $startUtc,
            'end_at_utc' => $endUtc,
            'customer_timezone' => 'Africa/Cairo',
            'customer_name' => 'Off Hours User',
            'customer_email' => 'offhours@example.com',
            'idempotency_key' => 'off-hours-1',
        ], isTrustedAdmin: true);
    }
}
