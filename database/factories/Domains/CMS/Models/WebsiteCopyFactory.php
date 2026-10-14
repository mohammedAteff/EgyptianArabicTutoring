<?php

namespace Database\Factories\Domains\CMS\Models;

use App\Domains\CMS\Models\WebsiteCopy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteCopy>
 */
class WebsiteCopyFactory extends Factory
{
    protected $model = WebsiteCopy::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page' => fake()->unique()->randomElement(['home', 'about', 'pricing', 'games', 'resources']),
            'locale' => 'en',
        ];
    }
}
