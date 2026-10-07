<?php

namespace Database\Factories;

use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonNote;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LessonNote> */
class LessonNoteFactory extends Factory
{
    protected $model = LessonNote::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'lesson_id' => Lesson::factory(), 'body' => fake()->sentence(), 'lock_version' => 1];
    }
}
