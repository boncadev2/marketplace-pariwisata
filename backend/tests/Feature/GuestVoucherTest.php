<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Region;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuestVoucherTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_guest_token_returns_voucher_without_caching(): void
    {
        $order = $this->order();

        $this->getJson('/api/v1/guest/orders/'.$order->public_id.'/vouchers', ['X-Guest-Access-Token' => str_repeat('g', 48)])->assertOk()->assertJsonPath('data.vouchers.0.token', str_repeat('v', 48))->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_wrong_guest_token_cannot_read_voucher(): void
    {
        $order = $this->order();

        $this->getJson('/api/v1/guest/orders/'.$order->public_id.'/vouchers', ['X-Guest-Access-Token' => str_repeat('x', 48)])->assertNotFound();
    }

    public function test_refunded_order_does_not_return_qr_token(): void
    {
        $order = $this->order();
        $order->update(['status' => 'refunded']);

        $this->getJson('/api/v1/guest/orders/'.$order->public_id.'/vouchers', ['X-Guest-Access-Token' => str_repeat('g', 48)])->assertOk()->assertJsonCount(0, 'data.vouchers');
    }

    private function order(): Order
    {
        $region = Region::create(['code' => 'GUEST-01', 'name' => 'Test', 'type' => 'regency']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Test', 'slug' => 'guest-partner', 'status' => 'approved']);
        $product = Product::create(['partner_id' => $partner->id, 'name' => 'Test', 'slug' => 'guest-product', 'type' => 'ticket']);
        $order = Order::create(['public_id' => fake()->uuid(), 'partner_id' => $partner->id, 'idempotency_key' => 'guest-voucher-key', 'guest_access_hash' => Hash::make(str_repeat('g', 48)), 'customer_name' => 'Test', 'customer_email' => 'guest@example.test', 'status' => 'paid', 'currency' => 'IDR', 'total' => 100, 'policy_snapshot' => []]);
        $item = $order->items()->create(['product_id' => $product->id, 'name' => 'Test', 'quantity' => 1, 'unit_price' => 100, 'total' => 100, 'snapshot' => []]);
        Voucher::create(['order_item_id' => $item->id, 'partner_id' => $partner->id, 'token_hash' => hash('sha256', str_repeat('v', 48)), 'token' => str_repeat('v', 48), 'service_date' => '2026-10-10', 'admissions' => 1]);

        return $order;
    }
}
