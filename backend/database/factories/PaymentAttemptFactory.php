<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
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
            'provider' => 'sandbox',
            'provider_reference' => fake()->unique()->uuid(),
            'status' => 'pending',
            'currency' => 'IDR',
            'amount' => 100,
        ];
    }
}
