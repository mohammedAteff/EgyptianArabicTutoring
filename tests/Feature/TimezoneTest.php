<?php

namespace Tests\Feature;

use App\Domains\Booking\Exceptions\InvalidTimezoneException;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected TimezoneService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TimezoneService::class);
    }

    public function test_validates_iana_timezone_identifiers(): void
    {
        $this->assertTrue($this->service->isValid('Africa/Cairo'));
        $this->assertTrue($this->service->isValid('Europe/Berlin'));
        $this->assertTrue($this->service->isValid('America/New_York'));
        $this->assertTrue($this->service->isValid('Asia/Tokyo'));

        $this->assertFalse($this->service->isValid('Invalid/Zone'));
        $this->assertFalse($this->service->isValid('UTC+2'));
        $this->assertFalse($this->service->isValid(''));
    }

    public function test_validate_method_throws_exception_on_invalid_zone(): void
    {
        $this->expectException(InvalidTimezoneException::class);
        $this->service->validate('Fake/Timezone');
    }

    public function test_retrieves_available_timezones_with_labels_and_offsets(): void
    {
        $zones = $this->service->getAvailableTimezones();

        $this->assertNotEmpty($zones);
        $this->assertArrayHasKey('id', $zones[0]);
        $this->assertArrayHasKey('label', $zones[0]);
        $this->assertArrayHasKey('offset', $zones[0]);

        $ids = array_column($zones, 'id');
        $this->assertContains('Africa/Cairo', $ids);
        $this->assertContains('Europe/Berlin', $ids);
        $this->assertContains('America/New_York', $ids);
    }

    public function test_converts_between_utc_and_local_accurately(): void
    {
        // 14:00 in Cairo (UTC+3) is 11:00 UTC
        $cairoTime = CarbonImmutable::parse('2026-06-15 14:00:00', 'Africa/Cairo');
        $utc = $this->service->toUtc($cairoTime);

        $this->assertSame('2026-06-15 11:00:00', $utc->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $utc->getTimezone()->getName());

        // Converted to New York (EDT, UTC-4): 11:00 UTC is 07:00 EDT
        $nyTime = $this->service->toLocal($utc, 'America/New_York');
        $this->assertSame('2026-06-15 07:00:00', $nyTime->format('Y-m-d H:i:s'));
        $this->assertSame('-04:00', $nyTime->format('P'));
    }

    public function test_handles_midnight_boundary_and_date_change_between_zones(): void
    {
        // Scenario C: Booking crosses midnight between zones
        // 01:00 AM on 2026-09-20 in Cairo (UTC+3) is 22:00 PM on 2026-09-19 in UTC
        // And in New York (UTC-4), it is 18:00 PM on 2026-09-19
        $cairoStart = CarbonImmutable::parse('2026-09-20 01:00:00', 'Africa/Cairo');
        $cairoEnd = CarbonImmutable::parse('2026-09-20 02:00:00', 'Africa/Cairo');

        $utcStart = $this->service->toUtc($cairoStart);
        $utcEnd = $this->service->toUtc($cairoEnd);

        $snapshot = $this->service->createBookingSnapshot(
            startUtc: $utcStart,
            endUtc: $utcEnd,
            customerTimezone: 'America/New_York',
            businessTimezone: 'Africa/Cairo'
        );

        // Business date is Sept 20, Customer date is Sept 19
        $this->assertSame('2026-09-20', $snapshot['business_local_date_at_booking']);
        $this->assertSame('01:00:00', $snapshot['business_local_start_time_at_booking']);
        $this->assertSame('2026-09-19', $snapshot['customer_local_date_at_booking']);
        $this->assertSame('18:00:00', $snapshot['customer_local_start_time_at_booking']);
        $this->assertSame('+03:00', $snapshot['business_utc_offset_at_booking']);
        $this->assertSame('-04:00', $snapshot['customer_utc_offset_at_booking']);
    }

    public function test_booking_snapshot_preserves_historical_local_context(): void
    {
        $startUtc = CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC');
        $endUtc = CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC');

        $snapshot = $this->service->createBookingSnapshot(
            startUtc: $startUtc,
            endUtc: $endUtc,
            customerTimezone: 'Europe/Berlin',
            businessTimezone: 'Africa/Cairo'
        );

        $this->assertSame('2026-10-15 10:00:00', $snapshot['start_at_utc']);
        $this->assertSame('2026-10-15 11:00:00', $snapshot['end_at_utc']);
        $this->assertSame('Europe/Berlin', $snapshot['customer_timezone']);
        $this->assertSame('Africa/Cairo', $snapshot['business_timezone']);

        // In October: Cairo is UTC+3, Berlin is CEST (UTC+2)
        // 10:00 UTC is 13:00 Cairo and 12:00 Berlin
        $this->assertSame('13:00:00', $snapshot['business_local_start_time_at_booking']);
        $this->assertSame('12:00:00', $snapshot['customer_local_start_time_at_booking']);
        $this->assertSame('+03:00', $snapshot['business_utc_offset_at_booking']);
        $this->assertSame('+02:00', $snapshot['customer_utc_offset_at_booking']);
    }
}
