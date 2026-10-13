<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\LearningSkill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningSkill>
 */
class LearningSkillFactory extends Factory
{
    protected $model = LearningSkill::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->unique()->words(3, true), 'active' => true, 'sort_order' => 0,
        ];
    }
}
