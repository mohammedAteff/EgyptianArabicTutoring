<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Availability\Models\AvailabilityException;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvernightAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_save_and_edit_a_window_past_midnight(): void
    {
        $admin = Administrator::factory()->create();
        $values = ['weekday' => 0, 'start_time' => '08:00', 'end_time' => '00:40', 'buffer_minutes' => 10, 'min_notice_hours' => 0, 'max_horizon_days' => 60];
        $this->actingAs($admin)->post(route('admin.availability.rules.store'), $values)->assertSessionHasNoErrors();
        $rule = AvailabilityRule::query()->sole();
        $this->assertSame('24:40:00', $rule->end_time);
        $this->get(route('admin.availability.index'))->assertSee('(+1 day)')->assertSee('value="00:40"', false);
        $this->put(route('admin.availability.rules.update', $rule), [...$values, 'end_time' => '02:30'])->assertSessionHasNoErrors();
        $this->assertSame('26:30:00', $rule->fresh()->end_time);
    }

    public function test_equal_start_and_end_times_are_rejected_without_mutation(): void
    {
        $this->actingAs(Administrator::factory()->create())->post(route('admin.availability.rules.store'),
            ['weekday' => 0, 'start_time' => '08:00', 'end_time' => '08:00'])->assertSessionHasErrors('end_time');
        $this->assertDatabaseCount('availability_rules', 0);
    }

    public function test_next_day_slots_are_found_when_search_starts_after_midnight_and_can_be_held(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 00:00:00', 'UTC'));
        $type = SessionType::factory()->create(['duration_minutes' => 60]);
        $this->overnightRule();
        $service = app(AvailabilityService::class);
        $monday = CarbonImmutable::parse('2026-10-26', 'Africa/Cairo');
        $slots = $service->getAvailableSlotsGroupedByDate($type, 'Africa/Cairo', $monday, $monday);
        $slot = collect($slots['2026-10-26'])->firstWhere('business_start_time', '00:20');
        $this->assertNotNull($slot);
        $this->assertSame('01:20', $slot['business_end_time']);
        $this->assertSame(10, $service->resolveEffectiveBuffer(CarbonImmutable::parse($slot['slot_start_utc'], 'UTC')));
        $hold = app(BookingHoldService::class)->acquireHold(visitorToken: 'overnight-owner', sessionToken: 'overnight-session', sessionType: $type,
            startUtc: CarbonImmutable::parse($slot['slot_start_utc'], 'UTC'), endUtc: CarbonImmutable::parse($slot['slot_end_utc'], 'UTC'));
        $this->assertSame('active', $hold->status);
        $this->assertDatabaseCount('booking_holds', 1);
        $this->assertFalse($service->isSlotAvailable($type, $hold->slot_start_utc->addMinutes(60), $hold->slot_end_utc->addMinutes(60), 'other-visitor'));
        $projected = $service->getAvailableSlotsGroupedByDate($type, 'America/New_York', $monday->subDay(), $monday);
        $this->assertNotEmpty($projected['2026-10-25']);
    }

    public function test_date_exceptions_override_the_previous_nights_spillover(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20', 'UTC'));
        $type = SessionType::factory()->create(['duration_minutes' => 60]);
        $this->overnightRule();
        AvailabilityException::create(['date' => '2026-10-26', 'type' => 'blocked']);
        $monday = CarbonImmutable::parse('2026-10-26', 'Africa/Cairo');
        $service = app(AvailabilityService::class);
        $this->assertSame([], $service->getAvailableSlotsGroupedByDate($type, 'Africa/Cairo', $monday, $monday));
        $start = $monday->setTime(0, 20)->utc();
        $this->assertFalse($service->isSlotAvailable($type, $start, $start->addHour()));
        $sunday = $monday->subDay();
        $slots = $service->getAvailableSlotsGroupedByDate($type, 'Africa/Cairo', $sunday, $sunday);
        $this->assertSame(['22:00'], array_column($slots['2026-10-25'], 'business_start_time'));
    }

    public function test_overlapping_weekly_windows_do_not_duplicate_the_same_slot(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20', 'UTC'));
        $type = SessionType::factory()->create(['duration_minutes' => 60]);
        $this->overnightRule();
        AvailabilityRule::create(['weekday' => 1, 'start_time' => '00:20:00', 'end_time' => '01:20:00', 'buffer_minutes' => 10, 'min_notice_hours' => 0, 'enabled' => true]);
        $monday = CarbonImmutable::parse('2026-10-26', 'Africa/Cairo');
        $slots = app(AvailabilityService::class)->getAvailableSlotsGroupedByDate($type, 'Africa/Cairo', $monday, $monday);
        $this->assertCount(1, $slots['2026-10-26']);
    }

    public function test_date_specific_special_hours_support_overnight_end_times(): void
    {
        $this->actingAs(Administrator::factory()->create())->post(route('admin.availability.exceptions.store'),
            ['date' => '2026-10-26', 'is_blocked' => '0', 'start_time' => '22:00', 'end_time' => '01:30'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('availability_exceptions', ['end_time' => '25:30:00']);
    }

    public function test_overnight_slots_preserve_elapsed_duration_across_cairo_dst_changes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-04-01', 'UTC'));
        $type = SessionType::factory()->create(['duration_minutes' => 60]);
        AvailabilityRule::create(['weekday' => 4, 'start_time' => '22:00:00', 'end_time' => '26:00:00', 'buffer_minutes' => 10, 'min_notice_hours' => 0, 'max_horizon_days' => 180, 'enabled' => true]);
        $service = app(AvailabilityService::class);
        foreach (['2026-04-23', '2026-10-29'] as $date) {
            $this->travelTo(CarbonImmutable::parse($date, 'Africa/Cairo')->subDays(3));
            $day = CarbonImmutable::parse($date, 'Africa/Cairo');
            $groups = $service->getAvailableSlotsGroupedByDate($type, 'Africa/Cairo', $day, $day->addDay());
            $this->assertNotEmpty($groups);
            foreach ($groups as $slots) {
                foreach ($slots as $slot) {
                    $start = CarbonImmutable::parse($slot['slot_start_utc'], 'UTC');
                    $end = CarbonImmutable::parse($slot['slot_end_utc'], 'UTC');
                    $this->assertSame(3600, $end->getTimestamp() - $start->getTimestamp());
                    $this->assertSame(60, $service->resolveSlotConfiguration($type, $start, $end)['duration_minutes']);
                }
            }
        }
    }

    public function test_a_window_ending_in_the_dst_midnight_gap_keeps_earlier_valid_slots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-04-20', 'UTC'));
        $type = SessionType::factory()->create(['duration_minutes' => 60]);
        AvailabilityRule::create(['weekday' => 4, 'start_time' => '22:00:00', 'end_time' => '24:00:00', 'buffer_minutes' => 10, 'min_notice_hours' => 0, 'enabled' => true]);
        $service = app(AvailabilityService::class);
        $day = CarbonImmutable::parse('2026-04-23', 'Africa/Cairo');
        $groups = $service->getAvailableSlotsGroupedByDate($type, 'Africa/Cairo', $day, $day);
        $this->assertSame(['22:00'], array_column($groups['2026-04-23'], 'business_start_time'));
        $start = CarbonImmutable::parse($groups['2026-04-23'][0]['slot_start_utc'], 'UTC');
        $this->assertSame(10, $service->resolveEffectiveBuffer($start));
        $this->assertSame(60, $service->resolveSlotConfiguration($type, $start, $start->addHour())['duration_minutes']);
    }

    private function overnightRule(): AvailabilityRule
    {
        return AvailabilityRule::create(['weekday' => 0, 'start_time' => '22:00:00', 'end_time' => '26:00:00', 'buffer_minutes' => 10, 'min_notice_hours' => 0, 'enabled' => true]);
    }
}
