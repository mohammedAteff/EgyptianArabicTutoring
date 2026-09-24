<?php

namespace Tests\Support;

use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Availability\Services\SlotResolver;
use App\Domains\Booking\Models\SessionType;
use Carbon\CarbonImmutable;

trait IssuesBookingSlotIds
{
    protected function slotIdFor(SessionType $sessionType, string $startUtc, string $timezone, string $visitorToken): string
    {
        $instant = CarbonImmutable::parse($startUtc, 'UTC');
        $date = $instant->setTimezone($timezone)->startOfDay();
        $slots = app(AvailabilityService::class)->getAvailableSlotsGroupedByDate(
            $sessionType,
            $timezone,
            $date,
            $date,
            $visitorToken,
        );

        foreach ($slots[$date->toDateString()] ?? [] as $slot) {
            if ($slot['slot_start_utc'] === $instant->toDateTimeString()) {
                return app(SlotResolver::class)->issue($sessionType, $slot, $timezone, $visitorToken);
            }
        }

        $this->fail("Expected server-generated slot {$instant->toIso8601String()} in {$timezone} to be available.");
    }
}
