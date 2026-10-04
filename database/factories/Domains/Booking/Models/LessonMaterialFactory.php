<?php

namespace Database\Factories\Domains\Booking\Models;

use App\Domains\Booking\Models\LessonMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LessonMaterial> */
class LessonMaterialFactory extends Factory
{
    protected $model = LessonMaterial::class;

    /** Parent Booking must be supplied; factories never create financial/schedule context implicitly.
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['kind' => 'external_link', 'title' => fake()->sentence(3), 'url' => 'https://example.org/lesson',
            'student_visible' => false, 'sort_order' => 0];
    }

    public function visible(): static
    {
        return $this->state(fn (): array => ['student_visible' => true]);
    }
}
