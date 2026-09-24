<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use App\Livewire\BookingWizard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class BookingHoldAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected BookingService $bookingService;

    protected BookingHoldService $holdService;

    protected SessionType $sessionType;

    protected CarbonImmutable $slotStartUtc;

    protected CarbonImmutable $slotEndUtc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookingService = app(BookingService::class);
        $this->holdService = app(BookingHoldService::class);

        $this->sessionType = SessionType::create([
            'title' => 'Standard Egyptian Arabic Lesson',
            'slug' => 'egyptian-arabic-standard',
            'description' => 'Comprehensive 50-minute 1-on-1 tutoring',
            'duration_minutes' => 50,
            'price' => 35.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        // Enable availability for all days 00:00 - 23:59 Cairo time with 10 min buffer (step = 60 mins)
        for ($w = 0; $w <= 6; $w++) {
            AvailabilityRule::create([
                'weekday' => $w,
                'start_time' => '00:00:00',
                'end_time' => '23:59:00',
                'buffer_minutes' => 10,
                'enabled' => true,
            ]);
        }

        // Slot 5 days in future at 10:00 UTC (aligns to 60-min grid)
        $this->slotStartUtc = CarbonImmutable::now('UTC')->addDays(5)->setTime(10, 0, 0);
        $this->slotEndUtc = $this->slotStartUtc->addMinutes(50);
    }

    public function test_valid_owner_succeeds_and_converts_hold(): void
    {
        $hold = $this->holdService->acquireHold(
            visitorToken: 'vis-legit-123',
            sessionToken: 'sess-legit-123',
            sessionType: $this->sessionType,
            startUtc: $this->slotStartUtc,
            endUtc: $this->slotEndUtc
        );

        $booking = $this->bookingService->createPublicBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $this->slotStartUtc,
            'end_at_utc' => $this->slotEndUtc,
            'customer_timezone' => 'Europe/London',
            'customer_name' => 'Valid Customer',
            'customer_email' => 'valid.customer@example.com',
            'customer_phone' => '+447911123456',
            'visitor_token' => 'vis-legit-123',
            'session_token' => 'sess-legit-123',
            'hold_id' => $hold->id,
            'hold_token' => $hold->hold_token,
            'idempotency_key' => 'idem-valid-owner-1',
        ]);

        $this->assertNotNull($booking);
        $this->assertEquals('confirmed', $booking->status);
        $this->assertEquals('valid.customer@example.com', $booking->contact->email);

        $this->assertDatabaseHas('booking_holds', [
            'id' => $hold->id,
            'status' => 'converted',
        ]);

        $this->assertDatabaseHas('booking_events', [
            'booking_id' => $booking->id,
            'event_type' => 'created',
            'performed_by' => 'customer',
        ]);
    }

    public function test_missing_hold_fails_without_creating_contact_or_booking(): void
    {
        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('A valid reservation hold is required.');

        try {
            $this->bookingService->createPublicBooking([
                'session_type_id' => $this->sessionType->id,
                'start_at_utc' => $this->slotStartUtc,
                'end_at_utc' => $this->slotEndUtc,
                'customer_timezone' => 'Europe/London',
                'customer_name' => 'No Hold User',
                'customer_email' => 'nohold@example.com',
                'visitor_token' => 'vis-nohold',
                'session_token' => 'sess-nohold',
                'idempotency_key' => 'idem-nohold-1',
            ]);
        } finally {
            $this->assertDatabaseMissing('contacts', ['email' => 'nohold@example.com']);
            $this->assertDatabaseCount('bookings', 0);
            $this->assertDatabaseCount('booking_events', 0);
        }
    }

    public function test_missing_token_fails_without_creating_records(): void
    {
        $hold = $this->holdService->acquireHold(
            visitorToken: 'vis-notoken',
            sessionToken: 'sess-notoken',
            sessionType: $this->sessionType,
            startUtc: $this->slotStartUtc,
            endUtc: $this->slotEndUtc
        );

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('Reservation hold authentication token is required.');

        try {
            $this->bookingService->createPublicBooking([
                'session_type_id' => $this->sessionType->id,
                'start_at_utc' => $this->slotStartUtc,
                'end_at_utc' => $this->slotEndUtc,
                'customer_timezone' => 'UTC',
                'customer_name' => 'Missing Token',
                'customer_email' => 'notoken@example.com',
                'visitor_token' => 'vis-notoken',
                'session_token' => 'sess-notoken',
                'hold_id' => $hold->id,
                'idempotency_key' => 'idem-notoken-1',
            ]);
        } finally {
            $this->assertDatabaseMissing('contacts', ['email' => 'notoken@example.com']);
            $this->assertDatabaseCount('bookings', 0);
            $this->assertDatabaseCount('booking_events', 0);
            $this->assertEquals('active', $hold->fresh()->status);
        }
    }

    public function test_missing_session_token_fails_without_creating_records(): void
    {
        $hold = $this->holdService->acquireHold(
            visitorToken: 'vis-nosess',
            sessionToken: 'sess-nosess',
            sessionType: $this->sessionType,
            startUtc: $this->slotStartUtc,
            endUtc: $this->slotEndUtc
        );

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('Session authentication token is required.');

        try {
            $this->bookingService->createPublicBooking([
                'session_type_id' => $this->sessionType->id,
                'start_at_utc' => $this->slotStartUtc,
                'end_at_utc' => $this->slotEndUtc,
                'customer_timezone' => 'UTC',
                'customer_name' => 'Missing Session Token',
                'customer_email' => 'nosess@example.com',
                'visitor_token' => 'vis-nosess',
                // session_token intentionally omitted
                'hold_id' => $hold->id,
                'hold_token' => $hold->hold_token,
                'idempotency_key' => 'idem-nosess-1',
            ]);
        } finally {
            $this->assertDatabaseMissing('contacts', ['email' => 'nosess@example.com']);
            $this->assertDatabaseCount('bookings', 0);
            $this->assertEquals('active', $hold->fresh()->status);
        }
    }

    public function test_wrong_token_fails_without_creating_records(): void
    {
        $hold = $this->holdService->acquireHold(
            visitorToken: 'vis-wrongtok',
            sessionToken: 'sess-wrongtok',
            sessionType: $this->sessionType,
            startUtc: $this->slotStartUtc,
            endUtc: $this->slotEndUtc
        );

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('Invalid reservation hold authentication.');

        try {
            $this->bookingService->createPublicBooking([
                'session_type_id' => $this->sessionType->id,
                'start_at_utc' => $this->slotStartUtc,
                'end_at_utc' => $this->slotEndUtc,
                'customer_timezone' => 'UTC',
                'customer_name' => 'Wrong Token',
                'customer_email' => 'wrongtok@example.com',
                'visitor_token' => 'vis-wrongtok',
                'session_token' => 'sess-wrongtok',
                'hold_id' => $hold->id,
                'hold_token' => 'wrong-token-value-that-does-not-match-hold-token',
                'idempotency_key' => 'idem-wrongtok-1',
            ]);
        } finally {
            $this->assertDatabaseMissing('contacts', ['email' => 'wrongtok@example.com']);
            $this->assertDatabaseCount('bookings', 0);
            $this->assertEquals('active', $hold->fresh()->status);
        }
    }

    public function test_guessed_hold_id_with_wrong_or_missing_token_fails(): void
    {
        $victimHold = $this->holdService->acquireHold(
            visitorToken: 'vis-victim',
            sessionToken: 'sess-victim',
            sessionType: $this->sessionType,
            startUtc: $this->slotStartUtc,
            endUtc: $this->slotEndUtc
        );

        $this->expectException(SlotUnavailableException::class);

        try {
            // Attacker guesses victim's sequential hold id and uses their own attacker token
            $this->bookingService->createPublicBooking([
                'session_type_id' => $this->sessionType->id,
                'start_at_utc' => $this->slotStartUtc,
                'end_at_utc' => $this->slotEndUtc,
                'customer_timezone' => 'UTC',
                'customer_name' => 'Attacker',
                'customer_email' => 'attacker@example.com',
                'visitor_token' => 'vis-attacker',
                'session_token' => 'sess-attacker',
                'hold_id' => $victimHold->id,
                'hold_token' => 'attacker-invented-token',
                'idempotency_key' => 'idem-attacker-1',
            ]);
        } finally {
            $this->assertDatabaseMissing('contacts', ['email' => 'attacker@example.com']);
            $this->assertDatabaseCount('bookings', 0);
            $this->assertEquals('active', $victimHold->fresh()->status);
        }
    }

    public function test_wrong_visitor_fails_ownership_verification(): void
    {
        $hold = $this->holdService->acquireHold(
            visitorToken: 'vis-original-owner',
            sessionToken: 'sess-original-owner',
            sessionType: $this->sessionType,
            startUtc: $this->slotStartUtc,
            endUtc: $this->slotEndUtc
        );

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('Reservation hold ownership mismatch.');

        try {
            $this->bookingService->createPublicBooking([
                'session_type_id' => $this->sessionType->id,
                'start_at_utc' => $this->slotStartUtc,
                'end_at_utc' => $this->slotEndUtc,
                'customer_timezone' => 'UTC',
                'customer_name' => 'Different Visitor',
                'customer_email' => 'diffvis@example.com',
                'visitor_token' => 'vis-imposter-token',
                'session_token' => 'sess-original-owner',
                'hold_id' => $hold->id,
                'hold_token' => $hold->hold_token,
                'idempotency_key' => 'idem-diffvis-1',
            ]);
        } finally {
            $this->assertDatabaseMissing('contacts', ['email' => 'diffvis@example.com']);
            $this->assertDatabaseCount('bookings', 0);
            $this->assertEquals('active', $hold->fresh()->status);
        }
    }

    public function test_wrong_session_fails_session_verification(): void
    {
        $hold = $this->holdService->acquireHold(
            visitorToken: 'vis-owner',
            sessionToken: 'sess-owner-1',
            sessionType: $this->sessionType,
            startUtc: $this->slotStartUtc,
            endUtc: $this->slotEndUtc
        );

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('Reservation hold session mismatch.');

        try {
            $this->bookingService->createPublicBooking([
                'session_type_id' => $this->sessionType->id,
                'start_at_utc' => $this->slotStartUtc,
                'end_at_utc' => $this->slotEndUtc,
                'customer_timezone' => 'UTC',
                'customer_name' => 'Session Mismatch',
                'customer_email' => 'sessmismatch@example.com',
                'visitor_token' => 'vis-owner',
                'session_token' => 'sess-different-device-2',
                'hold_id' => $hold->id,
                'hold_token' => $hold->hold_token,
                'idempotency_key' => 'idem-sessmismatch-1',
            ]);
        } finally {
            $this->assertDatabaseMissing('contacts', ['email' => 'sessmismatch@example.com']);
            $this->assertDatabaseCount('bookings', 0);
            $this->assertEquals('active', $hold->fresh()->status);
        }
    }

    public function test_client_timestamps_cannot_override_the_authenticated_hold_interval(): void
    {
        $hold = $this->holdService->acquireHold(
            visitorToken: 'vis-interval-owner',
            sessionToken: 'sess-interval-owner',
            sessionType: $this->sessionType,
            startUtc: $this->slotStartUtc,
            endUtc: $this->slotEndUtc
        );

        $tamperedStart = $this->slotStartUtc->addHours(1);
        $tamperedEnd = $tamperedStart->addMinutes(50);

        $booking = $this->bookingService->createPublicBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $tamperedStart,
            'end_at_utc' => $tamperedEnd,
            'customer_timezone' => 'UTC',
            'customer_name' => 'Tampered Interval',
            'customer_email' => 'tampered@example.com',
            'visitor_token' => 'vis-interval-owner',
            'session_token' => 'sess-interval-owner',
            'hold_id' => $hold->id,
            'hold_token' => $hold->hold_token,
            'idempotency_key' => 'idem-tampered-1',
        ]);

        $this->assertSame($this->slotStartUtc->toDateTimeString(), $booking->start_at_utc->toDateTimeString());
        $this->assertSame($this->slotEndUtc->toDateTimeString(), $booking->end_at_utc->toDateTimeString());
        $this->assertDatabaseHas('booking_holds', ['id' => $hold->id, 'status' => 'converted']);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_tampered_session_type_fails_session_type_mismatch(): void
    {
        $otherSessionType = SessionType::create([
            'title' => 'Advanced Arabic Conversation',
            'slug' => 'advanced-arabic-conv',
            'description' => 'Advanced lesson',
            'duration_minutes' => 50,
            'price' => 50.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        $hold = $this->holdService->acquireHold(
            visitorToken: 'vis-st-owner',
            sessionToken: 'sess-st-owner',
            sessionType: $this->sessionType,
            startUtc: $this->slotStartUtc,
            endUtc: $this->slotEndUtc
        );

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('Reservation hold session type mismatch.');

        try {
            $this->bookingService->createPublicBooking([
                'session_type_id' => $otherSessionType->id,
                'start_at_utc' => $this->slotStartUtc,
                'end_at_utc' => $this->slotEndUtc,
                'customer_timezone' => 'UTC',
                'customer_name' => 'Session Type Tamperer',
                'customer_email' => 'st-tamper@example.com',
                'visitor_token' => 'vis-st-owner',
                'session_token' => 'sess-st-owner',
                'hold_id' => $hold->id,
                'hold_token' => $hold->hold_token,
                'idempotency_key' => 'idem-st-tamper-1',
            ]);
        } finally {
            $this->assertDatabaseMissing('contacts', ['email' => 'st-tamper@example.com']);
            $this->assertDatabaseCount('bookings', 0);
            $this->assertEquals('active', $hold->fresh()->status);
        }
    }

    public function test_expired_hold_fails_and_releases_slot(): void
    {
        $hold = BookingHold::create([
            'session_type_id' => $this->sessionType->id,
            'slot_start_utc' => $this->slotStartUtc->toDateTimeString(),
            'slot_end_utc' => $this->slotEndUtc->toDateTimeString(),
            'visitor_token' => 'vis-expired',
            'session_token' => 'sess-expired',
            'hold_token' => 'expired-token-xyz',
            'expires_at' => now()->subMinutes(5)->toDateTimeString(),
            'status' => 'active',
        ]);

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('Your reservation hold has expired.');

        try {
            $this->bookingService->createPublicBooking([
                'session_type_id' => $this->sessionType->id,
                'start_at_utc' => $this->slotStartUtc,
                'end_at_utc' => $this->slotEndUtc,
                'customer_timezone' => 'UTC',
                'customer_name' => 'Expired User',
                'customer_email' => 'expired@example.com',
                'visitor_token' => 'vis-expired',
                'session_token' => 'sess-expired',
                'hold_id' => $hold->id,
                'hold_token' => $hold->hold_token,
                'idempotency_key' => 'idem-expired-1',
            ]);
        } finally {
            $this->assertDatabaseMissing('contacts', ['email' => 'expired@example.com']);
            $this->assertDatabaseCount('bookings', 0);
        }
    }

    public function test_released_or_converted_hold_fails(): void
    {
        $hold = BookingHold::create([
            'session_type_id' => $this->sessionType->id,
            'slot_start_utc' => $this->slotStartUtc->toDateTimeString(),
            'slot_end_utc' => $this->slotEndUtc->toDateTimeString(),
            'visitor_token' => 'vis-converted',
            'session_token' => 'sess-converted',
            'hold_token' => 'converted-token-xyz',
            'expires_at' => now()->addMinutes(10)->toDateTimeString(),
            'status' => 'converted',
        ]);

        $this->expectException(SlotUnavailableException::class);
        $this->expectExceptionMessage('Your reservation hold is no longer active.');

        try {
            $this->bookingService->createPublicBooking([
                'session_type_id' => $this->sessionType->id,
                'start_at_utc' => $this->slotStartUtc,
                'end_at_utc' => $this->slotEndUtc,
                'customer_timezone' => 'UTC',
                'customer_name' => 'Reused Hold User',
                'customer_email' => 'reused@example.com',
                'visitor_token' => 'vis-converted',
                'session_token' => 'sess-converted',
                'hold_id' => $hold->id,
                'hold_token' => $hold->hold_token,
                'idempotency_key' => 'idem-reused-1',
            ]);
        } finally {
            $this->assertDatabaseMissing('contacts', ['email' => 'reused@example.com']);
            $this->assertDatabaseCount('bookings', 0);
        }
    }

    public function test_admin_manual_booking_succeeds_through_trusted_path_without_hold(): void
    {
        $booking = $this->bookingService->createAdminBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $this->slotStartUtc,
            'end_at_utc' => $this->slotEndUtc,
            'customer_timezone' => 'Africa/Cairo',
            'customer_name' => 'Admin Student',
            'customer_email' => 'admin.student@example.com',
            'customer_phone' => '+201099887766',
            'notes' => 'Booked directly via admin telephone order',
            'idempotency_key' => 'idem-admin-trusted-1',
        ], adminId: 42);

        $this->assertNotNull($booking);
        $this->assertEquals('confirmed', $booking->status);
        $this->assertEquals('admin.student@example.com', $booking->contact->email);

        $this->assertDatabaseHas('booking_events', [
            'booking_id' => $booking->id,
            'event_type' => 'created',
            'performed_by' => 'admin_42',
        ]);
    }

    public function test_livewire_wizard_locked_properties_reject_client_tampering(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(BookingWizard::class)
            ->set('holdId', 9999);
    }
}
