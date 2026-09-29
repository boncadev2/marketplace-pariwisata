<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\RefundRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefundRequest>
 */
class RefundRequestFactory extends Factory
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
            'reason' => $this->faker->sentence(),
            'status' => 'requested',
            'refundable_amount' => $this->faker->numberBetween(10000, 100000),
            'decided_by' => null,
            'decision_notes' => null,
        ];
    }
}
