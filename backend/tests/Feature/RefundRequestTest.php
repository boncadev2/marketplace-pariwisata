<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\Refund\RefundAdapterInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_request_refund_for_paid_order()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'paid',
            'total' => 100000,
            'policy_snapshot' => [
                'is_refundable' => true,
                'refund_percentage' => 100,
                'refund_cutoff_hours' => 24,
                'visit_date' => now()->addDays(2)->toIso8601String(),
            ],
        ]);
        PaymentAttempt::factory()->create(['order_id' => $order->id, 'status' => 'succeeded', 'amount' => 100000]);

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/refunds", [
            'reason' => 'Change of plans',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('refund_requests', [
            'order_id' => $order->id,
            'reason' => 'Change of plans',
            'status' => 'requested',
            'refundable_amount' => 100000,
        ]);
    }

    public function test_cannot_request_refund_past_cutoff_time()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'paid',
            'total' => 100000,
            'policy_snapshot' => [
                'is_refundable' => true,
                'refund_percentage' => 100,
                'refund_cutoff_hours' => 24,
                'visit_date' => now()->addHours(12)->toIso8601String(), // Past the 24 hours cutoff
            ],
        ]);
        PaymentAttempt::factory()->create(['order_id' => $order->id, 'status' => 'succeeded', 'amount' => 100000]);

        $response = $this->actingAs($user)->postJson("/api/v1/orders/{$order->id}/refunds", [
            'reason' => 'Change of plans',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Nilai refund nol atau telah melewati batas waktu.');
    }

    public function test_can_approve_and_process_refund()
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $refundRequest = RefundRequest::factory()->create([
            'status' => 'requested',
        ]);

        $refundRequest->order->update(['status' => 'paid']);

        $mockAdapter = \Mockery::mock(RefundAdapterInterface::class);
        $mockAdapter->shouldReceive('process')->once()->andReturn([
            'confirmed' => true,
            'provider_reference' => 'refund-test-1',
            'payload' => ['verified' => true],
        ]);
        $this->app->instance(RefundAdapterInterface::class, $mockAdapter);

        $response = $this->actingAs($admin)->withHeaders($this->sensitiveHeaders($admin))
            ->postJson("/api/v1/refunds/{$refundRequest->id}/approve", [
                'notes' => 'Approved manually',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('refund_requests', [
            'id' => $refundRequest->id,
            'status' => 'succeeded',
            'decided_by' => $admin->id,
            'decision_notes' => 'Approved manually',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $refundRequest->order_id,
            'status' => 'refunded',
        ]);
    }

    public function test_can_reject_refund()
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $refundRequest = RefundRequest::factory()->create([
            'status' => 'requested',
        ]);

        $response = $this->actingAs($admin)->withHeaders($this->sensitiveHeaders($admin))
            ->postJson("/api/v1/refunds/{$refundRequest->id}/reject", [
                'notes' => 'Not eligible',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('refund_requests', [
            'id' => $refundRequest->id,
            'status' => 'rejected',
            'decided_by' => $admin->id,
            'decision_notes' => 'Not eligible',
        ]);
    }
}
