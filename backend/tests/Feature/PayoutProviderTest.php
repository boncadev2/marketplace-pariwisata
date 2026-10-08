<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerBankAccount;
use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use App\Models\Product;
use App\Models\User;
use App\Payouts\SandboxPayoutGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        SandboxPayoutGateway::resetSimulation();
        parent::tearDown();
    }

    private function createBatchWithOrders(string $status = 'approved'): array
    {
        $maker = User::factory()->create(['platform_role' => 'super_admin']);
        $checker = User::factory()->create(['platform_role' => 'super_admin']);
        $partner = Partner::factory()->create();
        $product = Product::factory()->create(['partner_id' => $partner->id]);
        PartnerBankAccount::factory()->create([
            'partner_id' => $partner->id,
            'is_verified' => true,
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'partner_id' => $partner->id,
            'payout_status' => 'requested',
            'status' => 'paid',
            'total' => 150000,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => 'Tiket Uji Coba Payout',
            'quantity' => 1,
            'unit_price' => 150000,
            'total' => 150000,
            'snapshot' => [],
            'commission_amount' => 15000,
        ]);

        $batch = PayoutBatch::factory()->create([
            'maker_id' => $maker->id,
            'checker_id' => $checker->id,
            'provider' => 'bank_transfer',
            'status' => $status,
            'total_amount' => 135000,
        ]);

        $item = PayoutItem::create([
            'payout_batch_id' => $batch->id,
            'partner_id' => $partner->id,
            'order_id' => $order->id,
            'amount' => 135000,
            'status' => $status,
        ]);

        return [$batch, $order, $item, $checker, $partner];
    }

    public function test_unconfigured_driver_fails_closed_with_http_503(): void
    {
        config(['services.payout.driver' => 'unconfigured']);

        [$batch, , , $checker] = $this->createBatchWithOrders('approved');

        $response = $this->actingAs($checker)
            ->withHeaders($this->sensitiveHeaders($checker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/process");

        $response->assertStatus(503)
            ->assertJsonPath('code', 'PAYOUT_PROVIDER_NOT_CONFIGURED');

        $this->assertDatabaseHas('payout_batches', [
            'id' => $batch->id,
            'status' => 'approved',
        ]);
    }

    public function test_cannot_process_unapproved_requested_batch(): void
    {
        config(['services.payout.driver' => 'sandbox']);

        [$batch, , , $checker] = $this->createBatchWithOrders('requested');

        $response = $this->actingAs($checker)
            ->withHeaders($this->sensitiveHeaders($checker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/process");

        $response->assertStatus(409);
    }

    public function test_payout_disbursement_successful_with_sandbox_driver(): void
    {
        config(['services.payout.driver' => 'sandbox']);

        [$batch, $order, $item, $checker, $partner] = $this->createBatchWithOrders('approved');

        $response = $this->actingAs($checker)
            ->withHeaders($this->sensitiveHeaders($checker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/process");

        $response->assertOk()
            ->assertJsonPath('status', 'paid');

        // Verify batch status & provider reference
        $batch->refresh();
        $this->assertSame('paid', $batch->status);
        $this->assertNotNull($batch->provider_reference);
        $this->assertNotNull($batch->completed_at);

        // Verify item status
        $item->refresh();
        $this->assertSame('paid', $item->status);
        $this->assertSame($batch->provider_reference, $item->provider_reference);

        // Verify order payout status
        $order->refresh();
        $this->assertSame('paid', $order->payout_status);

        // Verify ledger journal entry
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => PayoutBatch::class,
            'reference_id' => $batch->id,
        ]);

        $journalEntry = JournalEntry::where('reference_type', PayoutBatch::class)
            ->where('reference_id', $batch->id)
            ->firstOrFail();

        // Debit Partner Liability: 135,000
        $this->assertDatabaseHas('journal_transactions', [
            'journal_entry_id' => $journalEntry->id,
            'type' => 'debit',
            'amount' => 135000,
        ]);

        // Credit Payment Gateway Asset: 135,000
        $this->assertDatabaseHas('journal_transactions', [
            'journal_entry_id' => $journalEntry->id,
            'type' => 'credit',
            'amount' => 135000,
        ]);
    }

    /**
     * Skenario T13: Timeout payout lalu retry.
     * Menguji bahwa timeout provider saat pembuatan payout ditangani secara aman dengan lookup,
     * dan retry tidak menduplikasi pembayaran atau jurnal finansial.
     */
    public function test_payout_timeout_then_retry_uses_lookup_without_duplicate_disbursement(): void
    {
        config(['services.payout.driver' => 'sandbox']);

        [$batch, $order, $item, $checker, $partner] = $this->createBatchWithOrders('approved');

        // Step 1: Simulate network timeout on initial disbursement
        SandboxPayoutGateway::simulateTimeout(true);
        SandboxPayoutGateway::simulateLookupFailure(true);

        $response = $this->actingAs($checker)
            ->withHeaders($this->sensitiveHeaders($checker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/process");

        // The system marks the batch as processing/uncertain with failure reason
        // without corrupting data or double-sending
        $response->assertOk();
        $batch->refresh();
        $this->assertSame('processing', $batch->status);
        $this->assertNotNull($batch->failure_reason);
        $this->assertStringContainsString('Timeout provider', $batch->failure_reason);

        // Orders and items remain safe
        $order->refresh();
        $this->assertSame('requested', $order->payout_status);

        // No ledger transactions yet
        $this->assertDatabaseMissing('journal_entries', [
            'reference_type' => PayoutBatch::class,
            'reference_id' => $batch->id,
        ]);

        // Step 2: Operator retries the payout now that network is restored
        SandboxPayoutGateway::resetSimulation();

        $retryResponse = $this->actingAs($checker)
            ->withHeaders($this->sensitiveHeaders($checker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/process");

        $retryResponse->assertOk()
            ->assertJsonPath('status', 'paid');

        // Batch transitions to paid safely via lookup
        $batch->refresh();
        $this->assertSame('paid', $batch->status);
        $this->assertNull($batch->failure_reason);

        $order->refresh();
        $this->assertSame('paid', $order->payout_status);

        // Exactly one journal entry exists
        $this->assertSame(1, JournalEntry::where('reference_type', PayoutBatch::class)->where('reference_id', $batch->id)->count());
    }

    public function test_complete_batch_with_manual_proof_records_ledger_and_updates_status(): void
    {
        [$batch, $order, $item, $checker] = $this->createBatchWithOrders('approved');

        // Missing proof fails closed
        $response = $this->actingAs($checker)
            ->withHeaders($this->sensitiveHeaders($checker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/complete");

        $response->assertStatus(503)
            ->assertJsonPath('code', 'PAYOUT_PROVIDER_PROOF_REQUIRED');

        // Submitting with valid proof reference completes batch
        $response = $this->actingAs($checker)
            ->withHeaders($this->sensitiveHeaders($checker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/complete", [
                'proof_reference' => 'BCA-TRX-20261008-998811',
                'notes' => 'Transfer manual teller telah diverifikasi.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.provider_reference', 'BCA-TRX-20261008-998811');

        $batch->refresh();
        $this->assertSame('paid', $batch->status);

        $order->refresh();
        $this->assertSame('paid', $order->payout_status);

        // Ledger recorded
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => PayoutBatch::class,
            'reference_id' => $batch->id,
        ]);
    }

    public function test_duplicate_process_on_already_paid_batch_is_idempotent(): void
    {
        config(['services.payout.driver' => 'sandbox']);

        [$batch, , , $checker] = $this->createBatchWithOrders('approved');

        // First process
        $this->actingAs($checker)
            ->withHeaders($this->sensitiveHeaders($checker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/process")
            ->assertOk();

        $entryCountBefore = JournalEntry::where('reference_type', PayoutBatch::class)->count();

        // Second process (idempotent)
        $this->actingAs($checker)
            ->withHeaders($this->sensitiveHeaders($checker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/process")
            ->assertOk()
            ->assertJsonPath('status', 'paid');

        $entryCountAfter = JournalEntry::where('reference_type', PayoutBatch::class)->count();
        $this->assertSame($entryCountBefore, $entryCountAfter);
    }
}
