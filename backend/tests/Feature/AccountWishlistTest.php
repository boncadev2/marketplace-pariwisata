<?php

namespace Tests\Feature;

use App\Models\Destination;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountWishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_for_guest_and_404_for_unpublished_destination(): void
    {
        $draft = Destination::factory()->create();
        $this->postJson('/api/v1/account/wishlist', ['destination_slug' => $draft->slug])->assertUnauthorized();

        $this->actingAs(User::factory()->create())->postJson('/api/v1/account/wishlist', ['destination_slug' => $draft->slug])->assertNotFound();
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_repeated_save_is_idempotent_and_list_is_private(): void
    {
        $destination = Destination::factory()->published()->create();
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $first = $this->actingAs($owner)->postJson('/api/v1/account/wishlist', ['destination_slug' => $destination->slug, 'user_id' => $other->id])->assertOk()->assertJsonPath('data.destination.slug', $destination->slug);
        $this->actingAs($owner)->postJson('/api/v1/account/wishlist', ['destination_slug' => $destination->slug])->assertOk();

        $this->assertDatabaseCount('wishlist_items', 1);
        $this->assertDatabaseHas('wishlist_items', ['id' => $first->json('data.id'), 'user_id' => $owner->id]);
        $this->actingAs($other)->getJson('/api/v1/account/wishlist')->assertOk()->assertJsonPath('meta.total', 0);
        $this->actingAs($owner)->getJson('/api/v1/account/wishlist')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_unpublished_saved_destination_is_marked_unavailable_and_only_owner_can_delete(): void
    {
        $destination = Destination::factory()->published()->create();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $item = WishlistItem::factory()->create(['user_id' => $owner->id, 'destination_id' => $destination->id]);
        $destination->update(['publication_status' => 'draft']);

        $this->actingAs($owner)->getJson('/api/v1/account/wishlist')->assertOk()->assertJsonPath('data.0.available', false)->assertJsonPath('data.0.destination', null);
        $this->actingAs($other)->deleteJson('/api/v1/account/wishlist/'.$item->id)->assertNotFound();
        $this->actingAs($owner)->deleteJson('/api/v1/account/wishlist/'.$item->id)->assertNoContent();
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_invalid_slug_returns_422(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/v1/account/wishlist', [])->assertUnprocessable()->assertJsonValidationErrors('destination_slug');

    }
}
