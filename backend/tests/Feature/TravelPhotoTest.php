<?php

namespace Tests\Feature;

use App\Models\Destination;
use App\Models\Product;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TravelPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_destination_gallery_updates_public_detail_and_cache_without_exposing_paths(): void
    {
        Storage::fake('local');
        $destination = Destination::factory()->published()->create();
        $this->getJson('/api/v1/destinations')->assertJsonPath('data.0.photos', []);
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $endpoint = '/api/v1/dashboard/travel/destinations/'.$destination->id.'/photos';
        $revision = $this->getJson($endpoint)->assertOk()->json('revision');
        $r = $this->postJson($endpoint, ['revision' => $revision, 'caption' => 'Area luar', 'is_illustration' => false, 'photo' => $this->photo()])->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.path');
        $url = $r->json('data.0.url');
        Storage::disk('local')->assertExists($destination->photos()->first()->path);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('/api/v1/destinations/'.$destination->slug)->assertJsonPath('data.photos.0.caption', 'Area luar');
        $this->getJson('/api/v1/destinations')->assertJsonCount(1, 'data.0.photos');
        $this->postJson($endpoint, ['revision' => $revision, 'caption' => 'Stale', 'is_illustration' => true, 'photo' => $this->photo()])->assertConflict();
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->deleteJson($endpoint.'/'.$r->json('data.0.id'), ['revision' => $r->json('revision')])->assertOk()->assertJsonCount(0, 'data');
        $this->get($url)->assertNotFound();
    }

    public function test_package_gallery_is_hidden_for_drafts_and_visitors_cannot_manage_it(): void
    {
        Storage::fake('local');
        $product = Product::factory()->create(['type' => 'package']);
        TourPackage::factory()->create(['product_id' => $product->id, 'duration_days' => 1, 'meeting_point' => 'Gerbang', 'pricing_mode' => 'per_person', 'minimum_participants' => 1, 'maximum_participants' => 10, 'status' => 'draft']);
        $endpoint = '/api/v1/dashboard/travel/packages/'.$product->id.'/photos';
        $this->getJson($endpoint)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($endpoint)->assertForbidden();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $revision = $this->getJson($endpoint)->json('revision');
        $response = $this->postJson($endpoint, ['revision' => $revision, 'caption' => 'Itinerary', 'is_illustration' => true, 'photo' => $this->photo()])->assertOk();
        $url = $response->json('data.0.url');
        $this->get($url)->assertNotFound();
        $product->tourPackage->update(['status' => 'published']);
        $this->get($url)->assertOk();
        $this->postJson($endpoint, ['revision' => $response->json('revision'), 'caption' => 'SVG', 'is_illustration' => false, 'photo' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')])->assertUnprocessable();
        $product->delete();
        $this->get($url)->assertNotFound();
    }

    private function photo(): UploadedFile
    {
        return new UploadedFile(base_path('tests/Fixtures/umkm-photo.png'), 'travel.png', 'image/png', null, true);
    }
}
