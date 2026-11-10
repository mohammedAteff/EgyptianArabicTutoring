<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\FeedbackConversation;
use App\Domains\Students\Models\FeedbackReadState;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FeedbackReadState> */
class FeedbackReadStateFactory extends Factory
{
    protected $model = FeedbackReadState::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['conversation_id' => FeedbackConversation::factory(), 'actor_type' => 'student', 'actor_id' => 1, 'last_sequence' => 0];
    }
}
