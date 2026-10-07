<?php

namespace Database\Factories;

use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Section> */
class LmsSectionFactory extends Factory
{
    protected $model = Section::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['course_id' => Course::factory(), 'title' => fake()->sentence(3), 'sort_order' => 0, 'status' => 'draft'];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => 'published', 'published_at' => now('UTC')->subMinute()]);
    }
}
