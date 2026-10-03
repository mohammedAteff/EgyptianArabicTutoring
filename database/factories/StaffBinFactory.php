<?php

namespace Database\Factories;

use App\Domains\Administration\Models\StaffBin;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffBin> */
class StaffBinFactory extends Factory
{
    protected $model = StaffBin::class;

    public function definition(): array
    {
        return ['author_id' => AdministratorFactory::new(), 'title' => fake()->sentence(3), 'body' => fake()->paragraph()];
    }
}
