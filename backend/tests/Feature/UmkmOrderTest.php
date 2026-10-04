<?php

namespace Tests\Feature;

use App\Models\UmkmProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UmkmOrderTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $slug = 'kopi'): array
    {
        return ['product_slug' => $slug, 'quantity' => 2, 'expected_price' => 45000, 'customer_name' => 'Pelanggan Demo', 'customer_phone' => '081234567890', 'notes' => 'Ambil di lokasi'];
    }

    private function product(): UmkmProduct
    {
        return UmkmProduct::factory()->create(['slug' => 'kopi', 'status' => 'published', 'price' => 45000, 'stock' => 3]);
    }

    public function test_order_snapshots_price_and_retry_does_not_consume_stock_twice(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $this->actingAs($user);
        $first = $this->withHeader('Idempotency-Key', 'umkm-request-demo-01')->postJson('/api/v1/account/umkm-orders', $this->payload())->assertCreated()->assertJsonPath('data.total', 90000)->assertJsonPath('data.payment_status', 'unpaid');
        $product->refresh();
        $this->assertSame(1, $product->stock);
        $product->update(['price' => 50000]);
        $this->postJson('/api/v1/account/umkm-orders', $this->payload())->assertOk()->assertJsonPath('data.order_id', $first->json('data.order_id'))->assertJsonPath('data.product.price', 45000);
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseCount('umkm_orders', 1);
        $this->postJson('/api/v1/account/umkm-orders', [...$this->payload(), 'quantity' => 1])->assertConflict();
    }

    public function test_price_and_stock_conflicts_leave_inventory_unchanged(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create())->withHeader('Idempotency-Key', 'umkm-request-demo-02');
        $this->postJson('/api/v1/account/umkm-orders', [...$this->payload(), 'expected_price' => 100])->assertConflict();
        $this->postJson('/api/v1/account/umkm-orders', [...$this->payload(), 'quantity' => 4])->assertConflict();
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertDatabaseCount('umkm_orders', 0);
    }

    public function test_cancellation_restores_stock_once_and_ownership_is_enforced(): void
    {
        $product = $this->product();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->withHeader('Idempotency-Key', 'umkm-request-demo-03');
        $id = $this->postJson('/api/v1/account/umkm-orders', $this->payload())->json('data.order_id');
        $this->actingAs($other)->getJson('/api/v1/account/umkm-orders')->assertJsonPath('meta.total', 0);
        $this->postJson('/api/v1/account/umkm-orders/'.$id.'/cancel')->assertNotFound();
        $this->actingAs($owner)->postJson('/api/v1/account/umkm-orders/'.$id.'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson('/api/v1/account/umkm-orders/'.$id.'/cancel')->assertOk();
        $this->assertSame(3, $product->fresh()->stock);
        $this->getJson('/api/v1/account/umkm-orders?status=cancelled')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.status', 'cancelled');
        $this->getJson('/api/v1/account/umkm-orders?status=reserved_sandbox')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/account/umkm-orders?status=invalid')->assertUnprocessable();
    }

    public function test_auth_validation_and_draft_product_are_rejected(): void
    {
        $this->getJson('/api/v1/account/umkm-orders')->assertUnauthorized();
        $this->postJson('/api/v1/account/umkm-orders', $this->payload())->assertUnauthorized();
        $product = $this->product();
        $product->update(['status' => 'draft']);
        $this->actingAs(User::factory()->create())->withHeader('Idempotency-Key', 'umkm-request-demo-04');
        $this->postJson('/api/v1/account/umkm-orders', $this->payload())->assertNotFound();
        $this->postJson('/api/v1/account/umkm-orders', [...$this->payload(), 'quantity' => 0])->assertUnprocessable();
    }

    public function test_real_environment_cannot_create_sandbox_orders(): void
    {
        $this->app->instance('env', 'production');
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/v1/account/umkm-orders', $this->payload())->assertServiceUnavailable();
    }
}
