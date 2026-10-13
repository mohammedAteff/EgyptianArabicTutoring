<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\HomeworkSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeworkSubmission>
 */
class HomeworkSubmissionFactory extends Factory
{
    protected $model = HomeworkSubmission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'homework_id' => Homework::factory(), 'body' => fake()->paragraph(), 'submission_key' => fake()->uuid(), 'submitted_at' => now('UTC'),
        ];
    }
}
