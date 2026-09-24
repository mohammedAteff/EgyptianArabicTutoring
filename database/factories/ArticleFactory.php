<?php

namespace Database\Factories;

use App\Domains\CMS\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'slug' => fake()->unique()->slug(),
            'excerpt' => fake()->sentence(),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'status' => 'draft',
            'author_id' => AdministratorFactory::new(),
            'locale' => 'en',
        ];
    }
}
