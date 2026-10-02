<?php

namespace Database\Factories;

use App\Domains\Students\Models\StudentEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentEmail> */
class StudentEmailFactory extends Factory
{
    protected $model = StudentEmail::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => StudentFactory::new()->verified(), 'email_normalized' => fake()->unique()->safeEmail(), 'verified_at' => now('UTC')];
    }
}
