<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketMessage>
 */
class SupportTicketMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_ticket_id' => SupportTicket::factory(),
            'user_id' => fn (array $attributes) => SupportTicket::findOrFail($attributes['support_ticket_id'])->user_id,
            'body' => 'Mohon bantuan untuk pesanan ini.',
        ];
    }
}
