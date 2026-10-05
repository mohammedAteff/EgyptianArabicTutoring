<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\LearningPlan;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LearningPlan> */
class LearningPlanFactory extends Factory
{
    protected $model = LearningPlan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'title' => 'Learning goals', 'goals' => fake()->paragraph(), 'status' => 'active', 'start_date' => now()->toDateString(), 'student_visible' => false];
    }
}
