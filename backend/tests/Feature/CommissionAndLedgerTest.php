<?php

namespace Tests\Feature;

use App\Models\CommissionRule;
use App\Models\LedgerAccount;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Services\CommissionService;
use App\Services\LedgerService;
use App\Services\RevenueReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionAndLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_calculation()
    {
        $partner = Partner::factory()->create();
        $product = Product::factory()->create(['partner_id' => $partner->id]);

        CommissionRule::create([
            'partner_id' => null,
            'product_type' => null,
            'percentage_rate' => 10.5,
            'fixed_amount' => 5000,
            'effective_from' => now()->subDay(),
        ]);

        $service = app(CommissionService::class);
        // Total 100,000. 10.5% = 10,500. + 5000 = 15,500
        $result = $service->calculate($product, 100000);

        $this->assertEquals(15500, $result['commission_amount']);
        $this->assertNotNull($result['commission_rule_id']);
    }

    public function test_ledger_recording_for_payment_and_refund()
    {
        $partner = Partner::factory()->create();
        $order = Order::factory()->create(['partner_id' => $partner->id, 'total' => 100000]);
        $order->items()->create([
            'product_id' => Product::factory()->create()->id,
            'name' => 'Test Product',
            'quantity' => 1,
            'unit_price' => 100000,
            'total' => 100000,
            'commission_amount' => 15000,
            'snapshot' => [],
        ]);

        $ledgerService = app(LedgerService::class);

        // Record payment
        $ledgerService->recordPayment($order);
        $ledgerService->recordPayment($order);
        $this->assertDatabaseCount('journal_entries', 1);

        $gatewayAccount = LedgerAccount::where('code', 'asset_payment_gateway')->first();
        $partnerAccount = LedgerAccount::where('code', 'liability_partner_'.$partner->id)->first();
        $commissionAccount = LedgerAccount::where('code', 'revenue_commission')->first();

        $this->assertNotNull($gatewayAccount);
        $this->assertNotNull($partnerAccount);
        $this->assertNotNull($commissionAccount);

        $reportingService = app(RevenueReportingService::class);
        $metrics = $reportingService->getMetrics();

        $this->assertEquals(100000, $metrics['received_payments']);
        $this->assertEquals(15000, $metrics['platform_revenue']);
        $this->assertEquals(85000, $metrics['funds_ready_for_payout']);

        // Record refund
        $refund = RefundRequest::factory()->create([
            'order_id' => $order->id,
            'status' => 'succeeded',
            'refundable_amount' => 100000,
        ]);
        $ledgerService->recordRefund($order, $refund);
        $ledgerService->recordRefund($order, $refund);
        $this->assertDatabaseCount('journal_entries', 2);

        $metricsAfterRefund = $reportingService->getMetrics();
        $this->assertEquals(100000, $metricsAfterRefund['refunds']);
        $this->assertEquals(0, $metricsAfterRefund['platform_revenue']);
        $this->assertEquals(0, $metricsAfterRefund['funds_ready_for_payout']);
    }
}
