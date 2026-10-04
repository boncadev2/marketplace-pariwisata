<?php

namespace Tests\Feature;

use App\Models\UmkmOrder;
use App\Models\UmkmProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UmkmShippingProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_quote_sets_server_shipping_fee_and_rejects_forgery_and_expired_quote(): void
    {
        config(['services.shipping.driver' => 'biteship', 'services.shipping.enabled' => true, 'services.shipping.api_key' => 'biteship_test.fake']);
        Http::preventStrayRequests();
        Http::fake(['https://api.biteship.com/v1/rates/couriers' => Http::response(['success' => true, 'pricing' => [['courier_code' => 'jne', 'courier_service_code' => 'reg', 'price' => 18000, 'duration' => '2 - 3 days']]])]);
        $this->freezeTime();
        $product = UmkmProduct::factory()->create(['status' => 'published', 'stock' => 5, 'price' => 50000, 'delivery_available' => true, 'origin_postal_code' => '12345', 'weight_grams' => 500]);
        $this->actingAs(User::factory()->create());
        $quote = $this->postJson('/api/v1/account/umkm-shipping/quotes', ['product_slug' => $product->slug, 'quantity' => 2, 'postal_code' => '54321'])->assertOk()->assertJsonPath('data.0.fee', 18000)->json('data.0');
        $payload = ['product_slug' => $product->slug, 'quantity' => 2, 'expected_price' => 50000, 'customer_name' => 'Pembeli Demo', 'customer_phone' => '081234567890', 'fulfillment' => 'delivery', 'shipping_address' => 'Alamat sintetis untuk pengujian pengiriman', 'postal_code' => '54321', 'shipping_quote_id' => $quote['public_id'], 'expected_shipping_fee' => 0];
        $this->withHeader('Idempotency-Key', fake()->uuid())->postJson('/api/v1/account/umkm-orders', $payload)->assertConflict();
        $this->postJson('/api/v1/account/umkm-orders', [...$payload, 'expected_shipping_fee' => 18000])->assertCreated()->assertJsonPath('data.total', 118000)->assertJsonPath('data.shipping.provider', 'biteship')->assertJsonPath('data.shipping.service', 'reg');
        $this->travel(11)->minutes();
        $this->withHeader('Idempotency-Key', fake()->uuid())->postJson('/api/v1/account/umkm-orders', [...$payload, 'expected_shipping_fee' => 18000])->assertConflict();
        $this->assertSame(3, $product->fresh()->stock);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.biteship.com/v1/rates/couriers' && $request['items'][0]['weight'] === 500 && $request['items'][0]['quantity'] === 2);
    }

    public function test_tracking_is_owned_cached_and_does_not_expose_provider_contact_details(): void
    {
        config(['services.shipping.driver' => 'biteship', 'services.shipping.enabled' => true, 'services.shipping.api_key' => 'biteship_test.fake']);
        Http::preventStrayRequests();
        Http::fake(['https://api.biteship.com/v1/trackings/tracking-demo-id' => Http::response(['success' => true, 'id' => 'tracking-demo-id', 'waybill_id' => 'RESI123', 'courier' => ['company' => 'jne', 'driver_phone' => 'private-phone'], 'destination' => ['address' => 'Private provider address'], 'status' => 'delivered', 'history' => [['status' => 'delivered', 'note' => 'Paket diterima', 'updated_at' => '2026-10-03T10:00:00+07:00']]])]);
        $buyer = User::factory()->create();
        $product = UmkmProduct::factory()->create();
        $order = UmkmOrder::create(['public_id' => fake()->uuid(), 'user_id' => $buyer->id, 'umkm_product_id' => $product->id, 'idempotency_key' => fake()->uuid(), 'payload_hash' => str_repeat('a', 64), 'quantity' => 1, 'total' => 50000, 'snapshot' => [], 'customer_name' => 'Demo', 'customer_phone' => '081234567890', 'fulfillment' => 'delivery', 'status' => 'shipped_sandbox', 'carrier' => 'jne', 'tracking_number' => 'RESI123', 'provider_tracking_id' => 'tracking-demo-id']);
        $url = '/api/v1/account/umkm-orders/'.$order->public_id.'/tracking';
        $this->actingAs(User::factory()->create())->getJson($url)->assertNotFound();
        $response = $this->actingAs($buyer)->getJson($url)->assertOk()->assertJsonPath('data.status', 'delivered');
        $this->assertStringNotContainsString('private-phone', $response->getContent());
        $this->getJson($url)->assertOk();
        Http::assertSentCount(1);
        $this->assertSame('shipped_sandbox', $order->fresh()->status);
    }
}
