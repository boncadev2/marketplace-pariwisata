<?php

namespace Database\Factories;

use App\Models\NotificationDelivery;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'deduplication_key' => hash('sha256', fake()->uuid()),
            'type' => 'confirmation',
            'recipient' => 'customer@example.test',
            'snapshot' => ['order_id' => 'ORDER-TEST', 'name' => 'Pelanggan test', 'detail' => 'Status pembayaran: berhasil.'],
            'available_at' => now(),
            'delivery_log' => [],
        ];
    }
}
