<?php

namespace Tests\Feature;

use App\Models\PartnerMember;
use App\Models\UmkmProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UmkmPartnerOrderTest extends TestCase
{
    use RefreshDatabase;

    private function order(): array
    {
        $product = UmkmProduct::factory()->create(['status' => 'published', 'price' => 45000, 'stock' => 3]);
        $buyer = User::factory()->create();
        $id = $this->actingAs($buyer)->withHeader('Idempotency-Key', fake()->uuid())->postJson('/api/v1/account/umkm-orders', ['product_slug' => $product->slug, 'quantity' => 2, 'expected_price' => 45000, 'customer_name' => 'Demo Buyer', 'customer_phone' => '081234567890'])->assertCreated()->json('data.order_id');

        return [$product, $buyer, $id];
    }

    private function manager(UmkmProduct $product, string $role = 'owner', bool $active = true): User
    {
        $user = User::factory()->create();
        PartnerMember::create(['user_id' => $user->id, 'partner_id' => $product->partner_id, 'role' => $role, 'is_active' => $active]);

        return $user;
    }

    public function test_partner_progress_is_visible_to_buyer_and_cannot_skip_or_reopen(): void
    {
        [$product, $buyer, $id] = $this->order();
        $manager = $this->manager($product, 'manager');
        $this->actingAs($manager)->getJson('/api/v1/dashboard/umkm-orders')->assertOk()->assertJsonPath('meta.total', 1);
        $url = '/api/v1/dashboard/umkm-orders/'.$id;
        $this->patchJson($url, ['status' => 'completed_sandbox'])->assertConflict();
        foreach (['processing_sandbox', 'ready_sandbox', 'completed_sandbox'] as $status) {
            $this->patchJson($url, ['status' => $status])->assertOk()->assertJsonPath('data.status', $status)->assertJsonPath('data.payment_status', 'unpaid');
            $this->patchJson($url, ['status' => $status])->assertOk();
        }
        $this->patchJson($url, ['status' => 'cancelled'])->assertConflict();
        $this->assertSame(1, $product->fresh()->stock);
        $this->actingAs($buyer)->getJson('/api/v1/account/umkm-orders?status=completed_sandbox')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.status', 'completed_sandbox');
        $this->postJson('/api/v1/account/umkm-orders/'.$id.'/cancel')->assertConflict();
    }

    public function test_partner_cancellation_restores_stock_once_and_other_tenant_cannot_access(): void
    {
        [$product, $buyer, $id] = $this->order();
        $other = UmkmProduct::factory()->create();
        $this->actingAs($this->manager($other))->getJson('/api/v1/dashboard/umkm-orders')->assertJsonPath('meta.total', 0);
        $url = '/api/v1/dashboard/umkm-orders/'.$id;
        $this->patchJson($url, ['status' => 'cancelled'])->assertNotFound();
        $this->assertSame(1, $product->fresh()->stock);
        $this->actingAs($this->manager($product))->patchJson($url, ['status' => 'processing_sandbox'])->assertOk();
        $product->delete();
        $this->patchJson($url, ['status' => 'cancelled'])->assertOk();
        $this->patchJson($url, ['status' => 'cancelled'])->assertOk();
        $this->actingAs($buyer)->postJson('/api/v1/account/umkm-orders/'.$id.'/cancel')->assertOk();
        $this->assertSame(3, $product->fresh()->stock);
    }

    public static function deniedRoles(): array
    {
        return [['staff', true], ['owner', false], ['customer', true]];
    }

    #[DataProvider('deniedRoles')]
    public function test_unprivileged_users_cannot_list_or_change_orders(string $role, bool $active): void
    {
        [$product, , $id] = $this->order();
        $user = $role === 'customer' ? User::factory()->create() : $this->manager($product, $role, $active);
        $this->actingAs($user)->getJson('/api/v1/dashboard/umkm-orders')->assertForbidden();
        $this->patchJson('/api/v1/dashboard/umkm-orders/'.$id, ['status' => 'processing_sandbox'])->assertForbidden();
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_admin_can_manage_but_production_and_invalid_status_are_blocked(): void
    {
        [$product, , $id] = $this->order();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']))->getJson('/api/v1/dashboard/umkm-orders')->assertOk()->assertJsonPath('meta.total', 1);
        $url = '/api/v1/dashboard/umkm-orders/'.$id;
        $this->patchJson($url, ['status' => 'paid'])->assertUnprocessable();
        $this->patchJson($url, ['status' => 'processing_sandbox', 'payment_status' => 'paid'])->assertOk()->assertJsonPath('data.payment_status', 'unpaid');
        $this->app->detectEnvironment(fn () => 'production');
        $this->patchJson($url, ['status' => 'ready_sandbox'])->assertServiceUnavailable();
        $this->assertDatabaseHas('umkm_orders', ['public_id' => $id, 'status' => 'processing_sandbox']);
    }
}
