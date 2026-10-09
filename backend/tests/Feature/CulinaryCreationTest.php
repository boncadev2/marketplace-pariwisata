<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CulinaryCreationTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $extra = []): array
    {
        return [...['name' => 'Rumah Makan Pengujian', 'description' => 'Deskripsi rumah makan untuk pengujian aplikasi.', 'location' => 'Alamat pengujian lengkap', 'latitude' => null, 'longitude' => null, 'location_is_demo' => true, 'image_url' => null, 'photos_are_illustrations' => false, 'is_active' => false], ...$extra];
    }

    public function test_creation_retry_returns_same_place_and_keeps_new_place_inactive(): void
    {
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $header = ['Idempotency-Key' => 'a0fd115e-c2d6-4bd5-993c-c6bac587cc01'];
        $r = $this->postJson('/api/v1/dashboard/culinary-places', $this->data(['creation_key' => 'forged']), $header)->assertCreated()->assertJsonPath('data.is_active', false);
        $id = $r->json('data.id');
        $this->postJson('/api/v1/dashboard/culinary-places', $this->data(), $header)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('culinary_places', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertArrayNotHasKey('creation_key', $r->json('data'));
        $this->assertArrayNotHasKey('creation_fingerprint', $r->json('data'));
        $this->getJson('/api/v1/culinary/places/'.$id)->assertNotFound();
        $this->postJson('/api/v1/dashboard/culinary-places', $this->data(['name' => 'Informasi berbeda']), $header)->assertConflict();
    }

    public function test_key_is_scoped_to_admin_and_a_place_can_be_published_after_creation(): void
    {
        $header = ['Idempotency-Key' => 'a0fd115e-c2d6-4bd5-993c-c6bac587cc02'];
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $r = $this->postJson('/api/v1/dashboard/culinary-places', $this->data(), $header)->assertCreated();
        $this->patchJson('/api/v1/dashboard/culinary-places/'.$r->json('data.id'), [...$r->json('data'), 'is_active' => true])->assertOk();
        $this->getJson('/api/v1/culinary/places/'.$r->json('data.id'))->assertOk();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->postJson('/api/v1/dashboard/culinary-places', $this->data(), $header)->assertCreated();
        $this->assertDatabaseCount('culinary_places', 2);
    }

    public function test_access_validation_and_coordinates_prevent_invalid_creation(): void
    {
        $url = '/api/v1/dashboard/culinary-places';
        $header = ['Idempotency-Key' => 'a0fd115e-c2d6-4bd5-993c-c6bac587cc03'];
        $this->postJson($url, $this->data(), $header)->assertUnauthorized();
        foreach ([['platform_role' => 'customer'], ['platform_role' => 'super_admin', 'email_verified_at' => null]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            $this->postJson($url, $this->data(), $header)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->postJson($url, $this->data())->assertUnprocessable();
        foreach ([['name' => ''], ['latitude' => -6, 'longitude' => null], ['latitude' => -100, 'longitude' => 106], ['image_url' => 'https://untrusted.test/photo.jpg']] as $extra) {
            $this->postJson($url, $this->data($extra), $header)->assertUnprocessable();
        }
        $this->assertDatabaseCount('culinary_places', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
