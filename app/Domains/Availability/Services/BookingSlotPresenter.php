<?php

namespace App\Domains\Availability\Services;

use App\Domains\Booking\Models\SessionType;
use Carbon\CarbonImmutable;

class BookingSlotPresenter
{
    public function __construct(private AvailabilityService $availability, private SlotResolver $resolver) {}

    /** @return array<string, list<array<string, mixed>>> */
    public function forMonth(SessionType $session, string $timezone, CarbonImmutable $from, CarbonImmutable $to, string $visitor): array
    {
        $days = $this->availability->getAvailableSlotsGroupedByDate(
            sessionType: $session, customerTimezone: $timezone, fromDate: $from, toDate: $to, currentVisitorToken: $visitor,
        );
        foreach ($days as &$slots) {
            foreach ($slots as &$slot) {
                $slot['slot_id'] = $this->resolver->issue($session, $slot, $timezone, $visitor);
                $slot['id'] = $slot['slot_id'];
                $slot['label'] = CarbonImmutable::parse($slot['slot_start_utc'], 'UTC')->setTimezone($timezone)->format('g:i A');
            }
            unset($slot);
        }
        unset($slots);

        return $days;
    }
}
