<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_returns_only_active_categories_and_regions(): void
    {
        $parent = Region::create(['code' => 'PARENT', 'name' => 'Parent', 'type' => 'regency']);
        Region::create(['parent_id' => $parent->id, 'code' => 'ACTIVE', 'name' => 'Aktif', 'type' => 'village']);
        Region::create(['parent_id' => $parent->id, 'code' => 'INACTIVE', 'name' => 'Nonaktif', 'type' => 'village', 'is_active' => false]);
        Category::create(['name' => 'Aktif', 'slug' => 'aktif']);
        Category::create(['name' => 'Nonaktif', 'slug' => 'nonaktif', 'is_active' => false]);

        $this->getJson('/api/v1/lookup/regions?parent_id='.$parent->id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/lookup/categories')->assertOk()->assertJsonCount(1, 'data');
    }
}
