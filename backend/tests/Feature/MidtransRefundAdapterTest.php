<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\RefundRequest;
use App\Services\Refund\MidtransRefundAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransRefundAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_submission_is_not_success_and_refresh_never_sends_a_second_refund(): void
    {
        config(['services.midtrans.server_key' => 'SB-Mid-server-test', 'services.midtrans.refunds_enabled' => true]);
        Http::preventStrayRequests();
        $order = Order::factory()->create(['status' => 'paid', 'total' => 100000]);
        PaymentAttempt::factory()->create(['order_id' => $order->id, 'provider' => 'midtrans_sandbox', 'provider_reference' => $order->public_id, 'status' => 'succeeded', 'currency' => 'IDR', 'amount' => 100000]);
        $refund = RefundRequest::factory()->create(['order_id' => $order->id, 'status' => 'processing', 'refundable_amount' => 50000, 'reason' => 'Batal kunjungan']);
        $statusUrl = 'https://api.sandbox.midtrans.com/v2/'.$order->public_id.'/status';
        $refundUrl = 'https://api.sandbox.midtrans.com/v2/'.$order->public_id.'/refund';
        $status = ['order_id' => $order->public_id, 'transaction_status' => 'settlement', 'gross_amount' => '100000.00'];
        Http::fake([$statusUrl => Http::sequence()->push($status)->push($status)->push([...$status, 'transaction_status' => 'partial_refund', 'refunds' => [['refund_key' => 'wisata-refund-'.$refund->id, 'refund_amount' => '50000.00', 'refund_method' => 'online', 'bank_confirmed_at' => '2026-10-03 10:00:00', 'refund_chargeback_id' => 123]]]), $refundUrl => Http::response(['status_code' => '200'])]);
        $adapter = app(MidtransRefundAdapter::class);
        $first = $adapter->process($refund);
        $this->assertFalse($first['confirmed']);
        $this->assertTrue($first['pending']);
        $again = $adapter->process($refund->fresh());
        $this->assertTrue($again['pending']);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->url() === $refundUrl && $request['refund_key'] === 'wisata-refund-'.$refund->id && $request['amount'] === 50000);
        $verified = $adapter->process($refund->fresh());
        $this->assertTrue($verified['confirmed']);
        $this->assertSame('123', $verified['provider_reference']);
    }

    public function test_unconfirmed_bank_refund_and_mismatched_amount_never_report_success(): void
    {
        config(['services.midtrans.server_key' => 'SB-Mid-server-test', 'services.midtrans.refunds_enabled' => true]);
        Http::preventStrayRequests();
        $order = Order::factory()->create(['status' => 'paid', 'total' => 100000]);
        PaymentAttempt::factory()->create(['order_id' => $order->id, 'provider' => 'midtrans_sandbox', 'provider_reference' => $order->public_id, 'status' => 'succeeded', 'currency' => 'IDR', 'amount' => 100000]);
        $refund = RefundRequest::factory()->create(['order_id' => $order->id, 'status' => 'processing', 'refundable_amount' => 50000]);
        Http::fake(['https://api.sandbox.midtrans.com/v2/'.$order->public_id.'/status' => Http::response(['order_id' => $order->public_id, 'transaction_status' => 'partial_refund', 'gross_amount' => '100000.00', 'refunds' => [['refund_key' => 'wisata-refund-'.$refund->id, 'refund_amount' => '40000.00', 'refund_method' => 'online', 'bank_confirmed_at' => '2026-10-03 10:00:00', 'refund_chargeback_id' => 123]]])]);
        $this->assertFalse(app(MidtransRefundAdapter::class)->process($refund)['confirmed']);
        Http::assertSentCount(1);
    }

    public function test_fractional_provider_total_is_rejected_without_submitting_refund(): void
    {
        config(['services.midtrans.server_key' => 'SB-Mid-server-test', 'services.midtrans.refunds_enabled' => true]);
        Http::preventStrayRequests();
        $order = Order::factory()->create(['status' => 'paid', 'total' => 100000]);
        PaymentAttempt::factory()->create(['order_id' => $order->id, 'provider' => 'midtrans_sandbox', 'provider_reference' => $order->public_id, 'status' => 'succeeded', 'currency' => 'IDR', 'amount' => 100000]);
        $refund = RefundRequest::factory()->create(['order_id' => $order->id, 'status' => 'processing', 'refundable_amount' => 50000]);
        Http::fake(['https://api.sandbox.midtrans.com/v2/'.$order->public_id.'/status' => Http::response(['order_id' => $order->public_id, 'transaction_status' => 'settlement', 'gross_amount' => '100000.50'])]);
        $this->assertFalse(app(MidtransRefundAdapter::class)->process($refund)['confirmed']);
        Http::assertSentCount(1);
        $this->assertEmpty($refund->fresh()->provider_payload);
    }
}
