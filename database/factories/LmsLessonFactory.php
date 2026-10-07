<?php

namespace Database\Factories;

use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lesson> */
class LmsLessonFactory extends Factory
{
    protected $model = Lesson::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['section_id' => Section::factory(), 'course_id' => fn (array $attributes) => Section::query()->findOrFail($attributes['section_id'])->course_id, 'title' => fake()->sentence(3), 'slug' => fake()->unique()->slug(), 'sort_order' => 0, 'status' => 'draft'];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => 'published', 'published_at' => now('UTC')->subMinute()]);
    }
}
