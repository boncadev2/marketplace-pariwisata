<?php

namespace Tests\Feature;

use App\Models\CulinaryPlace;
use App\Models\MealSlot;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CulinaryCatalogDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_detail_and_local_day_slots_are_scoped_without_reserving(): void
    {
        $this->travelTo(Carbon::parse('2026-10-02 01:00:00', 'UTC'));
        $place = CulinaryPlace::factory()->create(['location' => 'Alamat lengkap untuk pengujian', 'location_is_demo' => false]);
        $slot = MealSlot::factory()->create(['culinary_place_id' => $place->id, 'time_slot' => '2026-10-02 17:30:00', 'price' => 75000, 'capacity' => 10, 'reserved' => 2]);
        MealSlot::factory()->create(['culinary_place_id' => $place->id, 'time_slot' => '2026-10-03 17:00:00', 'price' => 100000]);
        MealSlot::factory()->create(['culinary_place_id' => $place->id, 'time_slot' => '2026-10-03 05:00:00', 'price' => 1000, 'capacity' => 2, 'reserved' => 2]);
        MealSlot::factory()->create(['time_slot' => '2026-10-03 05:00:00']);
        $hidden = CulinaryPlace::factory()->create(['is_active' => false]);
        $r = $this->getJson('/api/v1/culinary/places/'.$place->id)->assertOk()->assertJsonPath('data.location', $place->location);
        $this->assertEquals(75000, $r->json('data.starting_price'));
        $this->getJson('/api/v1/culinary/places/'.$hidden->id)->assertNotFound();
        $r = $this->getJson('/api/v1/culinary/places/'.$place->id.'/slots?date=2026-10-03')->assertOk()->assertJsonCount(2, 'data.data')->assertJsonPath('data.data.0.id', $slot->id)->assertJsonPath('data.data.0.available', 8)->assertJsonPath('meta.timezone', 'Asia/Jakarta');
        $this->getJson('/api/v1/culinary/places/'.$place->id.'/slots?date=2026-10-01')->assertUnprocessable();
        $this->getJson('/api/v1/culinary/quote?meal_slot_id='.$slot->id.'&quantity=1&culinary_place_id='.$hidden->id)->assertNotFound();
        $this->assertDatabaseCount('meal_bookings', 0);
        $this->assertDatabaseHas('meal_slots', ['id' => $slot->id, 'reserved' => 2]);
    }

    public function test_metadata_access_revision_and_coordinate_validation(): void
    {
        $place = CulinaryPlace::factory()->create();
        $url = '/api/v1/dashboard/culinary-places';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['platform_role' => 'customer']));
        $this->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin', 'email_verified_at' => null]));
        $this->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $data = $this->getJson($url)->assertOk()->json('data.0');
        $this->patchJson($url.'/'.$place->id, [...$data, 'latitude' => -6, 'longitude' => null])->assertUnprocessable();
        $this->patchJson($url.'/'.$place->id, [...$data, 'location' => 'Alamat lengkap pengujian', 'location_is_demo' => false])->assertOk()->assertJsonPath('data.location', 'Alamat lengkap pengujian');
        $this->patchJson($url.'/'.$place->id, $data)->assertConflict();
        $this->getJson('/api/v1/culinary/places/'.$place->id)->assertJsonPath('data.location', 'Alamat lengkap pengujian');
    }

    public function test_lodging_detail_exposes_address_and_coordinates(): void
    {
        $room = RoomType::factory()->create(['location' => 'Alamat lengkap homestay pengujian', 'latitude' => -6.25, 'longitude' => 106.8]);
        $this->getJson('/api/v1/lodging/rooms/'.$room->id)->assertOk()->assertJsonPath('data.location', $room->location)->assertJsonPath('data.latitude', '-6.2500000')->assertJsonPath('data.longitude', '106.8000000');
    }
}
