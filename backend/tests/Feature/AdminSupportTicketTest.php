<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_tickets(): void
    {
        $customer = User::factory()->create(['platform_role' => 'customer']);
        $this->actingAs($customer)->getJson('/api/v1/dashboard/support-tickets')->assertForbidden();
    }

    public function test_admin_can_list_and_filter_tickets(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        SupportTicket::factory()->create(['user_id' => $user->id, 'order_id' => $order->id, 'status' => 'open']);
        SupportTicket::factory()->create(['user_id' => $user->id, 'order_id' => $order->id, 'status' => 'closed']);

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/support-tickets?status=open');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_can_view_and_reply_to_ticket(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $ticket = SupportTicket::factory()->create(['user_id' => $user->id, 'order_id' => $order->id, 'status' => 'open']);
        $ticket->messages()->create(['user_id' => $user->id, 'body' => 'Saya butuh bantuan refund.']);

        // View ticket
        $showRes = $this->actingAs($admin)->getJson("/api/v1/dashboard/support-tickets/{$ticket->id}");
        $showRes->assertOk()->assertJsonPath('data.messages.0.body', 'Saya butuh bantuan refund.');

        // Reply to ticket
        $replyRes = $this->actingAs($admin)->postJson("/api/v1/dashboard/support-tickets/{$ticket->id}/reply", [
            'message' => 'Halo, kami sedang memeriksa permintaan Anda.',
        ]);
        $replyRes->assertStatus(201)->assertJsonPath('data.author', 'support');

        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'body' => 'Halo, kami sedang memeriksa permintaan Anda.',
        ]);
    }

    public function test_admin_can_close_and_reopen_ticket(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $ticket = SupportTicket::factory()->create(['user_id' => $user->id, 'order_id' => $order->id, 'status' => 'open']);

        $response = $this->actingAs($admin)->patchJson("/api/v1/dashboard/support-tickets/{$ticket->id}/status", [
            'status' => 'closed',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'closed');
        $this->assertSame('closed', $ticket->fresh()->status);
    }
}
