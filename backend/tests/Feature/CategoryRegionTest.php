<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Destination;
use App\Models\Partner;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryRegionTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create([
            'platform_role' => 'super_admin',
            'email_verified_at' => now(),
        ]);
    }

    public function test_unauthorized_users_cannot_access_master_data(): void
    {
        $this->getJson('/api/v1/dashboard/master-data/categories')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard/master-data/regions')->assertUnauthorized();

        $customer = User::factory()->create(['platform_role' => 'customer']);
        $this->actingAs($customer)->getJson('/api/v1/dashboard/master-data/categories')->assertForbidden();
        $this->actingAs($customer)->getJson('/api/v1/dashboard/master-data/regions')->assertForbidden();
    }

    public function test_admin_can_crud_category(): void
    {
        $admin = $this->createAdmin();

        // 1. Create category
        $createRes = $this->actingAs($admin)->postJson('/api/v1/dashboard/master-data/categories', [
            'name' => 'Wisata Bahari',
            'is_active' => true,
        ]);

        $createRes->assertCreated()
            ->assertJsonPath('data.name', 'Wisata Bahari')
            ->assertJsonPath('data.slug', 'wisata-bahari');

        $categoryId = $createRes->json('data.id');

        // 2. Read categories
        $indexRes = $this->actingAs($admin)->getJson('/api/v1/dashboard/master-data/categories');
        $indexRes->assertOk();
        $this->assertTrue(collect($indexRes->json('data'))->contains('id', $categoryId));

        // 3. Update category
        $updateRes = $this->actingAs($admin)->putJson("/api/v1/dashboard/master-data/categories/{$categoryId}", [
            'name' => 'Wisata Bahari & Pesisir',
            'slug' => 'wisata-bahari-pesisir',
            'is_active' => true,
        ]);

        $updateRes->assertOk()
            ->assertJsonPath('data.name', 'Wisata Bahari & Pesisir')
            ->assertJsonPath('data.slug', 'wisata-bahari-pesisir');

        // 4. Delete category
        $deleteRes = $this->actingAs($admin)->deleteJson("/api/v1/dashboard/master-data/categories/{$categoryId}");
        $deleteRes->assertOk();
        $this->assertDatabaseMissing('categories', ['id' => $categoryId]);
    }

    public function test_cannot_delete_category_in_use(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Alam', 'slug' => 'alam']);
        $region = Region::create(['name' => 'Kabupaten Demo', 'code' => 'DEMO-REG', 'type' => 'regency']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Mitra Demo', 'slug' => 'mitra-demo', 'status' => 'approved']);

        Destination::create([
            'partner_id' => $partner->id,
            'category_id' => $category->id,
            'region_id' => $region->id,
            'name' => 'Bukit Bintang',
            'slug' => 'bukit-bintang',
            'summary' => 'Pemandangan malam hari.',
            'description' => 'Bukit indah.',
            'publication_status' => 'published',
        ]);

        $res = $this->actingAs($admin)->deleteJson("/api/v1/dashboard/master-data/categories/{$category->id}");
        $res->assertStatus(409);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_admin_can_crud_region(): void
    {
        $admin = $this->createAdmin();

        // 1. Create parent region (Kabupaten)
        $parentRes = $this->actingAs($admin)->postJson('/api/v1/dashboard/master-data/regions', [
            'name' => 'Kabupaten Sleman',
            'code' => 'KAB-SLM',
            'type' => 'regency',
            'is_active' => true,
        ]);

        $parentRes->assertCreated()
            ->assertJsonPath('data.name', 'Kabupaten Sleman')
            ->assertJsonPath('data.code', 'KAB-SLM')
            ->assertJsonPath('data.type', 'regency');

        $parentId = $parentRes->json('data.id');

        // 2. Create child region (Desa)
        $childRes = $this->actingAs($admin)->postJson('/api/v1/dashboard/master-data/regions', [
            'name' => 'Desa Sambirejo',
            'code' => 'DESA-SMB',
            'type' => 'village',
            'parent_id' => $parentId,
            'is_active' => true,
        ]);

        $childRes->assertCreated()
            ->assertJsonPath('data.name', 'Desa Sambirejo')
            ->assertJsonPath('data.parent_id', $parentId);

        $childId = $childRes->json('data.id');

        // 3. Cannot delete parent region while it has children
        $delParentFail = $this->actingAs($admin)->deleteJson("/api/v1/dashboard/master-data/regions/{$parentId}");
        $delParentFail->assertStatus(409);

        // 4. Update child region
        $updateChild = $this->actingAs($admin)->putJson("/api/v1/dashboard/master-data/regions/{$childId}", [
            'name' => 'Desa Wisata Sambirejo',
            'code' => 'DESA-SMB',
            'type' => 'village',
            'parent_id' => $parentId,
            'is_active' => true,
        ]);

        $updateChild->assertOk()
            ->assertJsonPath('data.name', 'Desa Wisata Sambirejo');

        // 5. Delete child region
        $this->actingAs($admin)->deleteJson("/api/v1/dashboard/master-data/regions/{$childId}")->assertOk();
        $this->assertDatabaseMissing('regions', ['id' => $childId]);

        // 6. Delete parent region now succeeds
        $this->actingAs($admin)->deleteJson("/api/v1/dashboard/master-data/regions/{$parentId}")->assertOk();
        $this->assertDatabaseMissing('regions', ['id' => $parentId]);
    }
}
