<?php

namespace App\Services;

use App\Models\InventoryHold;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VoucherService
{
    public function issue(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'paid') {
                return;
            }
            foreach ($order->items as $item) {
                if (Voucher::where('order_item_id', $item->id)->exists()) {
                    continue;
                }
                $hold = InventoryHold::find($item->snapshot['inventory_hold_id'] ?? null);
                if ($hold === null || $hold->state !== 'confirmed') {
                    continue;
                }
                $token = Str::random(48);
                Voucher::create(['order_item_id' => $item->id, 'partner_id' => $order->partner_id, 'token_hash' => hash('sha256', $token), 'token' => $token, 'service_date' => $hold->bucket->service_date, 'admissions' => $item->quantity]);
            }
        });
    }

    public function redeem(string $token, User $staff): Voucher
    {
        return DB::transaction(function () use ($token, $staff): Voucher {
            $voucher = Voucher::where('token_hash', hash('sha256', $token))->firstOrFail();
            abort_unless($staff->partnerMemberships()->where('partner_id', $voucher->partner_id)->where('is_active', true)->whereIn('role', ['owner', 'manager', 'staff'])->exists(), 404);
            $item = OrderItem::findOrFail($voucher->order_item_id);
            $order = Order::query()->lockForUpdate()->findOrFail($item->order_id);
            $voucher = Voucher::query()->lockForUpdate()->findOrFail($voucher->id);
            abort_unless($order->status === 'paid' && $voucher->status === 'active' && $voucher->service_date->toDateString() === now('Asia/Jakarta')->toDateString(), 409, 'Voucher tidak dapat digunakan.');
            $voucher->update(['status' => 'redeemed', 'used_admissions' => $voucher->admissions, 'redeemed_by' => $staff->id, 'redeemed_at' => now()]);

            return $voucher;
        });
    }
}
