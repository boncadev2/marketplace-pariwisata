<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_reviews(): void
    {
        Review::factory()->count(3)->create(['status' => 'approved']);
        Review::factory()->create(['status' => 'pending']);

        $response = $this->getJson('/api/v1/reviews');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonMissingPath('data.0.user.email');
    }

    public function test_can_create_review(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'partner_id' => $product->partner_id, 'status' => 'paid']);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
            'commission_amount' => 0,
            'snapshot' => [],
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/reviews', [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'rating' => 5,
            'comment' => 'Excellent!',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['rating' => 5]);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => 5,
        ]);
    }
}
