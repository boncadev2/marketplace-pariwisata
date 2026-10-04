<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_id' => fn (array $attributes) => Order::factory()->create(['user_id' => $attributes['user_id']])->id,
            'category' => 'booking',
            'status' => 'open',
        ];
    }
}
