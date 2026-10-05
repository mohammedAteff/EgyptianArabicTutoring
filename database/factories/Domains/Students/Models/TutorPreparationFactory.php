<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\TutorPreparation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TutorPreparation> */
class TutorPreparationFactory extends Factory
{
    protected $model = TutorPreparation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'body' => fake()->paragraph()];
    }
}
