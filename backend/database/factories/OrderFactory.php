<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Partner;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_id' => fn () => Partner::factory()->create(['region_id' => Region::factory()->create(['code' => fake()->unique()->bothify('REG-####'), 'name' => 'Wilayah test', 'type' => 'regency'])->id, 'name' => 'Mitra test', 'slug' => fake()->unique()->uuid(), 'status' => 'approved'])->id,
            'public_id' => fake()->uuid(),
            'idempotency_key' => fake()->uuid(),
            'guest_access_hash' => 'test-only',
            'customer_name' => 'Pelanggan test',
            'customer_email' => 'customer@example.test',
            'currency' => 'IDR',
            'total' => 100,
            'policy_snapshot' => [],
        ];
    }
}
