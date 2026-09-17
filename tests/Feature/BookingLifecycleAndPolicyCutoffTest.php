<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Exceptions\BookingPolicyViolationException;
use App\Domains\Booking\Exceptions\InvalidBookingStatusTransitionException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\CancellationService;
use App\Domains\Booking\Services\RescheduleService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingLifecycleAndPolicyCutoffTest extends TestCase
{
    use RefreshDatabase;

    protected TimezoneService $timezoneService;

    protected CancellationService $cancellationService;

    protected RescheduleService $rescheduleService;

    protected Administrator $admin;

    protected SessionType $sessionType;

    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timezoneService = app(TimezoneService::class);
        $this->cancellationService = app(CancellationService::class);
        $this->rescheduleService = app(RescheduleService::class);

        Setting::set('business_timezone', 'Africa/Cairo', 'booking', true);
        Setting::set('booking_buffer_minutes', '15', 'booking', true);
        Setting::set('booking_min_notice_hours', '0', 'booking', true);
        Setting::set('booking_max_horizon_days', '90', 'booking', true);
        Setting::set('booking_cancellation_cutoff_hours', '24', 'booking', true);

        $this->admin = Administrator::create([
            'name' => 'Ahmad Tutor',
            'email' => 'tutor@boltlanding.test',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'super_admin',
        ]);

        $this->contact = Contact::create([
            'email' => 'student.lifecycle@example.com',
            'name' => 'Lifecycle Student',
        ]);

        $this->sessionType = SessionType::create([
            'title' => '1-on-1 Egyptian Arabic Session',
            'slug' => 'egyptian-arabic-session',
            'duration_minutes' => 50,
            'price' => 40.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        // Seed 24/7 rules for testing flexible reschedule slots
        for ($w = 0; $w <= 6; $w++) {
            AvailabilityRule::create([
                'weekday' => $w,
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'session_duration_minutes' => 50,
                'buffer_minutes' => 15,
                'min_notice_hours' => 0,
                'max_horizon_days' => 90,
                'enabled' => true,
            ]);
        }
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    protected function createTestBooking(CarbonImmutable $startUtc, string $status = 'confirmed'): Booking
    {
        $endUtc = $startUtc->addMinutes(50);
        $snapshot = $this->timezoneService->createBookingSnapshot(
            startUtc: $startUtc,
            endUtc: $endUtc,
            customerTimezone: 'Africa/Cairo',
            businessTimezone: 'Africa/Cairo'
        );

        return Booking::create(array_merge($snapshot, [
            'contact_id' => $this->contact->id,
            'session_type_id' => $this->sessionType->id,
            'status' => $status,
            'idempotency_key' => 'idem-'.uniqid('', true),
            'confirmation_token' => bin2hex(random_bytes(32)),
        ]));
    }

    public function test_confirmed_booking_allowed_to_cancel_outside_cutoff(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 10:00:00');

        // Appointment is 48 hours away (well outside 24h cutoff)
        $startUtc = CarbonImmutable::parse('2026-10-03 10:00:00', 'UTC');
        $booking = $this->createTestBooking($startUtc, 'confirmed');

        $cancelled = $this->cancellationService->cancel(
            booking: $booking,
            performedBy: 'customer',
            reason: 'Schedule conflict'
        );

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('Schedule conflict', $cancelled->cancellation_reason);

        // Verify BookingEvent was recorded
        $this->assertDatabaseHas('booking_events', [
            'booking_id' => $booking->id,
            'event_type' => 'cancelled',
            'performed_by' => 'customer',
        ]);
    }

    public function test_confirmed_booking_allowed_to_reschedule_outside_cutoff(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 10:00:00');

        $startUtc = CarbonImmutable::parse('2026-10-03 10:00:00', 'UTC');
        $booking = $this->createTestBooking($startUtc, 'confirmed');

        $newStartUtc = CarbonImmutable::parse('2026-10-04 11:05:00', 'UTC'); // matches grid: 00:00 + k*(50+15)
        // 11:05 is 10 * 65 mins from 00:00 -> exactly 650 mins = 10h 50m... let's calculate: 11*65 = 715m = 11:55
        // Let's use 65 mins intervals: 00:00, 01:05, 02:10, 03:15, 04:20, 05:25, 06:30, 07:35, 08:40, 09:45, 10:50, 11:55...
        // 10:50 is 10 * 65m
        $alignedNewStart = CarbonImmutable::parse('2026-10-04 10:50:00', 'Africa/Cairo')->setTimezone('UTC');
        $alignedNewEnd = $alignedNewStart->addMinutes(50);

        $rescheduled = $this->rescheduleService->reschedule(
            booking: $booking,
            newStartUtc: $alignedNewStart,
            newEndUtc: $alignedNewEnd,
            performedBy: 'customer',
            reason: 'Moving to Sunday morning'
        );

        $this->assertSame('confirmed', $rescheduled->status);
        $this->assertSame($alignedNewStart->toDateTimeString(), $rescheduled->start_at_utc->toDateTimeString());

        // Verify BookingEvent recorded
        $this->assertDatabaseHas('booking_events', [
            'booking_id' => $booking->id,
            'event_type' => 'rescheduled',
            'performed_by' => 'customer',
        ]);
    }

    public function test_customer_cancel_rejected_inside_cutoff(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 10:00:00');

        // Appointment is 12 hours away (inside 24h cutoff)
        $startUtc = CarbonImmutable::parse('2026-10-01 22:00:00', 'UTC');
        $booking = $this->createTestBooking($startUtc, 'confirmed');

        // 1. Service direct call throws BookingPolicyViolationException
        try {
            $this->cancellationService->cancel($booking, performedBy: 'customer');
            $this->fail('Expected BookingPolicyViolationException was not thrown');
        } catch (BookingPolicyViolationException $e) {
            $this->assertStringContainsString('24 hours', $e->getMessage());
        }

        // 2. HTTP route rejects with redirect error
        $response = $this->post(route('booking.cancel', ['token' => $booking->confirmation_token]));
        $response->assertRedirect(route('booking.confirmation', ['token' => $booking->confirmation_token]));
        $response->assertSessionHas('error');

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
    }

    public function test_customer_reschedule_rejected_inside_cutoff(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 10:00:00');

        // Appointment is 12 hours away (inside 24h cutoff)
        $startUtc = CarbonImmutable::parse('2026-10-01 22:00:00', 'UTC');
        $booking = $this->createTestBooking($startUtc, 'confirmed');

        $alignedNewStart = CarbonImmutable::parse('2026-10-04 10:50:00', 'Africa/Cairo')->setTimezone('UTC');
        $alignedNewEnd = $alignedNewStart->addMinutes(50);

        // 1. Service throws BookingPolicyViolationException
        try {
            $this->rescheduleService->reschedule(
                booking: $booking,
                newStartUtc: $alignedNewStart,
                newEndUtc: $alignedNewEnd,
                performedBy: 'customer'
            );
            $this->fail('Expected BookingPolicyViolationException was not thrown');
        } catch (BookingPolicyViolationException $e) {
            $this->assertStringContainsString('24 hours', $e->getMessage());
        }

        // 2. HTTP route rejects
        $response = $this->post(route('booking.reschedule.submit', ['token' => $booking->confirmation_token]), [
            'new_start_utc' => $alignedNewStart->toDateTimeString(),
        ]);
        $response->assertSessionHas('error');

        $booking->refresh();
        $this->assertSame($startUtc->toDateTimeString(), $booking->start_at_utc->toDateTimeString());
    }

    public function test_past_booking_cannot_be_cancelled_or_rescheduled_by_customer(): void
    {
        CarbonImmutable::setTestNow('2026-10-05 10:00:00');

        // Past booking
        $startUtc = CarbonImmutable::parse('2026-10-01 10:00:00', 'UTC');
        $booking = $this->createTestBooking($startUtc, 'confirmed');

        $alignedNewStart = CarbonImmutable::parse('2026-10-10 10:50:00', 'Africa/Cairo')->setTimezone('UTC');
        $alignedNewEnd = $alignedNewStart->addMinutes(50);

        // Cancel rejected
        try {
            $this->cancellationService->cancel($booking, performedBy: 'customer');
            $this->fail('Expected BookingPolicyViolationException for past cancel');
        } catch (BookingPolicyViolationException $e) {
            $this->assertStringContainsString('Past appointments', $e->getMessage());
        }

        // Reschedule rejected
        try {
            $this->rescheduleService->reschedule($booking, $alignedNewStart, $alignedNewEnd, performedBy: 'customer');
            $this->fail('Expected BookingPolicyViolationException for past reschedule');
        } catch (BookingPolicyViolationException $e) {
            $this->assertStringContainsString('Past appointments', $e->getMessage());
        }
    }

    public function test_completed_booking_cannot_be_cancelled_or_rescheduled(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 10:00:00');
        $startUtc = CarbonImmutable::parse('2026-10-05 10:00:00', 'UTC');
        $booking = $this->createTestBooking($startUtc, 'completed');

        $alignedNewStart = CarbonImmutable::parse('2026-10-10 10:50:00', 'Africa/Cairo')->setTimezone('UTC');
        $alignedNewEnd = $alignedNewStart->addMinutes(50);

        // Cancel throws InvalidBookingStatusTransitionException
        $this->expectException(InvalidBookingStatusTransitionException::class);
        $this->cancellationService->cancel($booking, performedBy: 'customer');
    }

    public function test_completed_booking_cannot_be_rescheduled(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 10:00:00');
        $startUtc = CarbonImmutable::parse('2026-10-05 10:00:00', 'UTC');
        $booking = $this->createTestBooking($startUtc, 'completed');

        $alignedNewStart = CarbonImmutable::parse('2026-10-10 10:50:00', 'Africa/Cairo')->setTimezone('UTC');
        $alignedNewEnd = $alignedNewStart->addMinutes(50);

        $this->expectException(InvalidBookingStatusTransitionException::class);
        $this->rescheduleService->reschedule($booking, $alignedNewStart, $alignedNewEnd, performedBy: 'admin');
    }

    public function test_no_show_booking_cannot_be_cancelled_or_rescheduled(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 10:00:00');
        $startUtc = CarbonImmutable::parse('2026-10-05 10:00:00', 'UTC');
        $booking = $this->createTestBooking($startUtc, 'no_show');

        $alignedNewStart = CarbonImmutable::parse('2026-10-10 10:50:00', 'Africa/Cairo')->setTimezone('UTC');
        $alignedNewEnd = $alignedNewStart->addMinutes(50);

        try {
            $this->cancellationService->cancel($booking, performedBy: 'admin');
            $this->fail('Expected InvalidBookingStatusTransitionException for cancelling no-show');
        } catch (InvalidBookingStatusTransitionException $e) {
            $this->assertStringContainsString('no-show', $e->getMessage());
        }

        try {
            $this->rescheduleService->reschedule($booking, $alignedNewStart, $alignedNewEnd, performedBy: 'admin');
            $this->fail('Expected InvalidBookingStatusTransitionException for rescheduling no-show');
        } catch (InvalidBookingStatusTransitionException $e) {
            $this->assertStringContainsString('no-show', $e->getMessage());
        }
    }

    public function test_cancelled_booking_cannot_be_rescheduled_or_cancelled_again(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 10:00:00');
        $startUtc = CarbonImmutable::parse('2026-10-05 10:00:00', 'UTC');
        $booking = $this->createTestBooking($startUtc, 'cancelled');

        $alignedNewStart = CarbonImmutable::parse('2026-10-10 10:50:00', 'Africa/Cairo')->setTimezone('UTC');
        $alignedNewEnd = $alignedNewStart->addMinutes(50);

        try {
            $this->cancellationService->cancel($booking, performedBy: 'customer');
            $this->fail('Expected InvalidBookingStatusTransitionException');
        } catch (InvalidBookingStatusTransitionException $e) {
            $this->assertStringContainsString('already been cancelled', $e->getMessage());
        }

        try {
            $this->rescheduleService->reschedule($booking, $alignedNewStart, $alignedNewEnd, performedBy: 'admin');
            $this->fail('Expected InvalidBookingStatusTransitionException');
        } catch (InvalidBookingStatusTransitionException $e) {
            $this->assertStringContainsString('Cancelled bookings cannot be rescheduled', $e->getMessage());
        }
    }

    public function test_admin_can_override_cancellation_and_reschedule_cutoffs(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 10:00:00');

        // Appointment is 2 hours away (inside 24h cutoff)
        $startUtc = CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC');
        $booking = $this->createTestBooking($startUtc, 'confirmed');

        $alignedNewStart = CarbonImmutable::parse('2026-10-04 10:50:00', 'Africa/Cairo')->setTimezone('UTC');
        $alignedNewEnd = $alignedNewStart->addMinutes(50);

        // 1. Admin can reschedule inside cutoff
        $rescheduled = $this->rescheduleService->reschedule(
            booking: $booking,
            newStartUtc: $alignedNewStart,
            newEndUtc: $alignedNewEnd,
            performedBy: 'admin',
            performedById: $this->admin->id,
            reason: 'Tutor emergency override'
        );

        $this->assertSame('confirmed', $rescheduled->status);
        $this->assertSame($alignedNewStart->toDateTimeString(), $rescheduled->start_at_utc->toDateTimeString());

        // 2. Admin can cancel inside cutoff
        $booking2 = $this->createTestBooking(CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC'), 'confirmed');
        $cancelled = $this->cancellationService->cancel(
            booking: $booking2,
            performedBy: 'admin',
            performedById: $this->admin->id,
            reason: 'Tutor emergency cancellation'
        );

        $this->assertSame('cancelled', $cancelled->status);
    }
}
