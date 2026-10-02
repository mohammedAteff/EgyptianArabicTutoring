<?php

namespace Database\Factories;

use App\Domains\Booking\Models\MeetingProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeetingProvider> */
class MeetingProviderFactory extends Factory
{
    protected $model = MeetingProvider::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->company(), 'active' => true, 'is_default' => false, 'sort_order' => 10];
    }
}
