<?php

namespace Database\Factories\Domains\Resources;

use App\Domains\Resources\Models\ResourceCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceCollection>
 */
class ResourceCollectionFactory extends Factory
{
    protected $model = ResourceCollection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'slug' => fake()->uuid(),
            'is_published' => true,
            'sort_order' => 0,
        ];
    }
}
