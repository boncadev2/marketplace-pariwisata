<?php

namespace App\Services\Refund;

use App\Models\InventoryHold;
use App\Models\Order;
use App\Models\RefundRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function __construct(private RefundAdapterInterface $adapter) {}

    public function requestRefund(Order $order, string $reason): RefundRequest
    {
        if ($order->status !== 'paid') {
            throw new \Exception('Only paid orders can be refunded');
        }

        if ($order->refundRequest) {
            throw new \Exception('Refund already requested');
        }

        $policy = $order->policy_snapshot;
        $isRefundable = $policy['is_refundable'] ?? true;

        if (! $isRefundable) {
            throw new \Exception('Order is non-refundable according to policy');
        }

        $refundableAmount = $this->calculateRefundableAmount($order);

        if ($refundableAmount === 0) {
            throw new \Exception('Refundable amount is zero due to cutoff time');
        }

        return RefundRequest::create([
            'order_id' => $order->id,
            'reason' => $reason,
            'status' => 'requested',
            'refundable_amount' => $refundableAmount,
        ]);
    }

    public function calculateRefundableAmount(Order $order): int
    {
        $policy = $order->policy_snapshot;
        $visitDate = $policy['visit_date'] ?? null;

        if (! $visitDate) {
            // Assume 100% refundable if no date is set in policy, though we should probably check order items.
            // But let's follow the standard 100% if no date constraint exists.
            $percentage = $policy['refund_percentage'] ?? 100;

            return (int) round($order->total * ($percentage / 100));
        }

        $cutoffHours = $policy['refund_cutoff_hours'] ?? 24;
        $visitDateTime = Carbon::parse($visitDate); // Assuming local time

        if (now()->addHours($cutoffHours)->isAfter($visitDateTime)) {
            return 0; // Past cutoff time
        }

        $percentage = $policy['refund_percentage'] ?? 100;

        return (int) round($order->total * ($percentage / 100));
    }

    public function approve(RefundRequest $refundRequest, int $adminId, ?string $notes = null): bool
    {
        if ($refundRequest->status !== 'requested') {
            throw new \Exception('Refund request is not in requested state');
        }

        DB::transaction(function () use ($refundRequest, $adminId, $notes) {
            $refundRequest->update([
                'status' => 'approved',
                'decided_by' => $adminId,
                'decision_notes' => $notes,
            ]);

            // Release inventory / admission
            $order = $refundRequest->order;
            // Update order status
            $order->update(['status' => 'cancelled']);

            // Release inventory holds
            foreach ($order->items as $item) {
                $holdId = $item->snapshot['inventory_hold_id'] ?? null;
                if ($holdId) {
                    $hold = InventoryHold::find($holdId);
                    if ($hold && $hold->state === 'confirmed') {
                        $bucket = $hold->bucket;
                        if ($bucket) {
                            $bucket->decrement('confirmed', $hold->quantity);
                        }
                        $hold->update(['state' => 'released', 'released_at' => now()]);
                    }
                }
            }
        });

        // Trigger processing
        return $this->process($refundRequest);
    }

    public function reject(RefundRequest $refundRequest, int $adminId, string $notes): bool
    {
        if ($refundRequest->status !== 'requested') {
            throw new \Exception('Refund request is not in requested state');
        }

        return $refundRequest->update([
            'status' => 'failed',
            'decided_by' => $adminId,
            'decision_notes' => $notes,
        ]);
    }

    public function process(RefundRequest $refundRequest): bool
    {
        $refundRequest->update(['status' => 'processing']);

        try {
            $success = $this->adapter->process($refundRequest);
            if ($success) {
                $refundRequest->update(['status' => 'succeeded']);
                // Update order to refunded
                $refundRequest->order->update(['status' => 'refunded']);
                
                app(\App\Services\LedgerService::class)->recordRefund($refundRequest->order);

                return true;
            }
        } catch (\Exception $e) {
            // Log error
        }

        $refundRequest->update(['status' => 'failed']);

        return false;
    }
}
