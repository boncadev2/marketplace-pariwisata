<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerBankAccount;
use App\Models\PartnerMember;
use App\Models\PayoutBatch;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_eligible_funds()
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $partner = Partner::factory()->create();
        $product = Product::factory()->create(['partner_id' => $partner->id]);
        $order = Order::factory()->create([
            'partner_id' => $partner->id,
            'payout_status' => 'eligible',
            'status' => 'paid',
            'has_dispute' => false,
            'dispute_until' => now()->subDay(),
            'total' => 100000,
        ]);

        // Mock order items with commission
        $order->items()->create([
            'product_id' => $product->id,
            'name' => 'Test',
            'quantity' => 1,
            'unit_price' => 100000,
            'total' => 100000,
            'snapshot' => [],
            'commission_amount' => 10000,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/payouts/eligible');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.partner_id', $partner->id);
        $response->assertJsonPath('data.0.total_amount', 90000);
    }

    public function test_maker_can_create_payout_batch()
    {
        $maker = User::factory()->create(['platform_role' => 'super_admin']);
        $partner = Partner::factory()->create();
        $product = Product::factory()->create(['partner_id' => $partner->id]);
        PartnerBankAccount::factory()->create(['partner_id' => $partner->id, 'is_verified' => true, 'is_active' => true]);
        $order = Order::factory()->create([
            'partner_id' => $partner->id,
            'payout_status' => 'eligible',
            'status' => 'paid',
            'total' => 100000,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'name' => 'Test',
            'quantity' => 1,
            'unit_price' => 100000,
            'total' => 100000,
            'snapshot' => [],
            'commission_amount' => 10000,
        ]);

        $payload = [
            'provider' => 'bank_transfer',
            'notes' => 'Weekly payout',
            'items' => [
                [
                    'partner_id' => $partner->id,
                    'order_ids' => [$order->id],
                ],
            ],
        ];

        $response = $this->actingAs($maker)->withHeaders($this->sensitiveHeaders($maker))
            ->postJson('/api/v1/payouts/batches', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('payout_batches', ['provider' => 'bank_transfer']);
        $this->assertDatabaseHas('payout_items', ['order_id' => $order->id, 'amount' => 90000]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payout_status' => 'requested']);
    }

    public function test_checker_can_approve_batch()
    {
        $maker = User::factory()->create(['platform_role' => 'super_admin']);
        $checker = User::factory()->create(['platform_role' => 'super_admin']);
        $batch = PayoutBatch::factory()->create(['maker_id' => $maker->id, 'status' => 'requested']);

        $response = $this->actingAs($checker)->withHeaders($this->sensitiveHeaders($checker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/approve");

        $response->assertStatus(200);
        $this->assertDatabaseHas('payout_batches', ['id' => $batch->id, 'status' => 'approved']);
    }

    public function test_partner_bank_account_verification()
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $account = PartnerBankAccount::factory()->create(['is_verified' => false]);

        $response = $this->actingAs($admin)->withHeaders($this->sensitiveHeaders($admin))
            ->postJson("/api/v1/partner-bank-accounts/{$account->id}/verify");

        $response->assertStatus(200);
        $this->assertDatabaseHas('partner_bank_accounts', ['id' => $account->id, 'is_verified' => true]);
    }

    public function test_maker_checker_and_provider_proof_are_enforced(): void
    {
        $maker = User::factory()->create(['platform_role' => 'super_admin']);
        $checker = User::factory()->create(['platform_role' => 'super_admin']);
        $batch = PayoutBatch::factory()->create(['maker_id' => $maker->id, 'status' => 'requested']);

        $this->actingAs($maker)->withHeaders($this->sensitiveHeaders($maker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/approve")
            ->assertConflict();

        $this->actingAs($checker)->withHeaders($this->sensitiveHeaders($checker))
            ->postJson("/api/v1/payouts/batches/{$batch->id}/approve")
            ->assertOk();

        $this->postJson("/api/v1/payouts/batches/{$batch->id}/process")
            ->assertStatus(503)
            ->assertJsonPath('code', 'PAYOUT_PROVIDER_NOT_CONFIGURED');

        $this->assertDatabaseHas('payout_batches', ['id' => $batch->id, 'status' => 'approved']);
    }

    public function test_partner_only_sees_masked_account_for_own_tenant(): void
    {
        $owner = User::factory()->create();
        $partner = Partner::factory()->create();
        PartnerMember::factory()->create([
            'user_id' => $owner->id,
            'partner_id' => $partner->id,
            'role' => 'owner',
            'is_active' => true,
        ]);
        PartnerBankAccount::factory()->create([
            'partner_id' => $partner->id,
            'account_number' => '1234567890',
        ]);

        $this->actingAs($owner)->getJson("/api/v1/partner-bank-accounts?partner_id={$partner->id}")
            ->assertOk()
            ->assertJsonPath('data.0.account_number_masked', '******7890')
            ->assertJsonMissingPath('data.0.account_number');
    }

    public function test_pending_refund_excludes_order_from_payout_selection_and_batch(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $order = Order::factory()->create(['status' => 'paid', 'payout_status' => 'eligible', 'has_dispute' => false]);
        PartnerBankAccount::factory()->create(['partner_id' => $order->partner_id, 'is_verified' => true, 'is_active' => true]);
        RefundRequest::factory()->create(['order_id' => $order->id, 'status' => 'processing']);
        $this->actingAs($admin)->getJson('/api/v1/payouts/eligible')->assertOk()->assertJsonCount(0, 'data');
        $this->withHeaders($this->sensitiveHeaders($admin))->postJson('/api/v1/payouts/batches', ['provider' => 'bank_transfer', 'items' => [['partner_id' => $order->partner_id, 'order_ids' => [$order->id]]]])->assertNotFound();
        $this->assertDatabaseCount('payout_items', 0);
        $this->assertSame('eligible', $order->fresh()->payout_status);
    }

    public function test_admin_can_list_payout_batches(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        PayoutBatch::factory()->count(2)->create();

        $this->actingAs($admin)->getJson('/api/v1/payouts/batches')
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total']);
    }
}

