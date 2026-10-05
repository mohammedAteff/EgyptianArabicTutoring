<?php

namespace Database\Factories\Domains\Students\Models;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\TeachingTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeachingTag> */
class TeachingTagFactory extends Factory
{
    protected $model = TeachingTag::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'label' => fake()->word(), 'student_visible' => false];
    }
}
