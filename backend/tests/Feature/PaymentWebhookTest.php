<?php

namespace Tests\Feature;

use App\Models\InventoryBucket;
use App\Models\InventoryHold;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\Region;
use App\Services\InventoryReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_secret_rejects_webhook_with_401(): void
    {
        config(['services.sandbox_payment.webhook_secret' => null]);
        $this->postJson('/api/v1/webhooks/payments/sandbox', [])->assertUnauthorized();
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_duplicate_success_and_late_failure_keep_payment_succeeded(): void
    {
        $attempt = $this->attempt();
        $payload = ['event_key' => 'success-1', 'provider_reference' => $attempt->provider_reference, 'status' => 'succeeded', 'amount' => 125000, 'currency' => 'IDR'];
        $headers = ['X-Sandbox-Signature' => 'test-secret'];

        $this->postJson('/api/v1/webhooks/payments/sandbox', $payload, $headers)->assertOk();
        $this->postJson('/api/v1/webhooks/payments/sandbox', $payload, $headers)->assertOk();
        $this->postJson('/api/v1/webhooks/payments/sandbox', array_replace($payload, ['event_key' => 'failure-2', 'status' => 'failed']), $headers)->assertOk();

        $this->assertDatabaseCount('payment_webhook_events', 2);
        $this->assertSame('succeeded', $attempt->fresh()->status);
        $this->assertSame('paid', $attempt->order->fresh()->status);
    }

    public function test_wrong_amount_is_rejected_without_changing_order(): void
    {
        $attempt = $this->attempt();

        $this->postJson('/api/v1/webhooks/payments/sandbox', ['event_key' => 'wrong-1', 'provider_reference' => $attempt->provider_reference, 'status' => 'succeeded', 'amount' => 1, 'currency' => 'IDR'], ['X-Sandbox-Signature' => 'test-secret'])->assertConflict();

        $this->assertDatabaseCount('payment_webhook_events', 0);
        $this->assertSame('pending_payment', $attempt->order->fresh()->status);
    }

    private function attempt(): PaymentAttempt
    {
        config(['services.sandbox_payment.webhook_secret' => 'test-secret']);
        $region = Region::create(['code' => 'WEB-01', 'name' => 'Wilayah', 'type' => 'regency']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Mitra', 'slug' => 'mitra-webhook', 'status' => 'approved']);
        $order = Order::create(['public_id' => '11111111-1111-4111-8111-111111111111', 'partner_id' => $partner->id, 'idempotency_key' => 'webhook-test-key', 'guest_access_hash' => 'hash', 'customer_name' => 'Pelanggan', 'customer_email' => 'p@example.test', 'status' => 'pending_payment', 'currency' => 'IDR', 'total' => 125000, 'policy_snapshot' => []]);
        $product = Product::create(['partner_id' => $partner->id, 'name' => 'Tiket', 'slug' => 'webhook-ticket', 'type' => 'ticket', 'base_price' => 125000, 'status' => 'published']);
        $bucket = InventoryBucket::create(['product_id' => $product->id, 'service_date' => '2026-10-10', 'capacity' => 1]);
        $hold = app(InventoryReservationService::class)->reserve($bucket, 1, CarbonImmutable::now()->addMinutes(15));
        $order->items()->create(['product_id' => $product->id, 'name' => 'Tiket', 'quantity' => 1, 'unit_price' => 125000, 'total' => 125000, 'snapshot' => ['inventory_hold_id' => $hold->id]]);

        return PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'sandbox', 'provider_reference' => 'sandbox-test', 'status' => 'pending', 'currency' => 'IDR', 'amount' => 125000]);
    }

    public function test_late_payment_reallocates_available_stock(): void
    {
        $attempt = $this->attempt();
        $hold = InventoryHold::firstOrFail();
        $hold->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/webhooks/payments/sandbox', ['event_key' => 'late-1', 'provider_reference' => 'sandbox-test', 'status' => 'succeeded', 'amount' => 125000, 'currency' => 'IDR'], ['X-Sandbox-Signature' => 'test-secret'])->assertOk();

        $this->assertSame('paid', $attempt->order->fresh()->status);
        $this->assertSame('expired', $hold->fresh()->state);
        $this->assertSame(1, $hold->bucket->fresh()->confirmed);
        $this->assertSame(0, $hold->bucket->fresh()->held);
    }

    public function test_late_payment_with_stock_taken_enters_exception(): void
    {
        $attempt = $this->attempt();
        $hold = InventoryHold::firstOrFail();
        app(InventoryReservationService::class)->release($hold, 'expired');
        app(InventoryReservationService::class)->reserve($hold->bucket, 1, CarbonImmutable::now()->addMinutes(15));

        $this->postJson('/api/v1/webhooks/payments/sandbox', ['event_key' => 'late-2', 'provider_reference' => 'sandbox-test', 'status' => 'succeeded', 'amount' => 125000, 'currency' => 'IDR'], ['X-Sandbox-Signature' => 'test-secret'])->assertOk();

        $this->assertSame('payment_exception', $attempt->order->fresh()->status);
        $this->assertSame('succeeded', $attempt->fresh()->status);
        $this->assertSame(0, $hold->bucket->fresh()->confirmed);
        $this->assertSame(1, $hold->bucket->fresh()->held);
    }
}
