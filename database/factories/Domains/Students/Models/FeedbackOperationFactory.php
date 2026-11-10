<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\FeedbackConversation;
use App\Domains\Students\Models\FeedbackOperation;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FeedbackOperation> */
class FeedbackOperationFactory extends Factory
{
    protected $model = FeedbackOperation::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'conversation_id' => FeedbackConversation::factory(), 'actor_type' => 'student', 'actor_id' => 1, 'scope_id' => 0, 'request_key' => (string) Str::uuid(), 'action' => 'create', 'fingerprint' => hash('sha256', 'fixture'), 'result_sequence' => 1, 'result_version' => 1, 'created_at' => now('UTC')];
    }
}
