<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Destination;
use App\Models\Partner;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestinationCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_hides_draft_destinations(): void
    {
        $region = Region::create(['code' => 'CAT-01', 'name' => 'Wilayah', 'type' => 'regency']);
        $category = Category::create(['name' => 'Alam', 'slug' => 'alam']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Mitra', 'slug' => 'mitra', 'status' => 'approved']);
        Destination::create(['partner_id' => $partner->id, 'region_id' => $region->id, 'category_id' => $category->id, 'name' => 'Terbit', 'slug' => 'terbit', 'publication_status' => 'published']);
        Destination::create(['partner_id' => $partner->id, 'region_id' => $region->id, 'category_id' => $category->id, 'name' => 'Draft', 'slug' => 'draft', 'publication_status' => 'draft']);

        $this->getJson('/api/v1/destinations?region_id='.$region->id.'&category_id='.$category->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Terbit');
    }
}
