<?php

namespace Database\Factories;

use App\Models\InventoryBucket;
use App\Models\InventoryHold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryHold>
 */
class InventoryHoldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_bucket_id' => InventoryBucket::factory(),
            'public_id' => fake()->uuid(),
            'quantity' => 1,
            'expires_at' => now()->addMinutes(15),
            'state' => 'active',
        ];
    }
}
