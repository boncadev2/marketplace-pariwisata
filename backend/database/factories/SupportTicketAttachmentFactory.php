<?php

namespace Database\Factories;

use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketAttachment>
 */
class SupportTicketAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_ticket_message_id' => SupportTicketMessage::factory(),
            'disk' => 'local',
            'path' => 'support-attachments/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
        ];
    }
}
