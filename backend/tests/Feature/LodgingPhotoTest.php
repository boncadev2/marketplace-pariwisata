<?php

namespace Tests\Feature;

use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LodgingPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('room.png', file_get_contents(base_path('tests/Fixtures/umkm-photo.png')));
    }

    private function form(RoomType $room): array
    {
        $data = $this->getJson('/api/v1/dashboard/lodging-rooms?date='.now()->toDateString())->assertOk()->json('data.0');

        return [...$data, '_method' => 'PATCH', 'price' => 150000, 'stock' => 5];
    }

    public function test_admin_uploads_two_photos_and_public_catalog_streams_them_without_storage_paths(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        $room = RoomType::factory()->create();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $response = $this->post('/api/v1/dashboard/lodging-rooms/'.$room->id, [...$this->form($room), 'exterior_photo' => $this->photo(), 'interior_photo' => $this->photo()], ['Accept' => 'application/json'])->assertOk();
        $room->refresh();
        Storage::disk('local')->assertExists([$room->exterior_photo_path, $room->interior_photo_path]);
        $this->assertStringStartsWith('/api/v1/lodging/rooms/'.$room->id.'/photos/exterior', $response->json('data.exterior_photo_url'));
        $catalog = $this->getJson('/api/v1/lodging/rooms')->assertOk()->json('data.data.0');
        $this->assertArrayNotHasKey('exterior_photo_path', $catalog);
        $this->assertArrayNotHasKey('interior_photo_path', $catalog);
        $this->get($catalog['exterior_image_url'])->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($catalog['interior_image_url'])->assertOk();
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'stock' => 5]);
    }

    public function test_hidden_room_photos_require_verified_admin_and_detach_retains_file(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        $room = RoomType::factory()->create(['is_active' => false]);
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $this->actingAs($admin);
        $this->post('/api/v1/dashboard/lodging-rooms/'.$room->id, [...$this->form($room), 'exterior_photo' => $this->photo()], ['Accept' => 'application/json'])->assertOk();
        $path = $room->refresh()->exterior_photo_path;
        $url = '/api/v1/lodging/rooms/'.$room->id.'/photos/exterior';
        $this->get($url)->assertOk();
        $this->actingAs(User::factory()->create(['platform_role' => 'customer']));
        $this->get($url)->assertNotFound();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin', 'email_verified_at' => null]));
        $this->get($url)->assertNotFound();
        $this->actingAs($admin);
        $this->post('/api/v1/dashboard/lodging-rooms/'.$room->id, [...$this->form($room), 'remove_exterior_photo' => true], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.exterior_photo_url', null);
        $this->get($url)->assertNotFound();
        Storage::disk('local')->assertExists($path);
    }

    public function test_stale_upload_does_not_store_a_file_and_untrusted_input_cannot_set_path(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        $room = RoomType::factory()->create();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $data = $this->form($room);
        $room->update(['name' => 'Nama berubah']);
        $this->post('/api/v1/dashboard/lodging-rooms/'.$room->id, [...$data, 'exterior_photo' => $this->photo()], ['Accept' => 'application/json'])->assertConflict();
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->post('/api/v1/dashboard/lodging-rooms/'.$room->id, [...$this->form($room), 'exterior_photo_path' => '../private.txt'], ['Accept' => 'application/json'])->assertOk();
        $this->assertNull($room->refresh()->exterior_photo_path);
    }

    public function test_invalid_and_oversized_files_are_rejected(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        $room = RoomType::factory()->create();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->post('/api/v1/dashboard/lodging-rooms/'.$room->id, [...$this->form($room), 'exterior_photo' => UploadedFile::fake()->createWithContent('room.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'), 'interior_photo' => $this->photo()->size(5121)], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['exterior_photo', 'interior_photo']);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
