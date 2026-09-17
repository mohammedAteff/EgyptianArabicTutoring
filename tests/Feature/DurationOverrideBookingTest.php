<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DurationOverrideBookingTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $admin;

    protected SessionType $sessionType;

    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Administrator::create([
            'name' => 'Tutor Admin',
            'email' => 'admin@boltlanding.test',
            'password' => Hash::make('Password123!'),
            'role' => 'super_admin',
        ]);

        // Base session type: 50 minutes duration
        $this->sessionType = SessionType::create([
            'title' => 'Standard Egyptian Arabic Lesson',
            'slug' => 'standard-lesson',
            'duration_minutes' => 50,
            'price' => 25.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        $this->contact = Contact::create([
            'email' => 'student@example.com',
            'name' => 'Sara Student',
        ]);
    }

    public function test_customer_http_reschedule_into_override_duration_rule(): void
    {
        $businessTz = app(TimezoneService::class)->getBusinessTimezone();
        $targetMonday = CarbonImmutable::now($businessTz)->next(CarbonImmutable::MONDAY)->addWeeks(1);
        $targetTuesday = CarbonImmutable::now($businessTz)->next(CarbonImmutable::TUESDAY)->addWeeks(1);

        // Monday Rule: standard 50-minute sessions
        AvailabilityRule::create([
            'weekday' => 1, // Monday
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 12,
            'max_horizon_days' => 60,
            'enabled' => true,
        ]);

        // Tuesday Rule: DURATION OVERRIDE = 30 minutes!
        AvailabilityRule::create([
            'weekday' => 2, // Tuesday
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
            'session_duration_minutes' => 30, // 30 min override
            'buffer_minutes' => 10,
            'min_notice_hours' => 12,
            'max_horizon_days' => 60,
            'enabled' => true,
        ]);

        // Existing booking on Monday: 10:00 to 10:50 (50 mins)
        $startUtcMonday = app(TimezoneService::class)->toUtc("{$targetMonday->toDateString()} 10:00:00", $businessTz);
        $endUtcMonday = $startUtcMonday->addMinutes(50);
        $snapshot = app(TimezoneService::class)->createBookingSnapshot($startUtcMonday, $endUtcMonday, 'Europe/Berlin', $businessTz);

        $booking = Booking::create(array_merge($snapshot, [
            'contact_id' => $this->contact->id,
            'session_type_id' => $this->sessionType->id,
            'status' => 'confirmed',
            'confirmation_token' => Str::random(64),
            'idempotency_key' => (string) Str::uuid(),
        ]));

        // Target Tuesday slot: 10:00. Under the 30-min override rule, this slot must end at 10:30, NOT 10:50.
        $targetStartUtcTuesday = app(TimezoneService::class)->toUtc("{$targetTuesday->toDateString()} 10:00:00", $businessTz);

        $response = $this->post(route('booking.reschedule.submit', $booking->confirmation_token), [
            'new_start_utc' => $targetStartUtcTuesday->toIso8601String(),
            'reason' => 'Need a quick 30min session on Tuesday',
        ]);

        $response->assertRedirect(route('booking.confirmation', ['token' => $booking->confirmation_token]));
        $response->assertSessionHas('success');

        $booking->refresh();
        $this->assertEquals($targetStartUtcTuesday->getTimestamp(), $booking->start_at_utc->getTimestamp());
        // Verify end time matches 30 minutes, not 50 minutes!
        $this->assertEquals($targetStartUtcTuesday->addMinutes(30)->getTimestamp(), $booking->end_at_utc->getTimestamp());
        $this->assertEquals(30, $booking->start_at_utc->diffInMinutes($booking->end_at_utc));
    }

    public function test_admin_http_reschedule_and_manual_create_into_override_duration_rule(): void
    {
        $businessTz = app(TimezoneService::class)->getBusinessTimezone();
        $targetWednesday = CarbonImmutable::now($businessTz)->next(CarbonImmutable::WEDNESDAY)->addWeeks(1);

        // Wednesday Rule: DURATION OVERRIDE = 90 minutes!
        AvailabilityRule::create([
            'weekday' => 3, // Wednesday
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
            'session_duration_minutes' => 90, // 90 min override
            'buffer_minutes' => 15,
            'min_notice_hours' => 0,
            'max_horizon_days' => 60,
            'enabled' => true,
        ]);

        // 1. Admin manual booking creation into 90-min override rule
        $createResponse = $this->actingAs($this->admin, 'web')->post(route('admin.bookings.store'), [
            'session_type_id' => $this->sessionType->id, // base is 50m
            'student_name' => 'Michael Admin Student',
            'student_email' => 'michael@example.com',
            'date' => $targetWednesday->toDateString(),
            'time' => '14:00',
            'customer_timezone' => 'America/New_York',
            'notes' => 'Intensive 90-minute session',
        ]);

        $createResponse->assertRedirect();
        $newBooking = Booking::query()
            ->whereHas('contact', fn ($q) => $q->where('email', 'michael@example.com'))
            ->latest('id')
            ->first();
        $this->assertNotNull($newBooking);

        $expectedStartUtc = app(TimezoneService::class)->toUtc("{$targetWednesday->toDateString()} 14:00:00", $businessTz);
        $expectedEndUtc = $expectedStartUtc->addMinutes(90);

        $this->assertEquals($expectedStartUtc->getTimestamp(), $newBooking->start_at_utc->getTimestamp());
        $this->assertEquals($expectedEndUtc->getTimestamp(), $newBooking->end_at_utc->getTimestamp());
        $this->assertEquals(90, $newBooking->start_at_utc->diffInMinutes($newBooking->end_at_utc));

        // 2. Admin reschedule into next 90-min slot on the same day (15:45 = 14:00 + 90m + 15m buffer)
        $rescheduleResponse = $this->actingAs($this->admin, 'web')->post(route('admin.bookings.reschedule', $newBooking->id), [
            'new_date' => $targetWednesday->toDateString(),
            'new_time' => '15:45',
            'reason' => 'Tutor moved slot by 105 minutes',
        ]);

        $rescheduleResponse->assertRedirect();
        $rescheduleResponse->assertSessionHas('success');

        $newBooking->refresh();
        $expectedRescheduledStart = app(TimezoneService::class)->toUtc("{$targetWednesday->toDateString()} 15:45:00", $businessTz);
        $expectedRescheduledEnd = $expectedRescheduledStart->addMinutes(90);

        $this->assertEquals($expectedRescheduledStart->getTimestamp(), $newBooking->start_at_utc->getTimestamp());
        $this->assertEquals($expectedRescheduledEnd->getTimestamp(), $newBooking->end_at_utc->getTimestamp());
        $this->assertEquals(90, $newBooking->start_at_utc->diffInMinutes($newBooking->end_at_utc));
    }
}
