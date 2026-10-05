<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentErrorLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentErrorLog> */
class StudentErrorLogFactory extends Factory
{
    protected $model = StudentErrorLog::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'category' => 'pronunciation', 'mistake' => 'Long vowel', 'correction' => 'Hold the vowel for two beats', 'status' => 'practising', 'student_visible' => false];
    }
}
