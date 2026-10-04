<?php

namespace Tests\Feature;

use App\Models\CulinaryPlace;
use App\Models\MealSlot;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceCatalogSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_sort_keeps_unpriced_places_last_and_orders_equal_prices_by_name(): void
    {
        $this->freezeTime();
        foreach ([['model' => RoomType::class, 'url' => '/api/v1/lodging/rooms'], ['model' => CulinaryPlace::class, 'url' => '/api/v1/culinary/places']] as $catalog) {
            $ids = [];
            foreach ([['A Mahal', 100000], ['B Murah', 50000], ['C Tanpa Tarif', null], ['D Sama', 50000]] as [$name,$price]) {
                $place = $catalog['model']::factory()->create(['name' => $name]);
                $ids[] = $place->id;
                if ($price === null) {
                    continue;
                }
                if ($place instanceof RoomType) {
                    $date = now()->addDay()->toDateString();
                    $place->rates()->create(['date' => $date, 'price' => $price]);
                    $place->inventories()->create(['date' => $date, 'stock' => 3]);
                } else {
                    MealSlot::factory()->create(['culinary_place_id' => $place->id, 'price' => $price, 'reserved' => 0, 'capacity' => 5]);
                }
            }
            $asc = $this->getJson($catalog['url'].'?sort=price_asc')->assertOk()->json('data.data');
            $this->assertSame([$ids[1], $ids[3], $ids[0], $ids[2]], array_column($asc, 'id'));
            $desc = $this->getJson($catalog['url'].'?sort=price_desc')->assertOk()->json('data.data');
            $this->assertSame([$ids[0], $ids[1], $ids[3], $ids[2]], array_column($desc, 'id'));
            $default = $this->getJson($catalog['url'])->assertOk()->json('data.data');
            $this->assertSame($ids, array_column($default, 'id'));
            $this->getJson($catalog['url'].'?sort=price_desc&q=Murah')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $ids[1]);
            $this->getJson($catalog['url'].'?sort=invalid')->assertUnprocessable();
        }
    }

    public function test_sorted_pagination_does_not_repeat_places(): void
    {
        $this->freezeTime();
        for ($i = 0; $i < 13; $i++) {
            $place = CulinaryPlace::factory()->create(['name' => 'Restoran '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
            MealSlot::factory()->create(['culinary_place_id' => $place->id, 'price' => 10000 + $i * 1000, 'reserved' => 0]);
        }
        $first = $this->getJson('/api/v1/culinary/places?sort=price_asc')->assertOk()->assertJsonCount(12, 'data.data')->json('data.data');
        $last = $this->getJson('/api/v1/culinary/places?sort=price_asc&page=2')->assertOk()->assertJsonCount(1, 'data.data')->json('data.data');
        $this->assertSame([], array_values(array_intersect(array_column($first, 'id'), array_column($last, 'id'))));
        $this->assertEquals(22000,$last[0]['starting_price']);
    }
}
