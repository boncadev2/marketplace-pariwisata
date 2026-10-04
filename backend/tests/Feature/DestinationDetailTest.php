<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Destination;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestinationDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_detail_exposes_only_catalog_fields_without_authentication(): void
    {
        $region = Region::factory()->create(['name' => 'Desa Wisata']);
        $category = Category::factory()->create(['name' => 'Alam', 'slug' => 'alam']);
        $destination = Destination::factory()->published()->create([
            'region_id' => $region->id,
            'category_id' => $category->id,
            'name' => 'Bukit Hijau',
            'slug' => 'bukit-hijau',
            'summary' => 'Pemandangan perbukitan.',
            'description' => 'Jalur berjalan kaki untuk pengunjung.',
            'latitude' => -7.125,
            'longitude' => 110.25,
        ]);

        $this->getJson('/api/v1/destinations/bukit-hijau')
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $destination->id,
                'name' => 'Bukit Hijau',
                'slug' => 'bukit-hijau',
                'publication_status' => 'published',
                'summary' => 'Pemandangan perbukitan.',
                'photos' => [],
                'description' => 'Jalur berjalan kaki untuk pengunjung.',
                'address' => null,
                'location_is_demo' => false,
                'region' => ['id' => $region->id, 'name' => 'Desa Wisata'],
                'category' => ['id' => $category->id, 'name' => 'Alam'],
                'latitude' => -7.125,
                'longitude' => 110.25,
            ]]);
    }

    public function test_draft_deleted_and_missing_destinations_return_404(): void
    {
        Destination::factory()->create(['slug' => 'draft-rahasia']);
        $deleted = Destination::factory()->published()->create(['slug' => 'destinasi-dihapus']);
        $deleted->delete();

        $this->getJson('/api/v1/destinations/draft-rahasia')->assertNotFound()->assertJsonMissingPath('data');
        $this->getJson('/api/v1/destinations/destinasi-dihapus')->assertNotFound()->assertJsonMissingPath('data');
        $this->getJson('/api/v1/destinations/tidak-ada')->assertNotFound();
    }

    public function test_optional_category_and_coordinates_remain_null(): void
    {
        Destination::factory()->published()->create(['slug' => 'tanpa-koordinat']);

        $this->getJson('/api/v1/destinations/tanpa-koordinat')->assertOk()
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.latitude', null)
            ->assertJsonPath('data.longitude', null);
    }

    public function test_conditional_detail_changes_after_edit_and_returns_404_after_unpublishing(): void
    {
        $destination = Destination::factory()->published()->create(['slug' => 'detail-cache', 'summary' => 'Ringkasan awal']);
        $first = $this->getJson('/api/v1/destinations/detail-cache')->assertOk();
        $etag = $first->headers->get('ETag');
        $this->assertNotNull($etag);
        $this->withHeader('If-None-Match', $etag)->getJson('/api/v1/destinations/detail-cache')->assertNotModified();

        $destination->update(['summary' => 'Ringkasan terbaru']);
        $this->withHeader('If-None-Match', $etag)->getJson('/api/v1/destinations/detail-cache')
            ->assertOk()->assertJsonPath('data.summary', 'Ringkasan terbaru');

        $destination->update(['publication_status' => 'draft']);
        $this->withHeader('If-None-Match', $etag)->getJson('/api/v1/destinations/detail-cache')->assertNotFound();
    }

    public function test_mobile_search_filters_and_pagination_exclude_other_and_draft_destinations(): void
    {
        $region = Region::factory()->create();
        $category = Category::factory()->create(['name' => 'Alam', 'slug' => 'alam']);
        Destination::factory()->published()->create(['region_id' => $region->id, 'category_id' => $category->id, 'name' => 'Bukit A']);
        Destination::factory()->published()->create(['region_id' => $region->id, 'category_id' => $category->id, 'name' => 'Bukit B']);
        Destination::factory()->published()->create(['name' => 'Bukit Wilayah Lain']);
        Destination::factory()->create(['region_id' => $region->id, 'category_id' => $category->id, 'name' => 'Bukit Draft']);
        Destination::factory()->published()->create(['region_id' => $region->id, 'category_id' => $category->id, 'name' => 'Pantai']);

        $this->getJson('/api/v1/destinations?q=Bukit&region_id='.$region->id.'&category_id='.$category->id.'&per_page=1&page=2')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bukit B')
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.total', 2);
    }
}
