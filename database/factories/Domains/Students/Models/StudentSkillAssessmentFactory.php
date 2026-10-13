<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\LearningSkill;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentSkillAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentSkillAssessment>
 */
class StudentSkillAssessmentFactory extends Factory
{
    protected $model = StudentSkillAssessment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory()->verified(), 'learning_skill_id' => LearningSkill::factory(), 'state' => 'developing', 'student_visible' => false, 'assessed_at' => now('UTC'),
        ];
    }
}
