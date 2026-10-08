<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_coupons(): void
    {
        $customer = User::factory()->create(['platform_role' => 'customer']);
        $this->actingAs($customer)->getJson('/api/v1/dashboard/coupons')->assertForbidden();
    }

    public function test_admin_can_create_coupon(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);

        $response = $this->actingAs($admin)->postJson('/api/v1/dashboard/coupons', [
            'code' => 'promo2026',
            'name' => 'Promo Diskon Wisata 2026',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'minimum_spend' => 100000,
            'maximum_discount' => 50000,
            'global_quota' => 100,
            'user_quota' => 1,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'PROMO2026')
            ->assertJsonPath('data.discount_type', 'percentage');

        $this->assertDatabaseHas('coupons', ['code' => 'PROMO2026']);
    }

    public function test_admin_can_list_and_update_coupon(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $coupon = Coupon::factory()->create(['code' => 'HEMAT10']);

        // List
        $listRes = $this->actingAs($admin)->getJson('/api/v1/dashboard/coupons');
        $listRes->assertOk()->assertJsonCount(1, 'data');

        // Update
        $updateRes = $this->actingAs($admin)->putJson("/api/v1/dashboard/coupons/{$coupon->id}", [
            'code' => 'HEMAT20',
            'name' => 'Diskon Diperbarui',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'minimum_spend' => 50000,
            'is_active' => true,
        ]);

        $updateRes->assertOk()->assertJsonPath('data.code', 'HEMAT20');
        $this->assertSame('HEMAT20', $coupon->fresh()->code);
    }

    public function test_admin_can_toggle_and_delete_coupon(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $coupon = Coupon::factory()->create(['is_active' => true]);

        // Toggle
        $toggleRes = $this->actingAs($admin)->patchJson("/api/v1/dashboard/coupons/{$coupon->id}/toggle");
        $toggleRes->assertOk()->assertJsonPath('data.is_active', false);

        // Delete
        $delRes = $this->actingAs($admin)->deleteJson("/api/v1/dashboard/coupons/{$coupon->id}");
        $delRes->assertStatus(204);
        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }
}
