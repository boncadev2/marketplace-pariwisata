<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PartnerBankAccount;
use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PayoutController extends Controller
{
    public function eligible(Request $request): JsonResponse
    {
        $orders = Order::where('payout_status', 'eligible')
            ->where('status', 'paid')
            ->whereDoesntHave('refundRequest', fn ($query) => $query->whereIn('status', ['requested', 'approved', 'processing', 'succeeded']))
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

    public function storeBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(['manual', 'bank_transfer', 'api'])],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.partner_id' => ['required', 'exists:partners,id'],
            'items.*.order_ids' => ['required', 'array', 'min:1'],
            'items.*.order_ids.*' => ['required', 'exists:orders,id'],
        ]);

        return DB::transaction(function () use ($validated, $request): JsonResponse {
            $batch = PayoutBatch::create([
                'batch_number' => 'PO-'.strtoupper(Str::random(10)),
                'maker_id' => $request->user()->id,
                'provider' => $validated['provider'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'requested',
            ]);

            $totalBatchAmount = 0;

            foreach ($validated['items'] as $item) {
                $hasVerifiedAccount = PartnerBankAccount::query()
                    ->where('partner_id', $item['partner_id'])
                    ->where('is_verified', true)
                    ->where('is_active', true)
                    ->exists();
                abort_unless($hasVerifiedAccount, 422, 'Partner belum memiliki rekening terverifikasi.');

                foreach ($item['order_ids'] as $orderId) {
                    $order = Order::where('id', $orderId)
                        ->where('partner_id', $item['partner_id'])
                        ->where('payout_status', 'eligible')
                        ->where('status', 'paid')
                        ->whereDoesntHave('refundRequest', fn ($query) => $query->whereIn('status', ['requested', 'approved', 'processing', 'succeeded']))
                        ->where('has_dispute', false)
                        ->where(fn ($query) => $query->whereNull('dispute_until')->orWhere('dispute_until', '<', now()))
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

            $this->audit($request, $batch, 'payout_batch.created');

            return response()->json(['data' => $batch->load('items')], 201);
        });
    }

    public function approveBatch(Request $request, PayoutBatch $batch): JsonResponse
    {
        $batch = DB::transaction(function () use ($request, $batch): PayoutBatch {
            $batch = PayoutBatch::query()->lockForUpdate()->findOrFail($batch->id);

            abort_unless($batch->status === 'requested', 409, 'Batch tidak berstatus requested.');
            abort_if((int) $batch->maker_id === (int) $request->user()->id, 409, 'Maker tidak boleh menjadi checker.');

            $batch->update(['checker_id' => $request->user()->id, 'status' => 'approved']);
            $batch->items()->update(['status' => 'approved']);

            $this->audit($request, $batch, 'payout_batch.approved');

            return $batch->fresh();
        });

        return response()->json(['data' => $batch]);
    }

    public function processBatch(Request $request, PayoutBatch $batch): JsonResponse
    {
        return response()->json([
            'message' => 'Provider payout belum dikonfigurasi; tidak ada status keuangan yang diubah.',
            'code' => 'PAYOUT_PROVIDER_NOT_CONFIGURED',
        ], 503);
    }

    public function completeBatch(Request $request, PayoutBatch $batch): JsonResponse
    {
        return response()->json([
            'message' => 'Status paid hanya boleh berasal dari bukti provider payout yang terverifikasi.',
            'code' => 'PAYOUT_PROVIDER_PROOF_REQUIRED',
        ], 503);
    }

    private function audit(Request $request, PayoutBatch $batch, string $action): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => PayoutBatch::class,
            'auditable_id' => $batch->id,
            'metadata' => ['status' => $batch->status, 'item_count' => $batch->items()->count()],
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
        ]);
    }
}
