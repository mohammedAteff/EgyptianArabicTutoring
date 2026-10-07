<?php

namespace Database\Factories;

use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LessonBlock> */
class LmsLessonBlockFactory extends Factory
{
    protected $model = LessonBlock::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['lesson_id' => Lesson::factory(), 'kind' => 'bunny_video', 'status' => 'placeholder', 'sort_order' => 0];
    }
}
