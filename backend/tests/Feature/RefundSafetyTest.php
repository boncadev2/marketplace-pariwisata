<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Refund\RefundAdapterInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_request_refund_for_another_customers_order(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id, 'status' => 'paid']);

        $this->actingAs($attacker)->postJson("/api/v1/orders/{$order->id}/refunds", [
            'reason' => 'Tidak sah',
        ])->assertNotFound();
    }

    public function test_missing_policy_snapshot_fails_closed(): void
    {
        $owner = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id, 'status' => 'paid', 'policy_snapshot' => []]);
        PaymentAttempt::factory()->create(['order_id' => $order->id, 'status' => 'succeeded', 'amount' => 100]);

        $this->actingAs($owner)->postJson("/api/v1/orders/{$order->id}/refunds", [
            'reason' => 'Kebijakan kosong',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('refund_requests', 0);
    }

    public function test_refund_is_capped_at_confirmed_payment_and_failed_provider_does_not_refund_order(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $order = Order::factory()->create([
            'user_id' => $owner->id,
            'status' => 'paid',
            'total' => 100000,
            'policy_snapshot' => [
                'is_refundable' => true,
                'refund_percentage' => 100,
                'refund_cutoff_hours' => 24,
                'visit_date' => now()->addDays(2)->toIso8601String(),
            ],
        ]);
        PaymentAttempt::factory()->create(['order_id' => $order->id, 'status' => 'succeeded', 'amount' => 60000]);

        $refundId = $this->actingAs($owner)->postJson("/api/v1/orders/{$order->id}/refunds", [
            'reason' => 'Pembayaran parsial',
        ])->assertCreated()->assertJsonPath('refundable_amount', 60000)->json('id');

        $adapter = \Mockery::mock(RefundAdapterInterface::class);
        $adapter->shouldReceive('process')->once()->andReturn([
            'confirmed' => false,
            'failure_reason' => 'Provider menolak',
        ]);
        $this->app->instance(RefundAdapterInterface::class, $adapter);

        $this->actingAs($admin)->withHeaders($this->sensitiveHeaders($admin))
            ->postJson("/api/v1/refunds/{$refundId}/approve")->assertOk();

        $this->assertDatabaseHas('refund_requests', ['id' => $refundId, 'status' => 'failed']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
    }

    public function test_repeated_refund_request_and_approval_do_not_call_provider_twice(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $order = Order::factory()->create([
            'user_id' => $owner->id,
            'status' => 'paid',
            'total' => 100000,
            'policy_snapshot' => [
                'is_refundable' => true,
                'refund_percentage' => 100,
                'refund_cutoff_hours' => 24,
                'visit_date' => now()->addDays(2)->toIso8601String(),
            ],
        ]);
        PaymentAttempt::factory()->create(['order_id' => $order->id, 'status' => 'succeeded', 'amount' => 60000]);
        $payload = ['reason' => 'Pengajuan duplikat'];
        $refundId = $this->actingAs($owner)->postJson("/api/v1/orders/{$order->id}/refunds", $payload)
            ->assertCreated()
            ->assertJsonPath('refundable_amount', 60000)
            ->json('id');
        $this->actingAs($owner)->postJson("/api/v1/orders/{$order->id}/refunds", $payload)
            ->assertUnprocessable();

        $adapter = \Mockery::mock(RefundAdapterInterface::class);
        $adapter->shouldReceive('process')->once()->andReturn([
            'confirmed' => true,
            'provider_reference' => 'single-provider-refund',
            'payload' => ['verified' => true],
        ]);
        $this->app->instance(RefundAdapterInterface::class, $adapter);
        $headers = $this->sensitiveHeaders($admin);
        $this->actingAs($admin)->withHeaders($headers)
            ->postJson("/api/v1/refunds/{$refundId}/approve")
            ->assertOk();

        $this->actingAs($admin)->withHeaders($headers)
            ->postJson("/api/v1/refunds/{$refundId}/approve")
            ->assertUnprocessable();
        $this->assertDatabaseCount('refund_requests', 1);
        $this->assertDatabaseHas('refund_requests', [
            'id' => $refundId,
            'status' => 'succeeded',
            'refundable_amount' => 60000,
            'provider_reference' => 'single-provider-refund',
        ]);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_pending_refund_blocks_voucher_use_and_rejection_releases_it(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $order = Order::factory()->create(['status' => 'paid']);
        $product = Product::factory()->create(['partner_id' => $order->partner_id]);
        $item = $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'quantity' => 1, 'unit_price' => 100, 'total' => 100, 'snapshot' => []]);
        $token = str_repeat('v', 48);
        $voucher = Voucher::create(['order_item_id' => $item->id, 'partner_id' => $order->partner_id, 'token_hash' => hash('sha256', $token), 'token' => $token, 'service_date' => now()->toDateString(), 'admissions' => 1]);
        $refund = RefundRequest::factory()->create(['order_id' => $order->id, 'status' => 'processing']);
        $this->actingAs($admin)->postJson('/api/v1/staff/vouchers/redeem', ['token' => $token, 'override_reason' => 'Pemeriksaan operasional admin'])->assertConflict();
        $this->assertSame('active', $voucher->fresh()->status);
        $this->assertDatabaseCount('voucher_check_ins', 0);
        $refund->update(['status' => 'rejected']);
        $this->postJson('/api/v1/staff/vouchers/redeem', ['token' => $token, 'override_reason' => 'Pemeriksaan operasional admin'])->assertOk();
        $this->assertSame('redeemed', $voucher->fresh()->status);
    }

    public function test_refund_cannot_start_while_payout_is_requested(): void
    {
        $owner = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id, 'status' => 'paid', 'payout_status' => 'requested']);
        $this->actingAs($owner)->postJson('/api/v1/orders/'.$order->id.'/refunds', ['reason' => 'Payout sedang berjalan'])->assertUnprocessable();
        $this->assertDatabaseCount('refund_requests', 0);
    }
}
