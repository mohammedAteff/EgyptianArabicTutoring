<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPortalLogin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentPortalLogin>
 */
class StudentPortalLoginFactory extends Factory
{
    protected $model = StudentPortalLogin::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory()->verified(), 'timezone' => 'UTC', 'authenticated_at' => now('UTC'),
        ];
    }
}
