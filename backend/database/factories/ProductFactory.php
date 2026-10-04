<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(3),
            'type' => 'ticket',
            'currency' => 'IDR',
            'base_price' => fake()->numberBetween(10_000, 1_000_000),
            'status' => 'published',
        ];
    }
}
