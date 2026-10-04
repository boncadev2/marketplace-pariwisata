<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_process()
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $order = Order::factory()->create([
            'status' => 'paid',
            'total' => 100000,
        ]);

        $attempt1 = PaymentAttempt::create([
            'order_id' => $order->id,
            'provider' => 'sandbox',
            'provider_reference' => 'gw-123',
            'status' => 'succeeded',
            'currency' => 'IDR',
            'amount' => 100000,
        ]);

        $attempt2 = PaymentAttempt::create([
            'order_id' => $order->id,
            'provider' => 'sandbox',
            'provider_reference' => 'gw-mismatch',
            'status' => 'succeeded',
            'currency' => 'IDR',
            'amount' => 100000, // actual amount
        ]);

        JournalEntry::create([
            'order_id' => $order->id,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'description' => 'Payment received',
        ]);

        $payload = [
            'reference' => 'batch-001',
            'date' => '2026-09-28',
            'mutations' => [
                [
                    'reference' => 'gw-123',
                    'amount' => 100000,
                ],
                [
                    'reference' => 'gw-mismatch',
                    'amount' => 50000, // expected 100k
                ],
                [
                    'reference' => 'gw-456',
                    'amount' => 50000,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->withHeaders($this->sensitiveHeaders($admin))
            ->postJson('/api/v1/reconciliation', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonPath('data.entries.0.status', 'matched');
        $response->assertJsonPath('data.entries.1.status', 'mismatched');
        $response->assertJsonPath('data.entries.2.status', 'not_found');

        $this->assertDatabaseHas('reconciliation_batches', [
            'reference' => 'batch-001',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('reconciliation_entries', [
            'payment_gateway_reference' => 'gw-123',
            'status' => 'matched',
        ]);

        $this->assertDatabaseHas('reconciliation_entries', [
            'payment_gateway_reference' => 'gw-mismatch',
            'status' => 'mismatched',
        ]);

        $this->assertDatabaseHas('reconciliation_entries', [
            'payment_gateway_reference' => 'gw-456',
            'status' => 'not_found',
        ]);
    }
}
