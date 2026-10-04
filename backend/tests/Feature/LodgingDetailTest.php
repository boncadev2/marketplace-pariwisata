<?php

namespace Tests\Feature;

use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LodgingDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_detail_returns_selected_room_photos_and_available_rate_without_reserving(): void
    {
        $this->freezeTime();
        $room = RoomType::factory()->create(['name' => 'Homestay Terpilih', 'description' => 'Deskripsi lengkap untuk halaman detail kamar.', 'capacity' => 4, 'exterior_image_url' => 'https://images.unsplash.com/photo-exterior', 'interior_image_url' => 'https://images.unsplash.com/photo-interior', 'photos_are_illustrations' => true]);
        $room->rates()->createMany([['date' => now()->addDay()->toDateString(), 'price' => 175000], ['date' => now()->addDays(2)->toDateString(), 'price' => 100000]]);
        $room->inventories()->createMany([['date' => now()->addDay()->toDateString(), 'stock' => 3], ['date' => now()->addDays(2)->toDateString(), 'stock' => 0]]);
        RoomType::factory()->create(['name' => 'Kamar Lain']);
        $response = $this->getJson('/api/v1/lodging/rooms/'.$room->id)->assertOk()->assertJsonPath('data.id', $room->id)->assertJsonPath('data.name', 'Homestay Terpilih')->assertJsonPath('data.capacity', 4)->assertJsonPath('data.description', $room->description)->assertJsonPath('data.exterior_image_url', $room->exterior_image_url)->assertJsonPath('data.interior_image_url', $room->interior_image_url)->assertJsonPath('meta.sandbox_reservations_enabled', true);
        $this->assertSame(175000.0, (float) $response->json('data.starting_price'));
        $this->assertArrayNotHasKey('creation_key', $response->json('data'));
        $this->assertArrayNotHasKey('exterior_photo_path', $response->json('data'));
        $this->assertDatabaseCount('lodging_bookings', 0);
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'date' => now()->addDay()->toDateString(), 'stock' => 3]);
    }

    public function test_inactive_missing_and_invalid_room_ids_have_no_public_detail(): void
    {
        $room = RoomType::factory()->create(['is_active' => false]);
        $this->getJson('/api/v1/lodging/rooms/'.$room->id)->assertNotFound();
        $this->getJson('/api/v1/lodging/rooms/'.($room->id + 1))->assertNotFound();
        $this->getJson('/api/v1/lodging/rooms/invalid')->assertNotFound();
    }

    public function test_detail_uses_uploaded_photo_and_hides_untrusted_external_photo(): void
    {
        Storage::fake('local');
        $room = RoomType::factory()->create(['interior_image_url' => 'https://untrusted.test/interior.jpg']);
        $room->exterior_photo_path = 'lodging/'.$room->id.'/detail.png';
        $room->save();
        Storage::disk('local')->put($room->exterior_photo_path, file_get_contents(base_path('tests/Fixtures/umkm-photo.png')));
        $response = $this->getJson('/api/v1/lodging/rooms/'.$room->id)->assertOk()->assertJsonPath('data.interior_image_url', null)->assertJsonPath('data.starting_price', null);
        $this->assertStringStartsWith('/api/v1/lodging/rooms/'.$room->id.'/photos/exterior', $response->json('data.exterior_image_url'));
        $this->get($response->json('data.exterior_image_url'))->assertOk()->assertHeader('Content-Type', 'image/png');
    }
}
