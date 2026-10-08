<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Partner;
use App\Models\Product;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_revenue_reports(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $partner = Partner::factory()->create();
        $product = Product::factory()->create(['partner_id' => $partner->id, 'type' => 'package']);

        $order = Order::factory()->create([
            'partner_id' => $partner->id,
            'status' => 'paid',
            'total' => 500000,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => 'Tour Package Test',
            'quantity' => 1,
            'unit_price' => 500000,
            'total' => 500000,
            'commission_amount' => 50000,
            'snapshot' => [],
        ]);

        app(LedgerService::class)->recordPayment($order);

        $response = $this->actingAs($admin)->getJson('/api/v1/revenue-reports');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'gross_booking_value',
                    'received_payments',
                    'refunds',
                    'platform_revenue',
                    'funds_ready_for_payout',
                    'monthly_trends',
                    'category_breakdown',
                    'recent_transactions',
                ],
            ]);

        $this->assertEquals(500000, $response->json('data.received_payments'));
        $this->assertEquals(50000, $response->json('data.platform_revenue'));
        $this->assertEquals(450000, $response->json('data.funds_ready_for_payout'));
        $this->assertNotEmpty($response->json('data.monthly_trends'));
        $this->assertNotEmpty($response->json('data.recent_transactions'));
    }

    public function test_non_admin_cannot_access_revenue_reports(): void
    {
        $user = User::factory()->create(['platform_role' => 'customer']);

        $response = $this->actingAs($user)->getJson('/api/v1/revenue-reports');
        $response->assertForbidden();

        $guestResponse = $this->getJson('/api/v1/revenue-reports');
        $guestResponse->assertForbidden();
    }

    public function test_admin_can_export_revenue_csv(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $partner = Partner::factory()->create(['name' => 'Mitra Danau Toba']);
        $order = Order::factory()->create([
            'partner_id' => $partner->id,
            'customer_name' => 'Budi Santoso',
            'status' => 'paid',
            'total' => 300000,
        ]);

        $order->items()->create([
            'product_id' => Product::factory()->create(['partner_id' => $partner->id])->id,
            'name' => 'Paket Danau',
            'quantity' => 1,
            'unit_price' => 300000,
            'total' => 300000,
            'commission_amount' => 30000,
            'snapshot' => [],
        ]);

        $response = $this->actingAs($admin)->get('/api/v1/revenue-reports/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        
        $content = $response->getContent();
        $this->assertStringContainsString('ID Pesanan', $content);
        $this->assertStringContainsString('Budi Santoso', $content);
        $this->assertStringContainsString('Mitra Danau Toba', $content);
        $this->assertStringContainsString('300000', $content);
    }
}
