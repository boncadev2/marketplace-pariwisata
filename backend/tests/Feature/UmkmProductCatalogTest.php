<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\UmkmProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UmkmProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_and_detail_expose_description_location_and_integer_price(): void
    {
        $item = UmkmProduct::factory()->create(['status' => 'published', 'slug' => 'kopi', 'name' => 'Kopi Desa', 'price' => 45000, 'description' => 'Kopi 200 gram', 'location' => 'Desa Hijau']);
        $this->getJson('/api/v1/umkm-products?q=Hijau')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.price', 45000)->assertJsonPath('data.0.location', 'Desa Hijau');
        $this->getJson('/api/v1/umkm-products/kopi')->assertOk()->assertJsonPath('data.description', 'Kopi 200 gram')->assertJsonMissingPath('data.partner_id');
        $this->getJson('/api/v1/umkm-products?q=TidakAda')->assertJsonPath('meta.total', 0);
    }

    public function test_unpublished_deleted_and_unapproved_sellers_are_hidden(): void
    {
        $draft = UmkmProduct::factory()->create(['slug' => 'draft']);
        $deleted = UmkmProduct::factory()->create(['slug' => 'deleted', 'status' => 'published']);
        $deleted->delete();
        UmkmProduct::factory()->create(['slug' => 'pending', 'status' => 'published', 'partner_id' => Partner::factory()->create(['status' => 'pending'])->id]);
        $this->getJson('/api/v1/umkm-products')->assertJsonPath('meta.total', 0);
        foreach (['draft', 'deleted', 'pending', 'missing'] as $slug) {
            $this->getJson('/api/v1/umkm-products/'.$slug)->assertNotFound();
        }
    }

    public function test_pagination_and_query_validation(): void
    {
        UmkmProduct::factory()->count(3)->create(['status' => 'published']);
        $this->getJson('/api/v1/umkm-products?per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3);
        $this->getJson('/api/v1/umkm-products?per_page=500')->assertUnprocessable();
    }
}
