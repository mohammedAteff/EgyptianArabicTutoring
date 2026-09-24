<?php

namespace Database\Factories;

use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'name_normalized' => 'test student',
            'email' => fake()->unique()->safeEmail(),
            'email_normalized' => fn (array $attributes) => mb_strtolower($attributes['email']),
            'phone' => null,
            'phone_normalized' => null,
            'date_of_birth' => null,
            'identity_status' => 'legacy_unverified',
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'date_of_birth' => '1990-01-01',
            'identity_status' => 'verified',
        ]);
    }
}
