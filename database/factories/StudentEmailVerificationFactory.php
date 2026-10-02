<?php

namespace Database\Factories;

use App\Domains\Students\Models\StudentEmailVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentEmailVerification> */
class StudentEmailVerificationFactory extends Factory
{
    protected $model = StudentEmailVerification::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => StudentFactory::new()->verified(), 'email_normalized' => fake()->unique()->safeEmail(), 'token_hash' => hash('sha256', fake()->uuid()), 'expires_at' => now('UTC')->addMinutes(15), 'consumed_at' => null];
    }
}
