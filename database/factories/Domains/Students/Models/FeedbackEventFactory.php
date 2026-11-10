<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\FeedbackConversation;
use App\Domains\Students\Models\FeedbackEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FeedbackEvent> */
class FeedbackEventFactory extends Factory
{
    protected $model = FeedbackEvent::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['conversation_id' => FeedbackConversation::factory(), 'sequence' => 1, 'actor_type' => 'student', 'actor_id' => 1, 'kind' => 'message', 'body' => fake()->paragraph(), 'created_at' => now('UTC')];
    }
}
