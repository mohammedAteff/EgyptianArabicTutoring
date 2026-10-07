<?php

namespace Database\Factories;

use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LearningVisit;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LearningVisit> */
class LearningVisitFactory extends Factory
{
    protected $model = LearningVisit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'course_id' => Course::factory(), 'accessed_at' => now('UTC')];
    }
}
