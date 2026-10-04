<?php

namespace Tests\Feature;

use App\Models\CulinaryPlace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CulinaryPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function photo(): UploadedFile
    {
        return new UploadedFile(base_path('tests/Fixtures/umkm-photo.png'), 'restaurant.png', 'image/png', null, true);
    }

    public function test_admin_upload_is_used_in_catalog_and_detail_without_exposing_path(): void
    {
        Storage::fake('local');
        $place = CulinaryPlace::factory()->create();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $data = $this->getJson('/api/v1/dashboard/culinary-places')->json('data.0');
        $r = $this->postJson('/api/v1/dashboard/culinary-places/'.$place->id.'/photo', ['photo' => $this->photo(), 'revision' => $data['revision'], 'photos_are_illustrations' => true, 'photo_path' => 'forged'])->assertOk();
        $path = $place->fresh()->photo_path;
        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith('culinary/'.$place->id.'/', $path);
        $url = $r->json('data.uploaded_photo_url');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('/api/v1/culinary/places/'.$place->id)->assertJsonPath('data.image_url', $url)->assertJsonPath('data.photos_are_illustrations', true)->assertJsonMissingPath('data.photo_path');
        $this->getJson('/api/v1/culinary/places')->assertJsonPath('data.data.0.image_url', $url);
        $this->assertNotSame($data['revision'], $r->json('data.revision'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'culinary.photo_uploaded']);
    }

    public function test_stale_revision_cannot_store_a_file_or_overwrite_the_photo(): void
    {
        Storage::fake('local');
        $place = CulinaryPlace::factory()->create();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $data = $this->getJson('/api/v1/dashboard/culinary-places')->json('data.0');
        $place->update(['name' => 'Updated restaurant name']);
        $this->postJson('/api/v1/dashboard/culinary-places/'.$place->id.'/photo', ['photo' => $this->photo(), 'revision' => $data['revision'], 'photos_are_illustrations' => false])->assertConflict();
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertNull($place->fresh()->photo_path);
    }

    public function test_non_admin_cannot_upload_or_view_an_inactive_place_photo(): void
    {
        Storage::fake('local');
        $place = CulinaryPlace::factory()->create(['is_active' => false]);
        $place->photo_path = 'culinary/'.$place->id.'/test.png';
        $place->save();
        Storage::disk('local')->put($place->photo_path, file_get_contents(base_path('tests/Fixtures/umkm-photo.png')));
        $url = '/api/v1/dashboard/culinary-places/'.$place->id.'/photo';
        $this->postJson($url, [])->assertUnauthorized();
        $this->get($place->photoUrl())->assertNotFound();
        foreach ([['platform_role' => 'customer'], ['platform_role' => 'super_admin', 'email_verified_at' => null]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            $this->postJson($url, [])->assertForbidden();
            $this->get($place->photoUrl())->assertNotFound();
        }
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->get($place->photoUrl())->assertOk();
    }

    public function test_invalid_file_and_another_places_path_cannot_be_published(): void
    {
        Storage::fake('local');
        $place = CulinaryPlace::factory()->create();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $data = $this->getJson('/api/v1/dashboard/culinary-places')->json('data.0');
        foreach ([UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml'), UploadedFile::fake()->create('large.png', 5121, 'image/png')] as $file) {
            $this->postJson('/api/v1/dashboard/culinary-places/'.$place->id.'/photo', ['photo' => $file, 'revision' => $data['revision'], 'photos_are_illustrations' => false])->assertUnprocessable();
        }
        $place->photo_path = 'culinary/999/other.png';
        $place->save();
        Storage::disk('local')->put($place->photo_path, 'private');
        $this->get('/api/v1/culinary/places/'.$place->id.'/photo')->assertNotFound();
        $this->getJson('/api/v1/culinary/places/'.$place->id)->assertJsonPath('data.image_url',null);
    }
}
