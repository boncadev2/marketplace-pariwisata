<?php

namespace App\Services;

use App\Models\InventoryHold;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class ExpireOrderPayments
{
    public function run(): int
    {
        $expired = 0;
        Order::query()->where('status', 'pending_payment')->chunkById(100, function ($orders) use (&$expired): void {
            foreach ($orders as $candidate) {
                $changed = DB::transaction(function () use ($candidate): bool {
                    $order = Order::query()->lockForUpdate()->findOrFail($candidate->id);
                    if ($order->status !== 'pending_payment') {
                        return false;
                    }
                    $items = $order->items()->get();
                    if ($items->isEmpty()) {
                        return false;
                    }
                    foreach ($items as $item) {
                        $hold = InventoryHold::find($item->snapshot['inventory_hold_id'] ?? null);
                        if (! $hold || $hold->state !== 'expired' || $hold->expires_at->isFuture()) {
                            return false;
                        }
                    }
                    $order->update(['status' => 'expired']);
                    app(TransactionOutbox::class)->record($order, 'expired', 'payment-window', 'Kuota sementara telah dilepas. Pembayaran yang telanjur berhasil akan direkonsiliasi; jangan membayar ulang.');

                    return true;
                }, 3);
                $expired += (int) $changed;
            }
        });

        return $expired;
    }
}
