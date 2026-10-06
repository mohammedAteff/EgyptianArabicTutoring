<?php

namespace App\Domains\Analytics\Services;

use App\Domains\CMS\Models\Setting;

class AnalyticsLifecycle
{
    private ?int $bookingFloor = null;

    private ?string $generation = null;

    public function bookingFloor(): int
    {
        return $this->bookingFloor ??= max(0, (int) Setting::get('analytics_reset_booking_floor', 0));
    }

    public function forget(): void
    {
        $this->bookingFloor = null;
        $this->generation = null;
    }

    public function generation(): string
    {
        return $this->generation ??= (string) Setting::get('analytics_collection_generation', 'original');
    }
}
