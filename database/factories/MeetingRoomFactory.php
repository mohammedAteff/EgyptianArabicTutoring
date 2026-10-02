<?php

namespace Database\Factories;

use App\Domains\Booking\Models\MeetingRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeetingRoom> */
class MeetingRoomFactory extends Factory
{
    protected $model = MeetingRoom::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['meeting_provider_id' => MeetingProviderFactory::new(), 'name' => fake()->words(3, true), 'url' => fn (array $attributes) => 'https://example.org/rooms/'.fake()->uuid(), 'url_hash' => fn (array $attributes) => hash('sha256', $attributes['url']), 'active' => true];
    }
}
