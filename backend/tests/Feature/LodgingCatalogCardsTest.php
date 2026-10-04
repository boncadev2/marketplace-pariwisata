<?php

namespace Tests\Feature;

use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LodgingCatalogCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cards_show_photos_and_lowest_future_rate_with_stock(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->startOfDay());
        $room = RoomType::factory()->create(['exterior_image_url' => 'https://images.unsplash.com/photo-exterior?w=900', 'interior_image_url' => 'https://images.unsplash.com/photo-interior?w=900', 'photos_are_illustrations' => true]);
        foreach ([['2026-10-01', 50000, 3], ['2026-10-03', 120000, 0], ['2026-10-04', 150000, 3], ['2026-10-05', 180000, 3], ['2026-10-06', 100000, null], ['2027-10-05', 20000, 3]] as [$date, $price, $stock]) {
            $room->rates()->create(['date' => $date, 'price' => $price]);
            if ($stock !== null) {
                $room->inventories()->create(['date' => $date, 'stock' => $stock]);
            }
        }
        $response = $this->getJson('/api/v1/lodging/rooms')->assertOk()->assertJsonPath('data.data.0.exterior_image_url', $room->exterior_image_url)->assertJsonPath('data.data.0.interior_image_url', $room->interior_image_url)->assertJsonPath('data.data.0.photos_are_illustrations', true)->assertJsonPath('meta.price_basis', 'lowest_available_nightly_rate');
        $this->assertSame(150000.0, (float) $response->json('data.data.0.starting_price'));
    }

    public function test_missing_rates_show_null_price_and_untrusted_photos_are_hidden(): void
    {
        $room = RoomType::factory()->create(['exterior_image_url' => 'https://example.test/image.jpg', 'interior_image_url' => 'javascript:alert(1)']);
        $room->rates()->create(['date' => now()->addDay()->toDateString(), 'price' => 100000]);
        RoomType::factory()->create(['is_active' => false]);
        $this->getJson('/api/v1/lodging/rooms')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.starting_price', null)->assertJsonPath('data.data.0.exterior_image_url', null)->assertJsonPath('data.data.0.interior_image_url', null);
    }
}
