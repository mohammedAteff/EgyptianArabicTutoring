<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use App\Domains\Students\Models\StudentBookmark;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentBookmark>
 */
class StudentBookmarkFactory extends Factory
{
    protected $model = StudentBookmark::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory()->verified(), 'kind' => 'note', 'target_id' => fn (array $attributes): int => StudentBin::factory()->create(['student_id' => $attributes['student_id'], 'student_visible' => true])->id,
        ];
    }
}
