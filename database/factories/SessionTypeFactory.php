<?php

namespace Database\Factories;

use App\Domains\Booking\Models\SessionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SessionType> */
class SessionTypeFactory extends Factory
{
    protected $model = SessionType::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['title' => 'Synthetic lesson', 'slug' => fake()->unique()->slug(), 'description' => null,
            'duration_minutes' => 60, 'price' => '40.00', 'currency' => 'USD', 'active' => true, 'funding_mode' => 'direct'];
    }
}
