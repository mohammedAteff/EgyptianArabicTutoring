<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Models\AdminNotification;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingService;
use App\Livewire\BookingWizard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\IssuesBookingSlotIds;
use Tests\TestCase;

class BookingCreationNotificationTest extends TestCase
{
    use IssuesBookingSlotIds;
    use RefreshDatabase;

    protected Administrator $admin;

    protected SessionType $sessionType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Administrator::create([
            'name' => 'Notification Admin',
            'email' => 'admin.notify@example.com',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->sessionType = SessionType::create([
            'title' => '60-Min Egyptian Arabic Session',
            'slug' => 'egyptian-arabic-60',
            'duration_minutes' => 60,
            'price' => 40.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        for ($w = 0; $w <= 6; $w++) {
            AvailabilityRule::create([
                'weekday' => $w,
                'start_time' => '00:00:00',
                'end_time' => '23:59:00',
                'buffer_minutes' => 0,
                'enabled' => true,
            ]);
        }
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_livewire_booking_confirmation_emits_exactly_one_notification(): void
    {
        $slotDate = CarbonImmutable::now('Africa/Cairo')->addDays(3)->toDateString();
        $slotStartUtc = CarbonImmutable::parse("{$slotDate} 10:00:00", 'Africa/Cairo')->setTimezone('UTC')->toDateTimeString();
        $wizard = Livewire::test(BookingWizard::class)
            ->call('selectSession', $this->sessionType->id)
            ->call('setDetectedTimezone', 'Africa/Cairo')
            ->call('selectDate', $slotDate);
        $wizard->call('selectSlot', $this->slotIdFor($this->sessionType, $slotStartUtc, 'Africa/Cairo', $wizard->get('visitorToken')))
            ->set('first_name', 'Nadia')
            ->set('last_name', 'Mostafa')
            ->set('date_of_birth', '1990-09-15')
            ->set('email', 'nadia@example.com')
            ->set('phone', '+201000000011')
            ->call('submitDetails')
            ->call('confirmBooking');

        $booking = Booking::whereHas('contact', fn ($q) => $q->where('email', 'nadia@example.com'))->firstOrFail();

        $notifications = AdminNotification::where('type', 'booking_created')->get();
        $this->assertCount(1, $notifications);

        $notification = $notifications->first();
        $this->assertEquals("New Booking #{$booking->id}", $notification->title);
        $this->assertStringContainsString('Nadia Mostafa', $notification->message);
        $this->assertStringContainsString('(Cairo)', $notification->message);
        $this->assertEquals(route('admin.bookings.show', $booking->id), $notification->link);
    }

    public function test_idempotent_booking_replay_emits_no_duplicate_notification(): void
    {
        $slotDate = CarbonImmutable::now('Africa/Cairo')->addDays(4)->toDateString();
        $slotStartUtc = CarbonImmutable::parse("{$slotDate} 14:00:00", 'Africa/Cairo')->setTimezone('UTC')->toDateTimeString();
        $wizard = Livewire::test(BookingWizard::class)
            ->call('selectSession', $this->sessionType->id)
            ->call('setDetectedTimezone', 'Africa/Cairo')
            ->call('selectDate', $slotDate);
        $wizard->call('selectSlot', $this->slotIdFor($this->sessionType, $slotStartUtc, 'Africa/Cairo', $wizard->get('visitorToken')))
            ->set('first_name', 'Tariq')
            ->set('last_name', 'Zaki')
            ->set('date_of_birth', '1990-10-15')
            ->set('email', 'tariq@example.com')
            ->set('phone', '+201000000012')
            ->call('submitDetails');

        // First confirmation
        $wizard->call('confirmBooking');
        $this->assertEquals(1, AdminNotification::where('type', 'booking_created')->count());

        // Replay confirmation with same idempotency state
        $wizard->call('confirmBooking');
        $this->assertEquals(1, AdminNotification::where('type', 'booking_created')->count(), 'Idempotent replay must not create duplicate notification');
    }

    public function test_http_admin_creation_emits_exactly_one_notification(): void
    {
        $slotDate = CarbonImmutable::now('Africa/Cairo')->addDays(5)->toDateString();

        $response = $this->actingAs($this->admin, 'web')->post(route('admin.bookings.store'), [
            'session_type_id' => $this->sessionType->id,
            'student_name' => 'Mona Helmy',
            'student_email' => 'mona.helmy@example.com',
            'date' => $slotDate,
            'time' => '11:00',
            'customer_timezone' => 'Africa/Cairo',
        ]);

        $booking = Booking::whereHas('contact', fn ($q) => $q->where('email', 'mona.helmy@example.com'))->firstOrFail();
        $response->assertRedirect(route('admin.bookings.show', $booking->id));

        $notifications = AdminNotification::where('type', 'booking_created')->get();
        $this->assertCount(1, $notifications);

        $notification = $notifications->first();
        $this->assertEquals("New Booking #{$booking->id}", $notification->title);
        $this->assertStringContainsString('Mona Helmy', $notification->message);
        $this->assertStringContainsString('(Cairo)', $notification->message);
    }

    public function test_failed_booking_creation_emits_no_notification(): void
    {
        // Try creating admin booking in the past
        $response = $this->actingAs($this->admin, 'web')->post(route('admin.bookings.store'), [
            'session_type_id' => $this->sessionType->id,
            'student_name' => 'Past Student',
            'student_email' => 'past@example.com',
            'date' => '2020-01-01',
            'time' => '10:00',
            'customer_timezone' => 'Africa/Cairo',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(0, AdminNotification::where('type', 'booking_created')->count(), 'Failed booking must not emit any notification');
    }

    public function test_summer_cairo_dst_time_matches_business_snapshot(): void
    {
        CarbonImmutable::setTestNow('2026-07-01 10:00:00');

        // July 15 is during Egypt DST (UTC+3)
        // 14:00 UTC = 17:00 (5:00 PM) Cairo
        $startUtc = CarbonImmutable::parse('2026-07-15 14:00:00', 'UTC');
        $endUtc = $startUtc->addMinutes(60);

        $booking = app(BookingService::class)->createAdminBooking([
            'session_type_id' => $this->sessionType->id,
            'start_at_utc' => $startUtc,
            'end_at_utc' => $endUtc,
            'customer_timezone' => 'Europe/London',
            'business_timezone' => 'Africa/Cairo',
            'customer_name' => 'Summer Student',
            'customer_email' => 'summer@example.com',
        ]);

        $notification = AdminNotification::where('type', 'booking_created')
            ->where('title', "New Booking #{$booking->id}")
            ->firstOrFail();

        // 14:00 UTC in Cairo summer DST is 5:00 PM (17:00)
        $this->assertStringContainsString('Jul 15, 2026 at 5:00 PM (Cairo)', $notification->message);
        $this->assertEquals('17:00:00', $booking->business_local_start_time_at_booking);
    }
}
