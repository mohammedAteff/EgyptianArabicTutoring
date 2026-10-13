<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentProgressReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProgressReport>
 */
class StudentProgressReportFactory extends Factory
{
    protected $model = StudentProgressReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory()->verified(), 'date_from' => now('UTC')->subMonth()->toDateString(), 'date_to' => now('UTC')->toDateString(), 'summary' => fake()->paragraph(), 'next_goals' => fake()->sentence(), 'student_visible' => false,
        ];
    }
}
