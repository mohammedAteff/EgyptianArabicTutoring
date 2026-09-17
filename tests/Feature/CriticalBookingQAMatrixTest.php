<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Availability\Models\AvailabilityException;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Booking\Services\RescheduleService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section 115: Critical Booking QA Matrix
 */
class CriticalBookingQAMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected SessionType $sessionType;

    protected Administrator $admin;

    protected TimezoneService $timezoneService;

    protected BookingHoldService $holdService;

    protected BookingService $bookingService;

    protected RescheduleService $rescheduleService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionType = SessionType::create([
            'title' => 'Conversational Egyptian Arabic (Private)',
            'slug' => 'conversational-egyptian-private',
            'duration_minutes' => 50,
            'price' => 3500,
            'currency' => 'USD',
            'active' => true,
        ]);

        for ($w = 0; $w <= 6; $w++) {
            AvailabilityRule::create([
                'weekday' => $w,
                'start_time' => '00:00:00',
                'end_time' => '23:59:00',
                'session_duration_minutes' => 50,
                'buffer_minutes' => 10,
                'min_notice_hours' => 0,
                'max_horizon_days' => 365,
                'enabled' => true,
            ]);
        }

        $this->admin = Administrator::create([
            'name' => 'Tutor Ahmad',
            'email' => 'tutor@example.com',
            'password' => bcrypt('StrongSecret123!'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->timezoneService = app(TimezoneService::class);
        $this->holdService = app(BookingHoldService::class);
        $this->bookingService = app(BookingService::class);
        $this->rescheduleService = app(RescheduleService::class);
    }

    /**
     * Scenario A: Tutor in Cairo, Student in Berlin.
     * Booking displayed in Berlin time for student and Cairo time for admin.
     */
    public function test_scenario_a_cairo_tutor_and_berlin_student_timezones(): void
    {
        // 14:00 Cairo (UTC+3) -> 11:00 UTC -> 13:00 Berlin (UTC+2)
        $cairoStart = CarbonImmutable::parse('2026-10-12 14:00:00', 'Africa/Cairo');
        $startUtc = $cairoStart->setTimezone('UTC');
        $endUtc = $startUtc->addMinutes(50);

        $booking = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $startUtc,
            'end_at_utc' => $endUtc,
            'customer_name' => 'Hans Müller',
            'customer_email' => 'hans@example.de',
            'customer_timezone' => 'Europe/Berlin',
            'idempotency_key' => 'idem-scenario-a',
            'visitor_token' => 'vis-scenario-a',
            'notes' => 'Preparing for Cairo trip.',
        ], isTrustedAdmin: true);

        // Verification for Student
        $studentLocalStart = $booking->start_at_utc->copy()->setTimezone('Europe/Berlin');
        $this->assertEquals('13:00', $studentLocalStart->format('H:i'));
        $this->assertEquals('Europe/Berlin', $booking->customer_timezone);

        // Verification for Tutor/Admin
        $tutorLocalStart = $booking->start_at_utc->copy()->setTimezone('Africa/Cairo');
        $this->assertEquals('14:00', $tutorLocalStart->format('H:i'));
        $this->assertEquals('Africa/Cairo', $booking->business_timezone);

        // Verify admin show view renders both accurately
        $response = $this->actingAs($this->admin, 'web')->get(route('admin.bookings.show', $booking->id));
        $response->assertStatus(200);
        $response->assertSee('02:00 PM');
        $response->assertSee('01:00 PM');
        $response->assertSee('Europe/Berlin');
    }

    /**
     * Scenario B: Student manually switches Berlin → New York.
     * All subsequent times update accurately.
     */
    public function test_scenario_b_student_manually_switches_berlin_to_new_york(): void
    {
        $slotUtc = CarbonImmutable::parse('2026-10-12 11:00:00', 'UTC');

        // Berlin representation (UTC+2 in October before transition)
        $berlinTime = $slotUtc->setTimezone('Europe/Berlin');
        $this->assertEquals('13:00', $berlinTime->format('H:i'));

        // Student switches timezone to New York (EDT, UTC-4)
        $newYorkTime = $slotUtc->setTimezone('America/New_York');
        $this->assertEquals('07:00', $newYorkTime->format('H:i'));

        // Clock face difference is 6 hours
        $this->assertEquals(6, $berlinTime->hour - $newYorkTime->hour);
    }

    /**
     * Scenario C: Booking crosses midnight between zones.
     * Correct dates appear independently for student and admin.
     */
    public function test_scenario_c_booking_crosses_midnight_between_zones(): void
    {
        // 22:00 Cairo on Oct 15 (UTC+3) -> 19:00 UTC on Oct 15
        $cairoStart = CarbonImmutable::parse('2026-10-15 22:00:00', 'Africa/Cairo');
        $startUtc = $cairoStart->setTimezone('UTC');
        $endUtc = $startUtc->addMinutes(50);

        // Student in Tokyo (UTC+9): 19:00 UTC + 9h = 04:00 NEXT DAY (Oct 16)
        $booking = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $startUtc,
            'end_at_utc' => $endUtc,
            'customer_name' => 'Kenji Sato',
            'customer_email' => 'kenji@example.jp',
            'customer_timezone' => 'Asia/Tokyo',
            'idempotency_key' => 'idem-scenario-c',
            'visitor_token' => 'vis-scenario-c',
        ], isTrustedAdmin: true);

        $this->assertEquals('2026-10-15', $booking->business_local_date_at_booking->format('Y-m-d'));
        $this->assertEquals('2026-10-16', $booking->customer_local_date_at_booking->format('Y-m-d'));
        $this->assertEquals('22:00:00', $booking->business_local_start_time_at_booking);
        $this->assertEquals('04:00:00', $booking->customer_local_start_time_at_booking);
    }

    /**
     * Scenario D: DST transition - UTC anchor prevents slot shifting.
     */
    public function test_scenario_d_dst_transition_anchor_utc_prevents_shifting(): void
    {
        // London DST ends Oct 25, 2026: BST (UTC+1) -> GMT (UTC+0)
        // Lesson 1: Before transition (Oct 24, 2026 at 10:00 UTC -> 11:00 BST)
        $slot1Utc = CarbonImmutable::parse('2026-10-24 10:00:00', 'UTC');
        $booking1 = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slot1Utc,
            'end_at_utc' => $slot1Utc->addMinutes(50),
            'customer_name' => 'Arthur Dent',
            'customer_email' => 'arthur@example.co.uk',
            'customer_timezone' => 'Europe/London',
            'idempotency_key' => 'idem-dst-1',
        ], isTrustedAdmin: true);

        // Lesson 2: After transition (Oct 26, 2026 at 10:00 UTC -> 10:00 GMT)
        $slot2Utc = CarbonImmutable::parse('2026-10-26 10:00:00', 'UTC');
        $booking2 = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slot2Utc,
            'end_at_utc' => $slot2Utc->addMinutes(50),
            'customer_name' => 'Arthur Dent',
            'customer_email' => 'arthur@example.co.uk',
            'customer_timezone' => 'Europe/London',
            'idempotency_key' => 'idem-dst-2',
        ], isTrustedAdmin: true);

        // Canonical UTC times remain exact and immutable
        $this->assertEquals('2026-10-24 10:00:00', $booking1->start_at_utc->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-26 10:00:00', $booking2->start_at_utc->format('Y-m-d H:i:s'));

        // Local historical offsets reflect the correct DST shift (+01:00 then +00:00)
        $this->assertEquals('+01:00', $booking1->customer_utc_offset_at_booking);
        $this->assertEquals('+00:00', $booking2->customer_utc_offset_at_booking);
    }

    /**
     * Scenario E: Two users select same slot. Concurrency / lock protection allows only one.
     */
    public function test_scenario_e_two_users_select_same_slot_concurrency_race(): void
    {
        $slotUtc = CarbonImmutable::parse('2026-10-20 12:00:00', 'UTC');
        $endUtc = $slotUtc->addMinutes(50);

        // User 1 acquires the hold
        $holdUser1 = $this->holdService->acquireHold(
            visitorToken: 'visitor-race-1',
            sessionToken: 'session-race-1',
            sessionType: $this->sessionType,
            startUtc: $slotUtc,
            endUtc: $endUtc
        );
        $this->assertNotNull($holdUser1);

        // User 2 attempts to hold the same slot concurrently -> throws SlotUnavailableException
        $this->expectException(SlotUnavailableException::class);
        $this->holdService->acquireHold(
            visitorToken: 'visitor-race-2',
            sessionToken: 'session-race-2',
            sessionType: $this->sessionType,
            startUtc: $slotUtc,
            endUtc: $endUtc
        );
    }

    /**
     * Scenario F: Visitor refreshes confirmation page. Same booking appears.
     */
    public function test_scenario_f_visitor_refreshes_confirmation_page(): void
    {
        $slotUtc = CarbonImmutable::parse('2026-10-21 14:00:00', 'UTC');
        $booking = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotUtc,
            'end_at_utc' => $slotUtc->addMinutes(50),
            'customer_name' => 'Sophie Bernard',
            'customer_email' => 'sophie@example.fr',
            'customer_timezone' => 'Europe/Paris',
            'idempotency_key' => 'idem-refresh-1',
        ], isTrustedAdmin: true);

        $token = $booking->confirmation_token;

        // First visit
        $resp1 = $this->get(route('booking.confirmation', $token));
        $resp1->assertStatus(200);
        $resp1->assertSee('Booking Confirmed');
        $resp1->assertSee('Sophie Bernard');

        // Refresh 1
        $resp2 = $this->get(route('booking.confirmation', $token));
        $resp2->assertStatus(200);
        $resp2->assertSee('Booking Confirmed');

        // Refresh 2
        $resp3 = $this->get(route('booking.confirmation', $token));
        $resp3->assertStatus(200);
        $resp3->assertSee('Booking Confirmed');

        $this->assertDatabaseCount('bookings', 1);
    }

    /**
     * Scenario G: Duplicate booking submission with same idempotency key results in exactly one booking.
     */
    public function test_scenario_g_duplicate_booking_submission(): void
    {
        $slotUtc = CarbonImmutable::parse('2026-10-22 15:00:00', 'UTC');
        $idempotencyKey = 'idem-duplicate-attempt-999';

        $payload = [
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotUtc,
            'end_at_utc' => $slotUtc->addMinutes(50),
            'customer_name' => 'Elena Rostova',
            'customer_email' => 'elena@example.org',
            'customer_timezone' => 'Europe/Rome',
            'idempotency_key' => $idempotencyKey,
        ];

        $bookingA = $this->bookingService->createBooking($payload, isTrustedAdmin: true);

        // Immediate duplicate submission with identical idempotency key
        $bookingB = $this->bookingService->createBooking($payload, isTrustedAdmin: true);

        $this->assertEquals($bookingA->id, $bookingB->id);
        $this->assertEquals($bookingA->confirmation_token, $bookingB->confirmation_token);
        $this->assertDatabaseCount('bookings', 1);
    }

    /**
     * Scenario H: Admin reschedules. Original time is preserved in booking event history.
     */
    public function test_scenario_h_admin_reschedules_original_time_preserved_in_history(): void
    {
        $originalStart = CarbonImmutable::parse('2026-10-25 10:00:00', 'UTC');
        $booking = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $originalStart,
            'end_at_utc' => $originalStart->addMinutes(50),
            'customer_name' => 'Chloe Dupont',
            'customer_email' => 'chloe@example.com',
            'customer_timezone' => 'Europe/Brussels',
            'idempotency_key' => 'idem-reschedule-h',
        ], isTrustedAdmin: true);

        $newStart = CarbonImmutable::parse('2026-10-27 12:00:00', 'UTC');
        $this->rescheduleService->reschedule(
            booking: $booking,
            newStartUtc: $newStart,
            newEndUtc: $newStart->addMinutes(50),
            performedBy: 'admin',
            performedById: $this->admin->id,
            reason: 'Tutor medical appointment'
        );

        $freshBooking = $booking->fresh();
        $this->assertEquals('2026-10-27 12:00:00', $freshBooking->start_at_utc->format('Y-m-d H:i:s'));

        // History audit log preserved
        $this->assertDatabaseHas('booking_events', [
            'booking_id' => $booking->id,
            'event_type' => 'rescheduled',
            'performed_by' => 'admin',
        ]);

        $event = BookingEvent::where('booking_id', $booking->id)->where('event_type', 'rescheduled')->first();
        $this->assertEquals('2026-10-25 10:00:00', $event->previous_data['start_at_utc']);
        $this->assertEquals('Tutor medical appointment', $event->new_data['reason']);
    }

    /**
     * Scenario I: Admin blocks a date while visitor has an open booking flow. Final booking is rejected.
     */
    public function test_scenario_i_admin_blocks_date_while_visitor_has_open_flow(): void
    {
        $targetDate = CarbonImmutable::parse('2026-11-05');
        $slotUtc = $targetDate->setTime(11, 0, 0)->setTimezone('UTC');
        $endUtc = $slotUtc->addMinutes(50);

        // Visitor holds the slot
        $hold = $this->holdService->acquireHold(
            visitorToken: 'vis-blocked-date',
            sessionToken: 'sess-blocked-date',
            sessionType: $this->sessionType,
            startUtc: $slotUtc,
            endUtc: $endUtc
        );
        $this->assertNotNull($hold);

        // Before finalizing, Admin blocks the entire day (holiday / emergency)
        AvailabilityException::create([
            'date' => $targetDate->toDateString(),
            'type' => 'blocked',
            'notes' => 'Emergency tutor personal day',
        ]);

        // Visitor attempts to submit booking WITH their valid hold -> rejected specifically because date is blocked
        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('This date is blocked for appointments.');

        $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotUtc,
            'end_at_utc' => $endUtc,
            'customer_name' => 'Blocked User',
            'customer_email' => 'blocked@example.com',
            'customer_timezone' => 'UTC',
            'idempotency_key' => 'idem-blocked-attempt',
            'hold_id' => $hold->id,
            'hold_token' => $hold->hold_token,
            'visitor_token' => 'vis-blocked-date',
            'session_token' => 'sess-blocked-date',
        ]);
    }

    /**
     * Scenario J: Hold expires. Another visitor can book the slot.
     */
    public function test_scenario_j_hold_expires_another_visitor_can_book_slot(): void
    {
        $slotUtc = CarbonImmutable::parse('2026-11-10 13:00:00', 'UTC');
        $endUtc = $slotUtc->addMinutes(50);

        // Visitor 1 holds slot, but hold expires 15 minutes ago
        $holdVisitor1 = BookingHold::create([
            'session_type_id' => $this->sessionType->id,
            'slot_start_utc' => $slotUtc,
            'slot_end_utc' => $endUtc,
            'visitor_token' => 'visitor-expired-1',
            'session_token' => 'session-expired-1',
            'status' => 'active',
            'expires_at' => now()->subMinutes(15),
        ]);

        // Visitor 2 attempts to hold the same slot -> succeeds because previous hold is expired
        $holdVisitor2 = $this->holdService->acquireHold(
            visitorToken: 'visitor-active-2',
            sessionToken: 'session-active-2',
            sessionType: $this->sessionType,
            startUtc: $slotUtc,
            endUtc: $endUtc
        );

        $this->assertNotNull($holdVisitor2);
        $this->assertEquals('visitor-active-2', $holdVisitor2->visitor_token);

        // Visitor 2 completes booking with acquired hold
        $booking = $this->bookingService->createPublicBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $slotUtc,
            'end_at_utc' => $endUtc,
            'customer_name' => 'Winner Student',
            'customer_email' => 'winner@example.com',
            'customer_timezone' => 'UTC',
            'visitor_token' => 'visitor-active-2',
            'session_token' => 'session-active-2',
            'hold_id' => $holdVisitor2->id,
            'hold_token' => $holdVisitor2->hold_token,
            'idempotency_key' => 'idem-scenario-j-win',
        ]);

        $this->assertNotNull($booking);
        $this->assertEquals('confirmed', $booking->status);
    }
}
