<?php

namespace Tests\Feature;

use App\Models\PartnerMember;
use App\Models\UmkmProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UmkmShippingTest extends TestCase
{
    use RefreshDatabase;

    private function payload(UmkmProduct $product): array
    {
        return ['product_slug' => $product->slug, 'quantity' => 2, 'expected_price' => 45000, 'customer_name' => 'Pembeli Uji', 'customer_phone' => '081234567890', 'fulfillment' => 'delivery', 'shipping_address' => 'Alamat sintetis untuk pengujian pengiriman, bukan alamat nyata.', 'postal_code' => '12345', 'expected_shipping_fee' => 20000];
    }

    private function product(): UmkmProduct
    {
        return UmkmProduct::factory()->create(['status' => 'published', 'price' => 45000, 'stock' => 5, 'delivery_available' => true, 'shipping_fee' => 20000]);
    }

    private function manager(UmkmProduct $product): User
    {
        $user = User::factory()->create();
        PartnerMember::create(['user_id' => $user->id, 'partner_id' => $product->partner_id, 'role' => 'manager', 'is_active' => true]);

        return $user;
    }

    public function test_shipping_fee_is_snapshotted_once_per_order_and_retry_preserves_address_and_stock(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create())->withHeader('Idempotency-Key', fake()->uuid());
        $order = $this->postJson('/api/v1/account/umkm-orders', $this->payload($product))->assertCreated()->assertJsonPath('data.subtotal', 90000)->assertJsonPath('data.total', 110000)->assertJsonPath('data.shipping.fee', 20000)->assertJsonPath('data.product.fulfillment', 'delivery')->json('data');
        $product->update(['shipping_fee' => 50000, 'delivery_available' => false]);
        $this->postJson('/api/v1/account/umkm-orders', $this->payload($product))->assertOk()->assertJsonPath('data.order_id', $order['order_id'])->assertJsonPath('data.shipping.fee', 20000);
        $this->postJson('/api/v1/account/umkm-orders', [...$this->payload($product), 'shipping_address' => 'Alamat pengiriman pengujian yang berbeda'])->assertConflict();
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertDatabaseCount('umkm_orders', 1);
    }

    public function test_invalid_address_disabled_delivery_and_changed_fee_do_not_reserve_stock(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create())->withHeader('Idempotency-Key', fake()->uuid());
        foreach ([['shipping_address' => ''], ['postal_code' => '123'], ['expected_shipping_fee' => null]] as $changes) {
            $this->postJson('/api/v1/account/umkm-orders', [...$this->payload($product), ...$changes])->assertUnprocessable();
        }
        $this->postJson('/api/v1/account/umkm-orders', [...$this->payload($product), 'expected_shipping_fee' => 0])->assertConflict();
        $product->update(['delivery_available' => false]);
        $this->postJson('/api/v1/account/umkm-orders', $this->payload($product))->assertConflict();
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertDatabaseCount('umkm_orders', 0);
    }

    public function test_manager_records_and_corrects_tracking_and_buyer_sees_it_without_stock_changes(): void
    {
        $product = $this->product();
        $buyer = User::factory()->create();
        $order = $this->actingAs($buyer)->withHeader('Idempotency-Key', fake()->uuid())->postJson('/api/v1/account/umkm-orders', $this->payload($product))->assertCreated()->json('data');
        $url = '/api/v1/dashboard/umkm-orders/'.$order['order_id'];
        $this->actingAs($this->manager($product));
        $shipment = ['status' => 'shipped_sandbox', 'carrier' => 'Kurir Demo', 'tracking_number' => 'DEMO-TEST-001'];
        $this->patchJson($url, [...$shipment, 'revision' => $order['revision']])->assertConflict();
        $order = $this->patchJson($url, ['status' => 'processing_sandbox', 'revision' => $order['revision']])->assertOk()->json('data');
        $this->patchJson($url, ['status' => 'ready_sandbox', 'revision' => $order['revision']])->assertConflict();
        $this->patchJson($url, ['status' => 'shipped_sandbox', 'revision' => $order['revision']])->assertUnprocessable();
        $payload = [...$shipment, 'revision' => $order['revision']];
        $shipped = $this->patchJson($url, $payload)->assertOk()->assertJsonPath('data.shipping.tracking_number', 'DEMO-TEST-001')->json('data');
        $this->assertNotNull($shipped['shipping']['shipped_at']);
        $this->patchJson($url, $payload)->assertOk();
        $this->assertDatabaseCount('audit_logs', 2);
        $this->patchJson($url, [...$payload, 'tracking_number' => 'DEMO-TEST-002'])->assertConflict();
        $corrected = $this->patchJson($url, [...$payload, 'revision' => $shipped['revision'], 'tracking_number' => 'DEMO-TEST-002'])->assertOk()->json('data');
        $this->assertSame($shipped['shipping']['shipped_at'], $corrected['shipping']['shipped_at']);
        $this->patchJson($url, ['status' => 'cancelled', 'revision' => $corrected['revision']])->assertConflict();
        $this->actingAs($buyer)->getJson('/api/v1/account/umkm-orders?status=shipped_sandbox')->assertJsonPath('data.0.shipping.tracking_number', 'DEMO-TEST-002')->assertJsonPath('data.0.payment_status', 'unpaid');
        $this->postJson('/api/v1/account/umkm-orders/'.$order['order_id'].'/cancel')->assertConflict();
        $this->actingAs($this->manager($product))->patchJson($url, ['status' => 'completed_sandbox', 'revision' => $corrected['revision']])->assertOk();
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_shipping_addresses_are_private_to_buyer_and_approved_active_manager(): void
    {
        $product = $this->product();
        $buyer = User::factory()->create();
        $order = $this->actingAs($buyer)->withHeader('Idempotency-Key', fake()->uuid())->postJson('/api/v1/account/umkm-orders', $this->payload($product))->assertCreated()->json('data');
        $this->actingAs(User::factory()->create())->getJson('/api/v1/account/umkm-orders')->assertJsonCount(0, 'data');
        $other = $this->product();
        $this->actingAs($this->manager($other))->getJson('/api/v1/dashboard/umkm-orders')->assertJsonCount(0, 'data');
        $this->patchJson('/api/v1/dashboard/umkm-orders/'.$order['order_id'], ['status' => 'processing_sandbox', 'revision' => $order['revision']])->assertNotFound();
        $manager = $this->manager($product);
        $product->partner->update(['status' => 'pending']);
        $this->actingAs($manager)->getJson('/api/v1/dashboard/umkm-orders')->assertForbidden();
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_pickup_keeps_free_shipping_and_does_not_accept_shipping_data(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create())->withHeader('Idempotency-Key', fake()->uuid());
        $payload = collect($this->payload($product))->except(['shipping_address', 'postal_code', 'expected_shipping_fee'])->all();
        $payload['fulfillment'] = 'pickup';
        $this->postJson('/api/v1/account/umkm-orders', [...$payload, 'shipping_address' => 'Alamat tidak diperlukan untuk ambil di lokasi'])->assertUnprocessable();
        $order = $this->postJson('/api/v1/account/umkm-orders', $payload)->assertCreated()->assertJsonPath('data.total', 90000)->assertJsonPath('data.shipping.fee', 0)->assertJsonPath('data.shipping.address', null)->json('data');
        $this->actingAs($this->manager($product));
        $url = '/api/v1/dashboard/umkm-orders/'.$order['order_id'];
        $this->patchJson($url, ['status' => 'processing_sandbox'])->assertOk();
        $this->patchJson($url, ['status' => 'shipped_sandbox', 'carrier' => 'Demo Kurir', 'tracking_number' => 'DEMO-001'])->assertConflict();
    }

    public function test_seller_can_configure_shipping_fee_and_configuration_changes_invalidate_revision(): void
    {
        $product = $this->product();
        $this->actingAs($this->manager($product));
        $data = $this->getJson('/api/v1/dashboard/umkm-products')->assertOk()->json('data.0');
        $url = '/api/v1/dashboard/umkm-products/'.$product->slug;
        $this->patchJson($url, [...$data, 'shipping_fee' => -1])->assertUnprocessable();
        $this->patchJson($url, [...$data, 'shipping_fee' => 25000])->assertOk()->assertJsonPath('data.shipping_fee', 25000);
        $this->patchJson($url, [...$data, 'shipping_fee' => 10000])->assertConflict();
        $this->getJson('/api/v1/umkm-products/'.$product->slug)->assertOk()->assertJsonPath('data.delivery_available', true)->assertJsonPath('data.shipping_fee', 25000);
    }

    public function test_delivery_cancellation_before_shipping_restores_stock_once_and_preserves_fee_snapshot(): void
    {
        $product = $this->product();
        $buyer = User::factory()->create();
        $order = $this->actingAs($buyer)->withHeader('Idempotency-Key', fake()->uuid())->postJson('/api/v1/account/umkm-orders', $this->payload($product))->assertCreated()->json('data');
        $this->actingAs($this->manager($product));
        $url = '/api/v1/dashboard/umkm-orders/'.$order['order_id'];
        $order = $this->patchJson($url, ['status' => 'processing_sandbox', 'revision' => $order['revision']])->assertOk()->json('data');
        $payload = ['status' => 'cancelled', 'revision' => $order['revision']];
        $this->patchJson($url, $payload)->assertOk()->assertJsonPath('data.total', 110000)->assertJsonPath('data.shipping.fee', 20000);
        $this->patchJson($url, $payload)->assertOk();
        $this->assertSame(5, $product->fresh()->stock);
        $this->actingAs($buyer)->postJson('/api/v1/account/umkm-orders/'.$order['order_id'].'/cancel')->assertOk();
        $this->assertSame(5, $product->fresh()->stock);
    }
}
