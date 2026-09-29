<?php

namespace Tests\Feature;

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
        Review::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/reviews');

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data');
    }

    public function test_can_create_review(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/reviews', [
            'user_id' => $user->id,
            'product_id' => $product->id,
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
