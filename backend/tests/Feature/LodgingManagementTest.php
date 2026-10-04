<?php

namespace Tests\Feature;

use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LodgingManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $room): array
    {
        return [...$room, 'price' => 175000, 'stock' => 4];
    }

    public function test_admin_can_set_daily_availability_and_catalog_information(): void
    {
        $this->freezeTime();
        $room = RoomType::factory()->create();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $date = now()->addDay()->toDateString();
        $data = $this->getJson('/api/v1/dashboard/lodging-rooms?date='.$date)->assertOk()->json('data.0');
        $this->patchJson('/api/v1/dashboard/lodging-rooms/'.$room->id, [...$this->payload($data), 'name' => 'Homestay Uji', 'exterior_image_url' => 'https://images.unsplash.com/photo-exterior', 'is_active' => false])->assertOk()->assertJsonPath('data.stock', 4);
        $this->assertDatabaseHas('room_types', ['id' => $room->id, 'name' => 'Homestay Uji', 'is_active' => false]);
        $this->assertDatabaseHas('room_rates', ['room_type_id' => $room->id, 'date' => $date, 'price' => 175000]);
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'date' => $date, 'stock' => 4]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lodging.room_updated', 'auditable_id' => $room->id]);
        $this->getJson('/api/v1/lodging/rooms')->assertJsonCount(0, 'data.data');
    }

    public function test_stale_stock_or_rate_cannot_be_overwritten(): void
    {
        $this->freezeTime();
        $room = RoomType::factory()->create();
        $date = now()->addDay()->toDateString();
        $room->inventories()->create(['date' => $date, 'stock' => 5]);
        $room->rates()->create(['date' => $date, 'price' => 150000]);
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $data = $this->getJson('/api/v1/dashboard/lodging-rooms?date='.$date)->json('data.0');
        $room->inventories()->where('date', $date)->update(['stock' => 3]);
        $this->patchJson('/api/v1/dashboard/lodging-rooms/'.$room->id, $this->payload($data))->assertConflict();
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'stock' => 3]);
        $data = $this->getJson('/api/v1/dashboard/lodging-rooms?date='.$date)->json('data.0');
        $room->rates()->where('date', $date)->update(['price' => 160000]);
        $this->patchJson('/api/v1/dashboard/lodging-rooms/'.$room->id, $this->payload($data))->assertConflict();
        $this->assertDatabaseHas('room_rates', ['room_type_id' => $room->id, 'price' => 160000]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_guest_customer_and_unverified_admin_cannot_manage_rooms(): void
    {
        $this->freezeTime();
        $room = RoomType::factory()->create();
        $url = '/api/v1/dashboard/lodging-rooms';
        $this->getJson($url.'?date='.now()->toDateString())->assertUnauthorized();
        $this->patchJson($url.'/'.$room->id, [])->assertUnauthorized();
        foreach ([['platform_role' => 'customer'], ['platform_role' => 'super_admin', 'email_verified_at' => null]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            $this->getJson($url.'?date='.now()->toDateString())->assertForbidden();
            $this->patchJson($url.'/'.$room->id, [])->assertForbidden();
        }
    }

    public function test_invalid_photo_negative_stock_and_past_dates_do_not_change_rooms(): void
    {
        $this->freezeTime();
        $room = RoomType::factory()->create();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $data = $this->getJson('/api/v1/dashboard/lodging-rooms?date='.now()->toDateString())->json('data.0');
        $this->patchJson('/api/v1/dashboard/lodging-rooms/'.$room->id, [...$this->payload($data), 'stock' => -1, 'price' => 0, 'capacity' => 0, 'exterior_image_url' => 'https://example.test/image', 'interior_image_url' => 'javascript:alert(1)', 'date' => now()->subDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors(['stock', 'price', 'capacity', 'exterior_image_url', 'interior_image_url', 'date']);
        $this->assertDatabaseCount('room_rates', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_nonlocal_environment_is_read_only(): void
    {
        $this->freezeTime();
        $room = RoomType::factory()->create();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->app->instance('env', 'staging');
        $this->getJson('/api/v1/dashboard/lodging-rooms?date='.now()->toDateString())->assertOk()->assertJsonPath('meta.editing_available', false);
        $this->patchJson('/api/v1/dashboard/lodging-rooms/'.$room->id, [])->assertStatus(503);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
