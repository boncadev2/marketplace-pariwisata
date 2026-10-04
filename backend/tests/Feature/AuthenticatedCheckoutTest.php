<?php

namespace Tests\Feature;

use App\Models\InventoryBucket;
use App\Models\PilotControl;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthenticatedCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_checkout_without_coupon_belongs_to_customer_and_cannot_be_replayed_by_other_account(): void
    {
        config(['services.payment_gateway.driver' => 'sandbox']);
        PilotControl::factory()->create(['id' => 1, 'checkout_enabled' => true]);
        $product = Product::factory()->create(['status' => 'published', 'base_price' => 10000]);
        $date = now()->addDay()->toDateString();
        InventoryBucket::factory()->create(['product_id' => $product->id, 'service_date' => $date, 'capacity' => 10]);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $data = ['product_slug' => $product->slug, 'visit_date' => $date, 'quantity' => 1, 'customer_name' => $user->name, 'customer_email' => $user->email];
        $this->actingAs($user)->withHeader('Idempotency-Key', 'account-checkout-demo-0001');
        $response = $this->postJson('/api/v1/checkout', $data)->assertCreated();
        $id = $response->json('data.order_id');
        $this->getJson('/api/v1/account/orders')->assertOk()->assertJsonPath('data.0.order_id', $id);
        $this->getJson('/api/v1/account/orders/'.$id)->assertOk();
        $this->postJson('/api/v1/checkout', $data)->assertOk();
        $this->actingAs($other)->getJson('/api/v1/account/orders/'.$id)->assertNotFound();
        $this->postJson('/api/v1/checkout', $data)->assertConflict();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_login_to_midtrans_notification_updates_account_and_issues_voucher_once(): void
    {
        config(['services.payment_gateway.driver' => 'midtrans_sandbox', 'services.midtrans.server_key' => 'SB-Mid-server-test-only']);
        Http::preventStrayRequests();
        PilotControl::factory()->create(['id' => 1, 'checkout_enabled' => true]);
        $product = Product::factory()->create(['status' => 'published', 'base_price' => 10000]);
        $date = now()->addDay()->toDateString();
        InventoryBucket::factory()->create(['product_id' => $product->id, 'service_date' => $date, 'capacity' => 10]);
        $user = User::factory()->create(['email' => 'visitor@example.test', 'password' => 'test-password-2026']);
        $this->withHeaders(['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/login']);
        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'test-password-2026'])->assertOk();
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/demo'])]);
        $response = $this->postJson('/api/v1/checkout', ['product_slug' => $product->slug, 'visit_date' => $date, 'quantity' => 1, 'customer_name' => $user->name, 'customer_email' => $user->email], ['Idempotency-Key' => 'midtrans-account-demo-01'])->assertCreated()->assertJsonPath('data.payment_provider', 'midtrans_sandbox');
        $id = $response->json('data.order_id');
        $payload = ['transaction_id' => 'midtrans-test-demo-01', 'order_id' => $id, 'status_code' => '200', 'gross_amount' => '10000.00', 'currency' => 'IDR', 'transaction_status' => 'settlement', 'fraud_status' => 'accept'];
        $payload['signature_key'] = hash('sha512', $id.'20010000.00SB-Mid-server-test-only');
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response($payload)]);
        $this->postJson('/api/v1/webhooks/payments/midtrans', $payload)->assertOk();
        $this->postJson('/api/v1/webhooks/payments/midtrans', $payload)->assertOk();
        $this->getJson('/api/v1/account/orders/'.$id)->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.payment_status', 'succeeded');
        $this->getJson('/api/v1/account/orders/'.$id.'/vouchers')->assertOk()->assertJsonCount(1, 'data.vouchers');
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('vouchers', 1);
    }
}
