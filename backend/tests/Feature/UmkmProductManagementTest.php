<?php

namespace Tests\Feature;

use App\Models\PartnerMember;
use App\Models\UmkmProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UmkmProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(int $partnerId): array
    {
        return ['partner_id' => $partnerId, 'name' => 'Produk Uji', 'description' => 'Deskripsi produk lokal untuk pengujian.', 'location' => 'Lokasi Demo', 'price' => 45000, 'unit' => 'pcs', 'stock' => 10, 'status' => 'draft'];
    }

    private function member(UmkmProduct $product, string $role = 'owner', bool $active = true): User
    {
        $user = User::factory()->create();
        PartnerMember::create(['partner_id' => $product->partner_id, 'user_id' => $user->id, 'role' => $role, 'is_active' => $active]);

        return $user;
    }

    public function test_owner_creates_draft_publishes_and_hides_without_changing_slug(): void
    {
        $seed = UmkmProduct::factory()->create();
        $this->actingAs($this->member($seed));
        $payload = $this->payload($seed->partner_id);
        $created = $this->postJson('/api/v1/dashboard/umkm-products', [...$payload, 'is_demo' => false, 'slug' => 'untrusted-slug'])->assertCreated()->assertJsonPath('data.is_demo', true);
        $slug = $created->json('data.slug');
        $this->assertNotSame('untrusted-slug', $slug);
        $this->getJson('/api/v1/umkm-products/'.$slug)->assertNotFound();
        $response = $this->patchJson('/api/v1/dashboard/umkm-products/'.$slug, [...$payload, 'status' => 'published', 'revision' => $created->json('data.revision')])->assertOk()->assertJsonPath('data.slug', $slug);
        $this->getJson('/api/v1/umkm-products/'.$slug)->assertOk()->assertJsonPath('data.stock', 10);
        $this->patchJson('/api/v1/dashboard/umkm-products/'.$slug, [...$payload, 'revision' => $response->json('data.revision')])->assertOk();
        $this->getJson('/api/v1/umkm-products/'.$slug)->assertNotFound();
        $this->assertDatabaseHas('umkm_products', ['slug' => $slug, 'partner_id' => $seed->partner_id, 'status' => 'draft']);
    }

    public function test_old_editor_cannot_overwrite_stock_consumed_by_order(): void
    {
        $product = UmkmProduct::factory()->create(['status' => 'published', 'stock' => 10, 'price' => 45000]);
        $manager = $this->member($product, 'manager');
        $old = $this->actingAs($manager)->getJson('/api/v1/dashboard/umkm-products')->assertOk()->json('data.0');
        $buyer = User::factory()->create();
        $order = $this->actingAs($buyer)->withHeader('Idempotency-Key', fake()->uuid())->postJson('/api/v1/account/umkm-orders', ['product_slug' => $product->slug, 'quantity' => 2, 'expected_price' => 45000, 'customer_name' => 'Demo Buyer', 'customer_phone' => '081234567890'])->assertCreated();
        $this->actingAs($manager)->patchJson('/api/v1/dashboard/umkm-products/'.$product->slug, [...$this->payload($product->partner_id), 'revision' => $old['revision']])->assertConflict();
        $this->assertSame(8, $product->fresh()->stock);
        $new = $this->getJson('/api/v1/dashboard/umkm-products')->json('data.0');
        $otherPartner = UmkmProduct::factory()->create()->partner_id;
        $this->patchJson('/api/v1/dashboard/umkm-products/'.$product->slug, [...$this->payload($otherPartner), 'price' => 50000, 'stock' => 8, 'revision' => $new['revision']])->assertOk()->assertJsonPath('data.partner_id', $product->partner_id);
        $this->actingAs($buyer)->getJson('/api/v1/account/umkm-orders')->assertJsonPath('data.0.product.price', 45000)->assertJsonPath('data.0.total', 90000);
        $this->postJson('/api/v1/account/umkm-orders/'.$order->json('data.order_id').'/cancel')->assertOk();
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_other_partner_cannot_list_edit_or_create_for_target_partner(): void
    {
        $own = UmkmProduct::factory()->create();
        $other = UmkmProduct::factory()->create();
        $this->actingAs($this->member($own))->getJson('/api/v1/dashboard/umkm-products')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.slug', $own->slug);
        $this->patchJson('/api/v1/dashboard/umkm-products/'.$other->slug, [...$this->payload($other->partner_id), 'revision' => str_repeat('a', 64)])->assertNotFound();
        $this->postJson('/api/v1/dashboard/umkm-products', $this->payload($other->partner_id))->assertNotFound();
        $this->assertDatabaseCount('umkm_products', 2);
    }

    public static function roles(): array
    {
        return [['staff', true], ['owner', false], ['customer', true]];
    }

    #[DataProvider('roles')]
    public function test_unprivileged_users_are_denied(string $role, bool $active): void
    {
        $product = UmkmProduct::factory()->create();
        $user = $role === 'customer' ? User::factory()->create() : $this->member($product, $role, $active);
        $this->actingAs($user)->getJson('/api/v1/dashboard/umkm-products')->assertForbidden();
        $this->postJson('/api/v1/dashboard/umkm-products', $this->payload($product->partner_id))->assertForbidden();
        $this->patchJson('/api/v1/dashboard/umkm-products/'.$product->slug, [...$this->payload($product->partner_id), 'revision' => str_repeat('a', 64)])->assertForbidden();
    }

    public function test_auth_validation_admin_and_production_write_boundaries(): void
    {
        $product = UmkmProduct::factory()->create();
        $this->getJson('/api/v1/dashboard/umkm-products')->assertUnauthorized();
        $this->postJson('/api/v1/dashboard/umkm-products', $this->payload($product->partner_id))->assertUnauthorized();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']))->getJson('/api/v1/dashboard/umkm-products')->assertOk()->assertJsonPath('meta.total', 1);
        $this->postJson('/api/v1/dashboard/umkm-products', [...$this->payload($product->partner_id), 'price' => -1, 'stock' => -1, 'status' => 'deleted'])->assertUnprocessable()->assertJsonValidationErrors(['price', 'stock', 'status']);
        $this->patchJson('/api/v1/dashboard/umkm-products/'.$product->slug, $this->payload($product->partner_id))->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->app->detectEnvironment(fn () => 'production');
        $this->postJson('/api/v1/dashboard/umkm-products', $this->payload($product->partner_id))->assertServiceUnavailable();
        $this->getJson('/api/v1/dashboard/umkm-products')->assertJsonPath('meta.editing_available', false);
        $this->assertDatabaseCount('umkm_products', 1);
    }
}
