<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\LearningMilestone;
use App\Domains\Students\Models\LearningPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LearningMilestone> */
class LearningMilestoneFactory extends Factory
{
    protected $model = LearningMilestone::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['learning_plan_id' => LearningPlan::factory(), 'title' => fake()->sentence(3), 'status' => 'pending'];
    }
}
