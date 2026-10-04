<?php

namespace Tests\Feature;

use App\Models\CulinaryPlace;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceCatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_search_matches_name_and_address_but_excludes_hidden_places(): void
    {
        $this->freezeTime();
        foreach ([['model' => RoomType::class, 'url' => '/api/v1/lodging/rooms'], ['model' => CulinaryPlace::class, 'url' => '/api/v1/culinary/places']] as $catalog) {
            $name = $catalog['model']::factory()->create(['name' => 'Bintang Pesisir', 'location' => 'Jalan Utama']);
            $address = $catalog['model']::factory()->create(['name' => 'Tempat Terpilih', 'location' => 'Jalan Bintang 12']);
            $catalog['model']::factory()->create(['name' => 'Bintang tersembunyi', 'location' => 'Jalan Bintang', 'is_active' => false]);
            $catalog['model']::factory()->create(['name' => 'Tidak sesuai', 'location' => null]);
            $r = $this->getJson($catalog['url'].'?q=Bintang')->assertOk()->assertJsonCount(2, 'data.data')->assertJsonPath('data.total', 2);
            $this->assertEqualsCanonicalizing([$name->id, $address->id], array_column($r->json('data.data'), 'id'));
            $this->getJson($catalog['url'].'?q=TidakAda')->assertJsonCount(0, 'data.data');
        }
        $this->assertDatabaseCount('lodging_bookings', 0);
        $this->assertDatabaseCount('meal_bookings', 0);
    }

    public function test_wildcard_characters_are_literal_and_injection_is_not_executed(): void
    {
        foreach ([['model' => RoomType::class, 'url' => '/api/v1/lodging/rooms'], ['model' => CulinaryPlace::class, 'url' => '/api/v1/culinary/places']] as $catalog) {
            $literal = $catalog['model']::factory()->create(['name' => 'Tempat 20%_!', 'location' => null]);
            $catalog['model']::factory()->create(['name' => 'Tempat lainnya', 'location' => null]);
            $this->getJson($catalog['url'].'?'.http_build_query(['q' => '%_!']))->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $literal->id);
            $this->getJson($catalog['url'].'?'.http_build_query(['q' => "' OR 1=1 --"]))->assertOk()->assertJsonCount(0, 'data.data');
        }
    }

    public function test_search_pagination_validation_and_reset_use_complete_catalog(): void
    {
        foreach ([['model' => RoomType::class, 'url' => '/api/v1/lodging/rooms', 'size' => 20], ['model' => CulinaryPlace::class, 'url' => '/api/v1/culinary/places', 'size' => 12]] as $catalog) {
            for ($i = 0; $i < $catalog['size'] + 1; $i++) {
                $catalog['model']::factory()->create(['name' => 'Bintang '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
            }
            $catalog['model']::factory()->create(['name' => 'Tempat lain']);
            $this->getJson($catalog['url'].'?q=Bintang')->assertJsonCount($catalog['size'], 'data.data')->assertJsonPath('data.total', $catalog['size'] + 1);
            $this->getJson($catalog['url'].'?q=Bintang&page=2')->assertJsonCount(1, 'data.data')->assertJsonPath('data.current_page', 2);
            $this->getJson($catalog['url'].'?q=')->assertJsonPath('data.total', $catalog['size'] + 2);
            foreach (['q%5B%5D=invalid', 'q='.str_repeat('a', 101), 'page=-1'] as $query) {
                $this->getJson($catalog['url'].'?'.$query)->assertUnprocessable();
            }
        }
    }
}
