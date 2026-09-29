<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerBankAccount;
use App\Models\PayoutBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_eligible_funds()
    {
        $partner = Partner::factory()->create();
        $order = Order::factory()->create([
            'partner_id' => $partner->id,
            'payout_status' => 'eligible',
            'has_dispute' => false,
            'dispute_until' => now()->subDay(),
            'total' => 100000,
        ]);
        
        // Mock order items with commission
        $order->items()->create([
            'product_id' => 1,
            'name' => 'Test',
            'quantity' => 1,
            'unit_price' => 100000,
            'total' => 100000,
            'snapshot' => [],
            'commission_amount' => 10000
        ]);

        $response = $this->getJson('/api/v1/payouts/eligible');
        
        $response->assertStatus(200);
        $response->assertJsonPath('data.0.partner_id', $partner->id);
        $response->assertJsonPath('data.0.total_amount', 90000);
    }

    public function test_maker_can_create_payout_batch()
    {
        $partner = Partner::factory()->create();
        $order = Order::factory()->create([
            'partner_id' => $partner->id,
            'payout_status' => 'eligible',
            'total' => 100000,
        ]);
        $order->items()->create([
            'product_id' => 1,
            'name' => 'Test',
            'quantity' => 1,
            'unit_price' => 100000,
            'total' => 100000,
            'snapshot' => [],
            'commission_amount' => 10000
        ]);

        $payload = [
            'provider' => 'bank_transfer',
            'notes' => 'Weekly payout',
            'items' => [
                [
                    'partner_id' => $partner->id,
                    'order_ids' => [$order->id],
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/payouts/batches', $payload);
        
        $response->assertStatus(201);
        $this->assertDatabaseHas('payout_batches', ['provider' => 'bank_transfer']);
        $this->assertDatabaseHas('payout_items', ['order_id' => $order->id, 'amount' => 90000]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payout_status' => 'requested']);
    }

    public function test_checker_can_approve_batch()
    {
        $batch = PayoutBatch::factory()->create(['status' => 'requested']);
        
        $response = $this->postJson("/api/v1/payouts/batches/{$batch->id}/approve");
        
        $response->assertStatus(200);
        $this->assertDatabaseHas('payout_batches', ['id' => $batch->id, 'status' => 'approved']);
    }

    public function test_partner_bank_account_verification()
    {
        $account = PartnerBankAccount::factory()->create(['is_verified' => false]);
        
        $response = $this->postJson("/api/v1/partner-bank-accounts/{$account->id}/verify");
        
        $response->assertStatus(200);
        $this->assertDatabaseHas('partner_bank_accounts', ['id' => $account->id, 'is_verified' => true]);
    }
}
