<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\EntitlementType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EntitlementType> */
class EntitlementTypeFactory extends Factory
{
    protected $model = EntitlementType::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => fake()->unique()->slug(), 'label' => 'Synthetic session entitlement', 'nominal_minutes' => 60, 'active' => true];
    }
}
