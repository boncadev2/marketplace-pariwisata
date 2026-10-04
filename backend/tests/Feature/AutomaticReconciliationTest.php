<?php

namespace Tests\Feature;

use App\Jobs\ReconcilePaymentAttempt;
use App\Models\InventoryBucket;
use App\Models\InventoryHold;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PaymentAttempt;
use App\Models\PayoutBatch;
use App\Models\Product;
use App\Models\ReconciliationAlert;
use App\Models\RefundRequest;
use App\Models\User;
use App\Payments\PaymentGateway;
use App\Services\InventoryReservationService;
use App\Services\LedgerService;
use App\Services\ReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomaticReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_webhook_is_recovered_without_duplicate_ledger_or_voucher(): void
    {
        $attempt = $this->pendingAttempt([
            'provider_status' => 'succeeded',
            'provider_amount' => 125000,
            'provider_currency' => 'IDR',
            'provider_fee' => 0,
            'settlement_reference' => 'settlement-001',
        ]);
        $job = new ReconcilePaymentAttempt($attempt->id);

        $job->handle(app(PaymentGateway::class), app(ReconciliationService::class));
        $job->handle(app(PaymentGateway::class), app(ReconciliationService::class));

        $this->assertSame('succeeded', $attempt->fresh()->status);
        $this->assertSame('paid', $attempt->order->fresh()->status);
        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseCount('reconciliation_entries', 1);
        $this->assertDatabaseHas('reconciliation_entries', [
            'internal_payment_attempt_id' => $attempt->id,
            'status' => 'matched',
            'settlement_reference' => 'settlement-001',
        ]);
    }

    public function test_temporary_unknown_provider_status_never_downgrades_paid_payment(): void
    {
        $this->freezeTime();
        $attempt = $this->pendingAttempt(['provider_status' => 'unknown']);
        $attempt->update(['status' => 'succeeded']);
        $attempt->order->update(['status' => 'paid']);
        app(LedgerService::class)->recordPayment($attempt->order);

        (new ReconcilePaymentAttempt($attempt->id))->handle(app(PaymentGateway::class), app(ReconciliationService::class));

        $attempt->refresh();
        $this->assertSame('succeeded', $attempt->status);
        $this->assertSame('paid', $attempt->order->fresh()->status);
        $this->assertSame(now()->addMinutes(5)->toIso8601String(), $attempt->next_reconciliation_at?->toIso8601String());
        $this->assertDatabaseHas('reconciliation_entries', [
            'internal_payment_attempt_id' => $attempt->id,
            'provider_status' => 'unknown',
            'internal_status' => 'succeeded',
            'status' => 'mismatched',
        ]);
        $this->assertDatabaseHas('reconciliation_alerts', [
            'payment_attempt_id' => $attempt->id,
            'type' => 'payment_discrepancy',
            'status' => 'open',
        ]);
    }

    public function test_dispatch_command_queues_only_due_pending_attempts(): void
    {
        $due = $this->pendingAttempt();
        $due->update(['created_at' => now()->subMinutes(10)]);
        $recent = PaymentAttempt::factory()->create(['created_at' => now()]);
        $uncertainSucceeded = PaymentAttempt::factory()->create([
            'status' => 'succeeded',
            'created_at' => now()->subHour(),
            'next_reconciliation_at' => now()->subMinute(),
        ]);
        Queue::fake([ReconcilePaymentAttempt::class]);

        $this->artisan('reconciliation:dispatch')->assertSuccessful();

        Queue::assertPushed(ReconcilePaymentAttempt::class, fn (ReconcilePaymentAttempt $job): bool => $job->paymentAttemptId === $due->id);
        Queue::assertPushed(ReconcilePaymentAttempt::class, fn (ReconcilePaymentAttempt $job): bool => $job->paymentAttemptId === $uncertainSucceeded->id);
        Queue::assertNotPushed(ReconcilePaymentAttempt::class, fn (ReconcilePaymentAttempt $job): bool => $job->paymentAttemptId === $recent->id);
    }

    public function test_daily_report_creates_all_required_alerts_idempotently(): void
    {
        $paidOrder = Order::factory()->create(['status' => 'paid']);
        $product = Product::factory()->create(['partner_id' => $paidOrder->partner_id]);
        $paidOrder->items()->create([
            'product_id' => $product->id,
            'name' => 'Tiket',
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
            'snapshot' => [],
        ]);
        RefundRequest::factory()->create(['created_at' => now()->subDays(2)]);
        PayoutBatch::create([
            'batch_number' => 'PO-UNCERTAIN-1',
            'provider' => 'bank_transfer',
            'status' => 'processing',
            'total_amount' => 100000,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        InventoryHold::factory()->create(['state' => 'active', 'expires_at' => now()->subHour()]);
        $service = app(ReconciliationService::class);

        $first = $service->generateDailyReport(now('Asia/Jakarta')->subDay()->toDateString());
        $second = $service->generateDailyReport(now('Asia/Jakarta')->subDay()->toDateString());

        $this->assertSame($first->id, $second->id);
        $this->assertSame('completed', $second->status);
        $this->assertDatabaseCount('reconciliation_alerts', 4);
        $this->assertDatabaseHas('reconciliation_alerts', ['type' => 'paid_without_voucher']);
        $this->assertDatabaseHas('reconciliation_alerts', ['type' => 'stale_refund']);
        $this->assertDatabaseHas('reconciliation_alerts', ['type' => 'uncertain_payout']);
        $this->assertDatabaseHas('reconciliation_alerts', ['type' => 'unreleased_hold']);
        $this->assertSame(1, $second->summary['open_alerts']['paid_without_voucher']);
    }

    public function test_admin_retry_and_resolution_are_audited(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $attempt = PaymentAttempt::factory()->create();
        $alert = ReconciliationAlert::factory()->create(['payment_attempt_id' => $attempt->id]);
        Queue::fake([ReconcilePaymentAttempt::class]);
        $headers = $this->sensitiveHeaders($admin);

        $this->actingAs($admin)->withHeaders($headers)
            ->postJson("/api/v1/reconciliation/attempts/{$attempt->id}/retry")
            ->assertAccepted()
            ->assertJsonPath('data.queued', true);
        $this->actingAs($admin)->withHeaders($headers)
            ->patchJson("/api/v1/reconciliation/alerts/{$alert->id}/resolve", ['notes' => 'Sudah diperiksa dengan provider.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        Queue::assertPushed(ReconcilePaymentAttempt::class, fn (ReconcilePaymentAttempt $job): bool => $job->paymentAttemptId === $attempt->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reconciliation.retry_requested', 'auditable_id' => $attempt->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reconciliation.alert_resolved', 'auditable_id' => $alert->id]);
        $this->assertDatabaseHas('reconciliation_alerts', [
            'id' => $alert->id,
            'status' => 'resolved',
            'resolved_by' => $admin->id,
        ]);
    }

    /** @param array<string, mixed> $providerPayload */
    private function pendingAttempt(array $providerPayload = []): PaymentAttempt
    {
        $partner = Partner::factory()->create();
        $order = Order::factory()->create([
            'partner_id' => $partner->id,
            'status' => 'pending_payment',
            'total' => 125000,
        ]);
        $product = Product::factory()->create([
            'partner_id' => $partner->id,
            'base_price' => 125000,
        ]);
        $bucket = InventoryBucket::factory()->create([
            'product_id' => $product->id,
            'capacity' => 1,
        ]);
        $hold = app(InventoryReservationService::class)->reserve($bucket, 1, CarbonImmutable::now()->addMinutes(15));
        $order->items()->create([
            'product_id' => $product->id,
            'name' => 'Tiket',
            'quantity' => 1,
            'unit_price' => 125000,
            'total' => 125000,
            'snapshot' => ['inventory_hold_id' => $hold->id],
        ]);

        return PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'provider_reference' => 'sandbox-reconciliation-'.$order->id,
            'amount' => 125000,
            'provider_payload' => $providerPayload,
        ]);
    }
}
