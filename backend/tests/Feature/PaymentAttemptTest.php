<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Partner;
use App\Models\Region;
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
    }
}
