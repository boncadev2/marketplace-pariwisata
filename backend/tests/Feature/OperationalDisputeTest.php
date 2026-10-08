<?php

namespace Tests\Feature;

use App\Models\OperationalDispute;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalDisputeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_disputes(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        OperationalDispute::factory()->count(3)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/disputes');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_dispute(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/disputes', [
            'order_id' => $order->id,
            'reason' => 'Service not provided',
            'description' => 'The guide did not show up.',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['reason' => 'Service not provided']);

        $this->assertDatabaseHas('operational_disputes', [
            'order_id' => $order->id,
            'reporter_id' => $user->id,
            'reason' => 'Service not provided',
        ]);
    }

    public function test_user_only_sees_their_own_disputes(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $order1 = Order::factory()->create(['user_id' => $user1->id]);
        $order2 = Order::factory()->create(['user_id' => $user2->id]);

        OperationalDispute::factory()->create(['reporter_id' => $user1->id, 'order_id' => $order1->id]);
        OperationalDispute::factory()->create(['reporter_id' => $user2->id, 'order_id' => $order2->id]);

        $response = $this->actingAs($user1)->getJson('/api/v1/disputes');
        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_can_create_dispute_using_public_id(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'public_id' => 'ORD-TEST-999']);

        $response = $this->actingAs($user)->postJson('/api/v1/disputes', [
            'order_id' => 'ORD-TEST-999',
            'reason' => 'Fasilitas tidak sesuai',
            'description' => 'AC homestay tidak dingin.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('operational_disputes', [
            'order_id' => $order->id,
            'reporter_id' => $user->id,
            'reason' => 'Fasilitas tidak sesuai',
        ]);
    }

    public function test_admin_can_update_dispute_status_and_resolution(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $dispute = OperationalDispute::factory()->create([
            'order_id' => $order->id,
            'reporter_id' => $user->id,
            'status' => 'open',
        ]);

        $headers = $this->sensitiveHeaders($admin);

        $response = $this->actingAs($admin)->patchJson("/api/v1/disputes/{$dispute->id}", [
            'status' => 'resolved',
            'resolution' => 'Mediasi berhasil dengan kompensasi voucher.',
        ], $headers);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'resolved')
            ->assertJsonPath('resolution', 'Mediasi berhasil dengan kompensasi voucher.');

        $this->assertDatabaseHas('operational_disputes', [
            'id' => $dispute->id,
            'status' => 'resolved',
            'resolution' => 'Mediasi berhasil dengan kompensasi voucher.',
        ]);
    }
}
