<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class LodgingCreationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['name' => 'Homestay Uji — Kamar Keluarga', 'description' => 'Data sintetis untuk pengujian pembuatan tipe kamar.', 'capacity' => 4, 'is_active' => false, 'photos_are_illustrations' => true,
            'exterior_image_url' => null, 'interior_image_url' => null, 'date' => now()->addDay()->toDateString(), 'price' => 250000, 'stock' => 3];
    }

    public function test_admin_creates_room_with_photos_daily_rate_and_inventory_atomically(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $file = fn () => UploadedFile::fake()->createWithContent('room.png', file_get_contents(base_path('tests/Fixtures/umkm-photo.png')));
        $response = $this->post('/api/v1/dashboard/lodging-rooms', [...$this->payload(), 'exterior_photo' => $file(), 'interior_photo' => $file(), 'creation_key' => 'untrusted', 'exterior_photo_path' => '../private.txt'], ['Accept' => 'application/json', 'Idempotency-Key' => 'create-room-demo-0001'])->assertCreated()->assertJsonPath('data.stock', 3)->assertJsonPath('data.capacity', 4)->assertJsonPath('data.is_active', false);
        $room = RoomType::findOrFail($response->json('data.id'));
        Storage::disk('local')->assertExists([$room->exterior_photo_path, $room->interior_photo_path]);
        $this->assertDatabaseHas('room_rates', ['room_type_id' => $room->id, 'date' => now()->addDay()->toDateString(), 'price' => 250000]);
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'stock' => 3]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lodging.room_created', 'auditable_id' => $room->id]);
        $this->post('/api/v1/dashboard/lodging-rooms', [...$this->payload(), 'exterior_photo' => $file(), 'interior_photo' => $file()], ['Accept' => 'application/json', 'Idempotency-Key' => 'create-room-demo-0001'])->assertOk()->assertJsonPath('data.id', $room->id);
        $this->assertCount(2, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertNotSame('untrusted', $room->creation_key);
        $this->assertArrayNotHasKey('creation_key', $response->json('data'));
        $this->getJson('/api/v1/lodging/rooms')->assertJsonCount(0, 'data.data');
    }

    public function test_repeated_save_returns_same_room_and_changed_payload_conflicts(): void
    {
        $this->freezeTime();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $headers = ['Idempotency-Key' => 'create-room-demo-0002'];
        $payload = [...$this->payload(), 'is_active' => true];
        $first = $this->postJson('/api/v1/dashboard/lodging-rooms', $payload, $headers)->assertCreated();
        $this->postJson('/api/v1/dashboard/lodging-rooms', [...$payload, 'capacity' => '4', 'price' => '250000', 'stock' => '3', 'is_active' => '1', 'photos_are_illustrations' => '1'], $headers)->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
        $this->postJson('/api/v1/dashboard/lodging-rooms', [...$payload, 'stock' => 5], $headers)->assertConflict();
        $this->assertDatabaseCount('room_types', 1);
        $this->assertDatabaseCount('room_inventories', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->getJson('/api/v1/lodging/rooms')->assertJsonCount(1, 'data.data');
    }

    public function test_guest_customer_and_unverified_admin_cannot_create_rooms(): void
    {
        $this->postJson('/api/v1/dashboard/lodging-rooms', [])->assertUnauthorized();
        foreach ([['platform_role' => 'customer'], ['platform_role' => 'super_admin', 'email_verified_at' => null]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            $this->postJson('/api/v1/dashboard/lodging-rooms', [])->assertForbidden();
        }
        $this->assertDatabaseCount('room_types', 0);
    }

    public function test_invalid_input_or_missing_key_does_not_create_room(): void
    {
        $this->freezeTime();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->postJson('/api/v1/dashboard/lodging-rooms', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->postJson('/api/v1/dashboard/lodging-rooms', [...$this->payload(), 'name' => '', 'capacity' => 0, 'stock' => -1, 'price' => 0, 'date' => now()->subDay()->toDateString()], ['Idempotency-Key' => 'create-room-demo-0003'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'capacity', 'stock', 'price', 'date']);
        $this->assertDatabaseCount('room_types', 0);
        $this->assertDatabaseCount('room_rates', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_failed_transaction_rolls_back_room_and_removes_new_file(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        AuditLog::creating(function (): void {
            throw new RuntimeException('Simulated audit storage failure');
        });
        $file = UploadedFile::fake()->createWithContent('room.png', file_get_contents(base_path('tests/Fixtures/umkm-photo.png')));
        $this->post('/api/v1/dashboard/lodging-rooms', [...$this->payload(), 'exterior_photo' => $file], ['Accept' => 'application/json', 'Idempotency-Key' => 'create-room-demo-0004'])->assertStatus(500);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('room_types', 0);
        $this->assertDatabaseCount('room_inventories', 0);
        $this->assertDatabaseCount('room_rates', 0);
    }

    public function test_create_is_disabled_outside_local_and_testing(): void
    {
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->app->instance('env', 'staging');
        $this->postJson('/api/v1/dashboard/lodging-rooms', [])->assertStatus(503);
        $this->assertDatabaseCount('room_types', 0);
    }
}
