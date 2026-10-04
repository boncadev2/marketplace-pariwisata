<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Partner;
use App\Models\Region;
use App\Payments\PaymentGateway;
use App\Services\PaymentAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_sandbox_attempt_keeps_order_amount_and_currency(): void
    {
        $region = Region::create(['code' => 'PAY-01', 'name' => 'Wilayah', 'type' => 'regency']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Mitra', 'slug' => 'mitra-pay', 'status' => 'approved']);
        $order = Order::create(['public_id' => fake()->uuid(), 'partner_id' => $partner->id, 'idempotency_key' => 'payment-attempt-key-0001', 'guest_access_hash' => 'hash', 'customer_name' => 'Pelanggan', 'customer_email' => 'p@example.test', 'currency' => 'IDR', 'total' => 125_000, 'policy_snapshot' => []]);

        $attempt = app(PaymentAttemptService::class)->create($order);

        $this->assertSame(125_000, $attempt->amount);
        $this->assertSame('IDR', $attempt->currency);
        $this->assertSame('pending', $attempt->status);
        $this->assertStringContainsString('/pembayaran/sandbox_', (string) $attempt->checkout_url);
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_timeout_uses_provider_lookup_before_returning_pending(): void
    {
        $order = $this->order('payment-attempt-key-0002');
        $gateway = \Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('createCheckout')->once()->with($order)->andThrow(new \RuntimeException('timeout'));
        $gateway->shouldReceive('findCheckout')->once()->with($order)->andReturn([
            'provider_reference' => 'recovered-provider-reference',
            'checkout_url' => 'https://sandbox.example.test/pay/recovered-provider-reference',
            'payload' => ['recovered' => true],
        ]);

        $attempt = (new PaymentAttemptService($gateway))->create($order);

        $this->assertSame('pending', $attempt->status);
        $this->assertSame('recovered-provider-reference', $attempt->provider_reference);
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_uncertain_creation_is_looked_up_without_creating_a_second_charge(): void
    {
        $order = $this->order('payment-attempt-key-0003');
        $gateway = \Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('createCheckout')->once()->with($order)->andThrow(new \RuntimeException('timeout'));
        $gateway->shouldReceive('findCheckout')->twice()->with($order)->andReturn(null);
        $service = new PaymentAttemptService($gateway);

        $first = $service->create($order);
        $second = $service->create($order);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('uncertain', $second->status);
        $this->assertNull($second->provider_reference);
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    private function order(string $idempotencyKey): Order
    {
        $region = Region::factory()->create();
        $partner = Partner::factory()->create(['region_id' => $region->id]);

        return Order::factory()->create([
            'partner_id' => $partner->id,
            'idempotency_key' => $idempotencyKey,
            'status' => 'pending_payment',
            'currency' => 'IDR',
            'total' => 125_000,
        ]);
    }
}
