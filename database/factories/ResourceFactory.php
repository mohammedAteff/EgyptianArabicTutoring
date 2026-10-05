<?php

namespace Database\Factories;

use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<resource> */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['category_id' => fn () => ResourceCategory::query()->create(['name' => 'Synthetic learning', 'slug' => fake()->unique()->slug()])->id,
            'title' => fake()->sentence(3), 'slug' => fake()->unique()->slug(), 'status' => 'published',
            'published_at' => now()->subMinute(), 'external_url' => 'https://example.test/practice', 'is_gated' => true];
    }
}
