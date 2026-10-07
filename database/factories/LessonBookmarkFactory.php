<?php

namespace Database\Factories;

use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonBookmark;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LessonBookmark> */
class LessonBookmarkFactory extends Factory
{
    protected $model = LessonBookmark::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['student_id' => Student::factory()->verified(), 'block_id' => LessonBlock::factory(), 'lesson_id' => fn (array $attributes) => LessonBlock::query()->findOrFail($attributes['block_id'])->lesson_id, 'position_milliseconds' => 12000, 'label' => fake()->sentence()];
    }
}
