<?php

namespace Database\Factories;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingPolicyDecision;
use App\Domains\Booking\Services\BookingPolicyService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingPolicyDecision> */
class BookingPolicyDecisionFactory extends Factory
{
    protected $model = BookingPolicyDecision::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['booking_id' => Booking::factory(), 'action' => 'cancellation', 'outcome' => 'restore', 'policy_snapshot' => app(BookingPolicyService::class)->current(), 'actor_type' => 'system', 'created_at' => now('UTC')];
    }
}
