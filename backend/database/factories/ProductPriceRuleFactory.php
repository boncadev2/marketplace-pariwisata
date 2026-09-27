<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPriceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPriceRule>
 */
class ProductPriceRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'starts_on' => now()->startOfDay(),
            'ends_on' => now()->addMonth()->endOfDay(),
            'price' => fake()->numberBetween(10_000, 500_000),
            'priority' => 0,
            'is_active' => true,
        ];
    }
}
