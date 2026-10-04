<?php

namespace Tests\Feature;

use App\Models\NotificationDelivery;
use App\Models\OperationalDispute;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_without_authentication_and_403_for_customer(): void
    {
        $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/dashboard/summary')
            ->assertForbidden();
    }

    public function test_admin_summary_matches_ledger_fixture(): void
    {
        $this->travelTo('2026-09-30 12:00:00');
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $partner = Partner::factory()->create(['name' => 'Mitra Satu']);
        $order = $this->createPaidOrder($partner, 100000, 15000, 'ORDER-ADMIN-001');
        app(LedgerService::class)->recordPayment($order);

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/summary?from=2026-09-01&to=2026-09-30&timezone=Asia%2FJakarta');

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('sales.gross', 100000)
            ->assertJsonPath('sales.net', 100000)
            ->assertJsonPath('sales.platform_revenue', 15000)
            ->assertJsonPath('sales.partner_liability', 85000)
            ->assertJsonPath('orders.total', 1)
            ->assertJsonPath('orders.paid', 1)
            ->assertJsonPath('pilot_monitoring.order_to_payment_percent', 100)
            ->assertJsonPath('scope.role', 'super_admin');
    }

    public function test_admin_summary_reports_daily_pilot_failures_and_complaints(): void
    {
        $this->travelTo('2026-09-30 12:00:00');
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $customer = User::factory()->create();
        $partner = Partner::factory()->create();
        $this->createPaidOrder($partner, 10000, 1000, 'ORDER-PILOT-PAID');
        $exceptionOrder = Order::factory()->create([
            'partner_id' => $partner->id,
            'status' => 'payment_exception',
            'public_id' => 'ORDER-PILOT-EXCEPTION',
        ]);
        PaymentAttempt::factory()->create(['order_id' => $exceptionOrder->id, 'status' => 'failed']);
        SupportTicket::create([
            'user_id' => $customer->id,
            'order_id' => $exceptionOrder->id,
            'category' => 'payment',
            'status' => 'open',
        ]);
        OperationalDispute::create([
            'order_id' => $exceptionOrder->id,
            'reporter_id' => $customer->id,
            'reason' => 'payment_exception',
            'description' => 'Pembayaran perlu diperiksa.',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/dashboard/summary?from=2026-09-30&to=2026-09-30&timezone=Asia%2FJakarta')
            ->assertOk()
            ->assertJsonPath('pilot_monitoring.order_to_payment_percent', 50)
            ->assertJsonPath('pilot_monitoring.failed_payments', 1)
            ->assertJsonPath('pilot_monitoring.transaction_exceptions', 1)
            ->assertJsonPath('pilot_monitoring.open_support_tickets', 1)
            ->assertJsonPath('pilot_monitoring.open_disputes', 1)
            ->assertJsonPath('pilot_monitoring.financial_discrepancies', 0);
    }

    public function test_partner_only_sees_own_transactions_and_cross_tenant_detail_returns_404(): void
    {
        $owner = User::factory()->create();
        $ownPartner = Partner::factory()->create(['name' => 'Mitra Sendiri']);
        $otherPartner = Partner::factory()->create(['name' => 'Mitra Lain']);
        PartnerMember::create(['user_id' => $owner->id, 'partner_id' => $ownPartner->id, 'role' => 'owner', 'is_active' => true]);
        $ownOrder = $this->createPaidOrder($ownPartner, 75000, 7500, 'ORDER-OWN-001');
        $otherOrder = $this->createPaidOrder($otherPartner, 90000, 9000, 'ORDER-OTHER-001');

        $response = $this->actingAs($owner)->getJson('/api/v1/dashboard/transactions?timezone=Asia%2FJakarta');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_id', $ownOrder->public_id)
            ->assertJsonMissing(['order_id' => $otherOrder->public_id]);
        $this->getJson("/api/v1/dashboard/transactions/{$otherOrder->id}")->assertNotFound();
        $this->getJson("/api/v1/dashboard/summary?partner_id={$otherPartner->id}")->assertNotFound();
    }

    public function test_export_follows_filter_escapes_formula_and_records_audit_without_personal_data(): void
    {
        $owner = User::factory()->create();
        $partner = Partner::factory()->create(['name' => '=HYPERLINK("https://invalid.test")']);
        PartnerMember::create(['user_id' => $owner->id, 'partner_id' => $partner->id, 'role' => 'owner', 'is_active' => true]);
        $included = $this->createPaidOrder($partner, 50000, 5000, 'ORDER-EXPORT-PAID');
        $excluded = Order::factory()->create([
            'partner_id' => $partner->id,
            'status' => 'cancelled',
            'customer_name' => 'Rahasia',
            'customer_email' => 'private@example.test',
        ]);

        $response = $this->actingAs($owner)->get('/api/v1/dashboard/export?status=paid&timezone=Asia%2FJakarta');
        $content = $response->streamedContent();

        $response->assertOk()->assertDownload()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString($included->public_id, $content);
        $this->assertStringNotContainsString($excluded->public_id, $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString('private@example.test', $content);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'partner_id' => $partner->id,
            'action' => 'dashboard.transactions_exported',
        ]);
    }

    public function test_staff_cannot_export_and_exception_queue_contains_only_partner_sources(): void
    {
        $this->travelTo('2026-09-30 12:00:00');
        $staff = User::factory()->create();
        $ownPartner = Partner::factory()->create();
        $otherPartner = Partner::factory()->create();
        PartnerMember::create(['user_id' => $staff->id, 'partner_id' => $ownPartner->id, 'role' => 'staff', 'is_active' => true]);
        $ownOrder = Order::factory()->create(['partner_id' => $ownPartner->id, 'status' => 'pending_payment']);
        $otherOrder = Order::factory()->create(['partner_id' => $otherPartner->id, 'status' => 'pending_payment']);
        PaymentAttempt::factory()->create(['order_id' => $ownOrder->id, 'status' => 'pending', 'created_at' => now()->subHour()]);
        PaymentAttempt::factory()->create(['order_id' => $otherOrder->id, 'status' => 'pending', 'created_at' => now()->subHour()]);
        NotificationDelivery::factory()->create(['order_id' => $ownOrder->id, 'status' => 'failed']);

        $response = $this->actingAs($staff)->getJson('/api/v1/dashboard/exceptions');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['type' => 'late_payment'])
            ->assertJsonFragment(['type' => 'failed_notification'])
            ->assertJsonMissing(['description' => "Pembayaran pesanan {$otherOrder->public_id} masih pending lebih dari 30 menit."]);
        $this->get('/api/v1/dashboard/export')->assertForbidden();
    }

    private function createPaidOrder(Partner $partner, int $total, int $commission, string $publicId): Order
    {
        $product = Product::factory()->create(['partner_id' => $partner->id]);
        $order = Order::factory()->create([
            'partner_id' => $partner->id,
            'public_id' => $publicId,
            'status' => 'paid',
            'total' => $total,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'unit_price' => $total,
            'total' => $total,
            'commission_amount' => $commission,
            'snapshot' => [],
        ]);
        PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'status' => 'succeeded',
            'amount' => $total,
        ]);

        return $order;
    }
}
