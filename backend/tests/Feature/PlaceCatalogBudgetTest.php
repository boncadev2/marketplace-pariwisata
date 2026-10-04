<?php

namespace Tests\Feature;

use App\Models\CulinaryPlace;
use App\Models\MealSlot;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceCatalogBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_uses_available_starting_prices_and_combines_search_and_sort(): void
    {
        $this->freezeTime();
        foreach ([['model' => RoomType::class, 'url' => '/api/v1/lodging/rooms'], ['model' => CulinaryPlace::class, 'url' => '/api/v1/culinary/places']] as $catalog) {
            $ids = [];
            foreach ([['A Pilihan', 50000], ['B Pilihan', 100000], ['C Pilihan', 150000], ['D Tanpa Tarif', null]] as [$name,$price]) {
                $place = $catalog['model']::factory()->create(['name' => $name]);
                $ids[] = $place->id;
                if ($price === null) {
                    continue;
                }
                if ($place instanceof RoomType) {
                    $date = now()->addDay()->toDateString();
                    $place->rates()->create(['date' => $date, 'price' => $price]);
                    $place->inventories()->create(['date' => $date, 'stock' => 2]);
                } else {
                    MealSlot::factory()->create(['culinary_place_id' => $place->id, 'price' => $price, 'capacity' => 10, 'reserved' => 0]);
                }
            }
            $r = $this->getJson($catalog['url'].'?q=Pilihan&min_price=50000&max_price=100000&sort=price_desc')->assertOk()->assertJsonCount(2, 'data.data');
            $this->assertSame([$ids[1], $ids[0]], array_column($r->json('data.data'), 'id'));
            $this->getJson($catalog['url'].'?max_price=50000')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $ids[0]);
            $this->getJson($catalog['url'].'?min_price=150000')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $ids[2]);
            $this->getJson($catalog['url'].'?min_price=100000&max_price=100000')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $ids[1]);
            $this->getJson($catalog['url'].'?max_price=0')->assertOk()->assertJsonCount(0, 'data.data');
            $this->getJson($catalog['url'])->assertJsonCount(4, 'data.data');
            foreach (['min_price=100&max_price=50', 'min_price=-1', 'max_price=invalid', 'max_price=1000000001'] as $query) {
                $this->getJson($catalog['url'].'?'.$query)->assertUnprocessable();
            }
        }
    }
}
