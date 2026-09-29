<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PayoutController extends Controller
{
    public function eligible(Request $request)
    {
        $orders = Order::where('payout_status', 'eligible')
            ->where('has_dispute', false)
            ->where(function ($query) {
                $query->whereNull('dispute_until')
                      ->orWhere('dispute_until', '<', now());
            })
            ->with('partner')
            ->get();

        $eligibleFunds = $orders->groupBy('partner_id')->map(function ($partnerOrders) {
            $partner = $partnerOrders->first()->partner;
            $totalAmount = $partnerOrders->sum(function ($order) {
                $commission = $order->items->sum('commission_amount');
                return $order->total - $commission;
            });
            return [
                'partner_id' => $partner->id,
                'partner_name' => $partner->name,
                'total_orders' => $partnerOrders->count(),
                'total_amount' => $totalAmount,
                'orders' => $partnerOrders->pluck('id'),
            ];
        })->values();

        return response()->json(['data' => $eligibleFunds]);
    }

    public function storeBatch(Request $request)
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(['manual', 'bank_transfer', 'api'])],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.partner_id' => ['required', 'exists:partners,id'],
            'items.*.order_ids' => ['required', 'array'],
            'items.*.order_ids.*' => ['required', 'exists:orders,id'],
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $batch = PayoutBatch::create([
                'batch_number' => 'PO-' . strtoupper(Str::random(10)),
                'maker_id' => $request->user()->id ?? 1, // fallback for testing without auth
                'provider' => $validated['provider'],
                'notes' => $validated['notes'],
                'status' => 'requested',
            ]);

            $totalBatchAmount = 0;

            foreach ($validated['items'] as $item) {
                foreach ($item['order_ids'] as $orderId) {
                    $order = Order::where('id', $orderId)
                        ->where('partner_id', $item['partner_id'])
                        ->where('payout_status', 'eligible')
                        ->where('has_dispute', false)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $commission = $order->items()->sum('commission_amount');
                    $payoutAmount = $order->total - $commission;

                    PayoutItem::create([
                        'payout_batch_id' => $batch->id,
                        'partner_id' => $item['partner_id'],
                        'order_id' => $order->id,
                        'amount' => $payoutAmount,
                        'status' => 'requested',
                    ]);

                    $order->update(['payout_status' => 'requested']);
                    $totalBatchAmount += $payoutAmount;
                }
            }

            $batch->update(['total_amount' => $totalBatchAmount]);

            return response()->json(['data' => $batch->load('items')], 201);
        });
    }

    public function approveBatch(Request $request, PayoutBatch $batch)
    {
        if ($batch->status !== 'requested') {
            return response()->json(['message' => 'Batch is not in requested status'], 400);
        }

        $batch->update([
            'checker_id' => $request->user()->id ?? 2,
            'status' => 'approved',
        ]);
        
        $batch->items()->update(['status' => 'approved']);

        return response()->json(['data' => $batch]);
    }

    public function processBatch(Request $request, PayoutBatch $batch)
    {
        if (!in_array($batch->status, ['approved', 'processing'])) {
            return response()->json(['message' => 'Batch cannot be processed'], 400);
        }

        // Handle timeout by checking status before retry
        if ($batch->status === 'processing') {
            // Check provider status (mock implementation)
            // If still processing, return early
            return response()->json(['message' => 'Batch is already processing', 'data' => $batch]);
        }

        $batch->update(['status' => 'processing']);
        $batch->items()->update(['status' => 'processing']);

        return response()->json(['data' => $batch]);
    }

    public function completeBatch(Request $request, PayoutBatch $batch)
    {
        if ($batch->status !== 'processing') {
            return response()->json(['message' => 'Batch is not processing'], 400);
        }

        DB::transaction(function () use ($batch) {
            $batch->update(['status' => 'paid']);
            $batch->items()->update(['status' => 'paid']);

            $orderIds = $batch->items()->pluck('order_id');
            Order::whereIn('id', $orderIds)->update(['payout_status' => 'paid']);
        });

        return response()->json(['data' => $batch]);
    }
}
