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
}
