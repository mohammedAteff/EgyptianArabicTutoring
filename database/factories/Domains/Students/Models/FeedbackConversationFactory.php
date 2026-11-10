<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\FeedbackConversation;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FeedbackConversation> */
class FeedbackConversationFactory extends Factory
{
    protected $model = FeedbackConversation::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'subject' => fake()->sentence(3), 'type' => 'question', 'area' => 'portal', 'status' => 'open', 'version' => 1, 'last_sequence' => 0, 'last_activity_at' => now('UTC')];
    }
}
