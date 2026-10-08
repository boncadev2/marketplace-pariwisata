<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
        $title = fake()->sentence(5);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999),
            'image_url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80',
            'category' => fake()->randomElement(['Panduan Wisata', 'Tips Liburan', 'Kuliner Lokal', 'Tradisi & Budaya']),
            'author_name' => fake()->name(),
            'excerpt' => fake()->paragraph(2),
            'body' => fake()->paragraphs(4, true),
            'status' => 'published',
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'meta_title' => $title,
            'meta_description' => fake()->sentence(10),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }
}
