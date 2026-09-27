<?php

namespace Tests\Feature;

use App\Models\InventoryBucket;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_idempotency_key_creates_only_one_order(): void
    {
        $region = Region::create(['code' => 'CHECK-01', 'name' => 'Wilayah', 'type' => 'regency']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Mitra', 'slug' => 'mitra-checkout', 'status' => 'approved']);
        $product = Product::create(['partner_id' => $partner->id, 'name' => 'Tiket', 'slug' => 'tiket-checkout', 'type' => 'ticket', 'base_price' => 75_000, 'status' => 'published']);
        InventoryBucket::create(['product_id' => $product->id, 'service_date' => '2026-10-10', 'session_key' => 'default', 'capacity' => 2]);
        $payload = ['product_slug' => 'tiket-checkout', 'visit_date' => '2026-10-10', 'quantity' => 2, 'customer_name' => 'Pengunjung', 'customer_email' => 'pengunjung@example.test'];
        $headers = ['Idempotency-Key' => 'checkout-test-key-0001'];

        $this->postJson('/api/v1/checkout', $payload, $headers)->assertCreated()->assertJsonPath('data.total', 150_000);
        $this->postJson('/api/v1/checkout', $payload, $headers)->assertOk()->assertJsonPath('data.guest_access_token', null);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('inventory_holds', 1);
    }
}
