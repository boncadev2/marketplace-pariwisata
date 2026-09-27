<?php

namespace Database\Factories;

use App\Models\InventoryBucket;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBucket>
 */
class InventoryBucketFactory extends Factory
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
            'service_date' => fake()->dateTimeBetween('tomorrow', '+1 month')->format('Y-m-d'),
            'session_key' => 'default',
            'capacity' => 20,
            'held' => 0,
            'confirmed' => 0,
            'is_closed' => false,
        ];
    }
}
