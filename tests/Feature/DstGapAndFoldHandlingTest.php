<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Availability\Models\AvailabilityException;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\IcsGenerator;
use App\Domains\CMS\Models\Setting;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Timezone\Exceptions\DstFoldAmbiguityException;
use App\Domains\Timezone\Exceptions\DstGapException;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DstGapAndFoldHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected TimezoneService $service;

    protected AvailabilityService $availabilityService;

    protected Administrator $admin;

    protected SessionType $sessionType;

    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TimezoneService::class);
        $this->availabilityService = app(AvailabilityService::class);

        Setting::set('business_timezone', 'Africa/Cairo', 'booking', true);
        Setting::set('booking_buffer_minutes', '15', 'booking', true);
        Setting::set('booking_min_notice_hours', '0', 'booking', true);
        Setting::set('booking_max_horizon_days', '90', 'booking', true);

        $this->admin = Administrator::create([
            'name' => 'Ahmad Tutor',
            'email' => 'tutor@boltlanding.test',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'super_admin',
        ]);

        $this->contact = Contact::create([
            'email' => 'student.dst@example.com',
            'name' => 'DST Student',
        ]);

        $this->sessionType = SessionType::create([
            'title' => '1-on-1 Egyptian Arabic Session',
            'slug' => 'egyptian-arabic-session',
            'duration_minutes' => 50,
            'price' => 40.00,
            'currency' => 'USD',
            'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_cairo_dst_spring_gap_throws_dst_gap_exception(): void
    {
        // On Friday 2026-04-24 in Africa/Cairo, clocks jump from 00:00 to 01:00.
        // The hour 00:00:00 to 00:59:59 does not exist.
        $this->assertTrue($this->service->isNonexistentLocalTime('2026-04-24 00:30:00', 'Africa/Cairo'));
        $this->assertFalse($this->service->isAmbiguousLocalTime('2026-04-24 00:30:00', 'Africa/Cairo'));

        $this->expectException(DstGapException::class);
        $this->service->resolveLocalWallTime('2026-04-24 00:30:00', 'Africa/Cairo');
    }

    public function test_cairo_to_utc_rejects_dst_gap_via_to_utc(): void
    {
        $this->expectException(DstGapException::class);
        $this->service->toUtc('2026-04-24 00:30:00', 'Africa/Cairo');
    }

    public function test_cairo_dst_fall_fold_resolves_deterministically(): void
    {
        // On Thursday 2026-10-29 in Africa/Cairo, clocks jump from 24:00 (00:00) back to 23:00.
        // The hour 23:00:00 to 23:59:59 occurs twice (first as EEST +03:00, then as EET +02:00).
        $this->assertTrue($this->service->isAmbiguousLocalTime('2026-10-29 23:30:00', 'Africa/Cairo'));
        $this->assertFalse($this->service->isNonexistentLocalTime('2026-10-29 23:30:00', 'Africa/Cairo'));

        // Default 'first' policy chooses the earlier UTC instant (summer time / EEST)
        $firstInstant = $this->service->resolveLocalWallTime('2026-10-29 23:30:00', 'Africa/Cairo', 'first');
        $this->assertSame('2026-10-29 20:30:00', $firstInstant->format('Y-m-d H:i:s'));
        $this->assertSame('+03:00', $firstInstant->setTimezone('Africa/Cairo')->format('P'));

        // 'second' policy chooses the later UTC instant (standard time / EET)
        $secondInstant = $this->service->resolveLocalWallTime('2026-10-29 23:30:00', 'Africa/Cairo', 'second');
        $this->assertSame('2026-10-29 21:30:00', $secondInstant->format('Y-m-d H:i:s'));
        $this->assertSame('+02:00', $secondInstant->setTimezone('Africa/Cairo')->format('P'));

        // Difference between the two instants is exactly 1 hour
        $this->assertSame(3600, $secondInstant->getTimestamp() - $firstInstant->getTimestamp());

        // 'reject' policy throws DstFoldAmbiguityException
        $this->expectException(DstFoldAmbiguityException::class);
        $this->service->resolveLocalWallTime('2026-10-29 23:30:00', 'Africa/Cairo', 'reject');
    }

    public function test_second_iana_zone_america_new_york_gap_and_fold(): void
    {
        // 1. America/New_York spring-forward gap: 2026-03-08 02:30:00 does not exist (clocks jump 02:00 -> 03:00)
        $this->assertTrue($this->service->isNonexistentLocalTime('2026-03-08 02:30:00', 'America/New_York'));

        try {
            $this->service->resolveLocalWallTime('2026-03-08 02:30:00', 'America/New_York');
            $this->fail('Expected DstGapException was not thrown for America/New_York');
        } catch (DstGapException $e) {
            $this->assertStringContainsString('America/New_York', $e->getMessage());
        }

        // 2. America/New_York fall-back fold: 2026-11-01 01:30:00 occurs twice (EDT -04:00 and EST -05:00)
        $this->assertTrue($this->service->isAmbiguousLocalTime('2026-11-01 01:30:00', 'America/New_York'));

        $nyFirst = $this->service->resolveLocalWallTime('2026-11-01 01:30:00', 'America/New_York', 'first');
        $this->assertSame('2026-11-01 05:30:00', $nyFirst->format('Y-m-d H:i:s'));
        $this->assertSame('-04:00', $nyFirst->setTimezone('America/New_York')->format('P'));

        $nySecond = $this->service->resolveLocalWallTime('2026-11-01 01:30:00', 'America/New_York', 'second');
        $this->assertSame('2026-11-01 06:30:00', $nySecond->format('Y-m-d H:i:s'));
        $this->assertSame('-05:00', $nySecond->setTimezone('America/New_York')->format('P'));

        $this->assertSame(3600, $nySecond->getTimestamp() - $nyFirst->getTimestamp());
    }

    public function test_recurring_slot_generation_skips_dst_gap(): void
    {
        CarbonImmutable::setTestNow('2026-04-20 12:00:00');

        // Seed Friday rule 00:00 to 04:00 in Africa/Cairo
        AvailabilityRule::create([
            'weekday' => 5, // Friday
            'start_time' => '00:00:00',
            'end_time' => '04:00:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 0,
            'max_horizon_days' => 90,
            'enabled' => true,
        ]);

        $gapFriday = CarbonImmutable::parse('2026-04-24 00:00:00', 'Africa/Cairo');

        $slots = $this->availabilityService->getAvailableSlotsGroupedByDate(
            sessionType: $this->sessionType,
            customerTimezone: 'Africa/Cairo',
            fromDate: $gapFriday,
            toDate: $gapFriday
        );

        $this->assertArrayHasKey('2026-04-24', $slots);
        $daySlots = $slots['2026-04-24'];

        // The first slot must start at 01:00 or later; none can have start time 00:00 or 00:30
        $this->assertNotEmpty($daySlots);
        foreach ($daySlots as $slot) {
            $this->assertNotSame('00:00', $slot['business_start_time']);
            $this->assertNotSame('00:30', $slot['business_start_time']);
            $this->assertGreaterThanOrEqual('01:00', $slot['business_start_time']);
        }
    }

    public function test_slot_presentation_disambiguates_repeated_times_during_dst_fold(): void
    {
        // Tutor available Sunday 04:00 to 10:00 in Cairo (which corresponds to 01:00 to 07:00 UTC)
        AvailabilityRule::create([
            'weekday' => 0, // Sunday
            'start_time' => '04:00:00',
            'end_time' => '10:00:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 0,
            'max_horizon_days' => 90,
            'enabled' => true,
        ]);

        $foldSundayUtc = CarbonImmutable::parse('2026-11-01 04:00:00', 'UTC');

        // Customer in America/New_York viewing slots across the 01:00 AM fold
        $slots = $this->availabilityService->getAvailableSlotsGroupedByDate(
            sessionType: $this->sessionType,
            customerTimezone: 'America/New_York',
            fromDate: $foldSundayUtc,
            toDate: $foldSundayUtc->addDay()
        );

        $this->assertArrayHasKey('2026-11-01', $slots);
        $nySlots = $slots['2026-11-01'];

        // Find slots that land at 1:00 AM in New York
        $ambiguousSlots = array_filter($nySlots, fn ($s) => $s['customer_start_time'] === '01:00');
        $this->assertCount(2, $ambiguousSlots);

        // Verify each ambiguous slot has its timezone abbreviation in the formatted label
        $formattedLabels = array_column($ambiguousSlots, 'customer_formatted');
        $this->assertTrue(
            collect($formattedLabels)->contains(fn ($l) => str_contains($l, 'EDT')),
            'Expected EDT disambiguation in formatted label'
        );
        $this->assertTrue(
            collect($formattedLabels)->contains(fn ($l) => str_contains($l, 'EST')),
            'Expected EST disambiguation in formatted label'
        );
    }

    public function test_admin_manual_booking_at_nonexistent_local_time_is_rejected(): void
    {
        // Ensure availability rule exists for Friday 00:00 to 04:00
        AvailabilityRule::create([
            'weekday' => 5,
            'start_time' => '00:00:00',
            'end_time' => '04:00:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 0,
            'max_horizon_days' => 90,
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.bookings.store'), [
                'session_type_id' => $this->sessionType->id,
                'student_name' => 'Sara Student',
                'student_email' => 'sara@example.com',
                'date' => '2026-04-24',
                'time' => '00:30', // Nonexistent in Africa/Cairo!
                'customer_timezone' => 'Africa/Cairo',
            ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('does not exist', session('error'));
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_admin_reschedule_at_nonexistent_local_time_is_rejected(): void
    {
        // Create an existing valid booking
        AvailabilityRule::create([
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 0,
            'max_horizon_days' => 90,
            'enabled' => true,
        ]);

        $validStart = CarbonImmutable::parse('2026-05-04 10:00:00', 'Africa/Cairo')->setTimezone('UTC');
        $validEnd = $validStart->addMinutes(50);

        $booking = Booking::create(array_merge(
            $this->service->createBookingSnapshot($validStart, $validEnd, 'Africa/Cairo', 'Africa/Cairo'),
            [
                'contact_id' => $this->contact->id,
                'session_type_id' => $this->sessionType->id,
                'status' => 'confirmed',
                'idempotency_key' => 'test-resched-dst',
                'confirmation_token' => 'token-resched-dst-1234567890',
            ]
        ));

        // Attempt to reschedule to nonexistent time 2026-04-24 00:30
        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.bookings.reschedule', $booking->id), [
                'new_date' => '2026-04-24',
                'new_time' => '00:30',
                'reason' => 'Testing DST gap rejection',
            ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('does not exist', session('error'));

        // Booking remains at original time
        $booking->refresh();
        $this->assertSame($validStart->toDateTimeString(), $booking->start_at_utc->toDateTimeString());
    }

    public function test_utc_snapshot_and_ics_generation_preserve_exact_instants_across_dst(): void
    {
        // Booking at the repeated hour in New York: 2026-11-01 01:30 EDT (05:30 UTC)
        $startUtc = $this->service->resolveLocalWallTime('2026-11-01 01:30:00', 'America/New_York', 'first');
        $endUtc = $startUtc->addMinutes(50);

        $snapshot = $this->service->createBookingSnapshot(
            startUtc: $startUtc,
            endUtc: $endUtc,
            customerTimezone: 'America/New_York',
            businessTimezone: 'Africa/Cairo'
        );

        $this->assertSame('2026-11-01 05:30:00', $snapshot['start_at_utc']);
        $this->assertSame('2026-11-01 06:20:00', $snapshot['end_at_utc']);
        $this->assertSame('-04:00', $snapshot['customer_utc_offset_at_booking']);
        $this->assertSame('+02:00', $snapshot['business_utc_offset_at_booking']); // Cairo is EET in Nov (+2)

        $booking = Booking::create(array_merge($snapshot, [
            'contact_id' => $this->contact->id,
            'session_type_id' => $this->sessionType->id,
            'status' => 'confirmed',
            'idempotency_key' => 'test-ics-dst-snap',
            'confirmation_token' => 'token-ics-dst-1234567890abcdef',
        ]));

        $ics = app(IcsGenerator::class)->generate($booking);

        $this->assertStringContainsString('DTSTART:20261101T053000Z', $ics);
        $this->assertStringContainsString('DTEND:20261101T062000Z', $ics);
        $this->assertStringContainsString('STATUS:CONFIRMED', $ics);
    }

    public function test_special_hours_boundary_inside_cairo_dst_gap_does_not_silently_shift(): void
    {
        CarbonImmutable::setTestNow('2026-04-20 12:00:00');

        // Special-hours exception on Cairo DST spring gap date starting at nonexistent time 00:30
        AvailabilityException::create([
            'date' => '2026-04-24',
            'type' => 'special_hours',
            'start_time' => '00:30:00', // nonexistent!
            'end_time' => '03:30:00',
        ]);

        $gapFriday = CarbonImmutable::parse('2026-04-24 00:00:00', 'Africa/Cairo');

        $slots = $this->availabilityService->getAvailableSlotsGroupedByDate(
            sessionType: $this->sessionType,
            customerTimezone: 'Africa/Cairo',
            fromDate: $gapFriday,
            toDate: $gapFriday
        );

        // Special hours at a nonexistent time must be skipped and NEVER silently rounded to 01:30
        $this->assertEmpty($slots['2026-04-24'] ?? []);
    }

    public function test_recurring_rule_boundary_inside_dst_gap_resolves_exact_utc_instants(): void
    {
        CarbonImmutable::setTestNow('2026-04-20 12:00:00');

        // Recurring rule on Friday starting at 00:30 (step = 60 mins: 50m duration + 10m buffer)
        AvailabilityRule::create([
            'weekday' => 5, // Friday
            'start_time' => '00:30:00',
            'end_time' => '04:00:00',
            'session_duration_minutes' => 50,
            'buffer_minutes' => 10,
            'min_notice_hours' => 0,
            'max_horizon_days' => 90,
            'enabled' => true,
        ]);

        $gapFriday = CarbonImmutable::parse('2026-04-24 00:00:00', 'Africa/Cairo');

        $slots = $this->availabilityService->getAvailableSlotsGroupedByDate(
            sessionType: $this->sessionType,
            customerTimezone: 'Africa/Cairo',
            fromDate: $gapFriday,
            toDate: $gapFriday
        );

        $this->assertArrayHasKey('2026-04-24', $slots);
        $daySlots = $slots['2026-04-24'];

        // 00:30 must be skipped entirely because it does not exist
        // First generated slot must be 01:30 local wall time (which is 22:30 UTC on 2026-04-23)
        $this->assertNotEmpty($daySlots);
        $firstSlot = $daySlots[0];
        $this->assertSame('01:30', $firstSlot['business_start_time']);
        $this->assertSame('2026-04-23 22:30:00', $firstSlot['slot_start_utc']);
        $this->assertSame('2026-04-23 23:20:00', $firstSlot['slot_end_utc']);

        // Second slot must be 02:30 local wall time (23:30 UTC)
        $secondSlot = $daySlots[1];
        $this->assertSame('02:30', $secondSlot['business_start_time']);
        $this->assertSame('2026-04-23 23:30:00', $secondSlot['slot_start_utc']);
    }
}
