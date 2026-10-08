<?php

namespace App\Services;

use App\Models\InventoryHold;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundRequest;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherCheckIn;
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
                Voucher::create([
                    'order_item_id' => $item->id,
                    'partner_id' => $order->partner_id,
                    'token_hash' => hash('sha256', $token),
                    'token' => $token,
                    'service_date' => $hold->bucket->service_date,
                    'admissions' => $item->quantity,
                ]);
            }
        });
    }

    public function inspect(string $token, User $staff): array
    {
        $voucher = Voucher::where('token_hash', hash('sha256', $token))->firstOrFail();
        $item = OrderItem::with(['product.destination', 'order.partner'])->findOrFail($voucher->order_item_id);
        $order = $item->order;

        $isSuperAdmin = $staff->platform_role === 'super_admin';
        $member = $staff->partnerMemberships()->where('partner_id', $voucher->partner_id)->where('is_active', true)->whereIn('role', ['owner', 'manager', 'staff'])->first();

        abort_unless($isSuperAdmin || $member !== null, 403, 'Akun Anda tidak memiliki hak akses sebagai petugas mitra untuk voucher ini.');

        $isToday = $voucher->service_date->toDateString() === now('Asia/Jakarta')->toDateString();

        return [
            'voucher_id' => $voucher->id,
            'token' => $voucher->token,
            'status' => $voucher->status,
            'service_date' => $voucher->service_date->toDateString(),
            'service_date_formatted' => $voucher->service_date->format('d/m/Y'),
            'is_today' => $isToday,
            'today_date' => now('Asia/Jakarta')->toDateString(),
            'admissions' => $voucher->admissions,
            'used_admissions' => $voucher->used_admissions,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'order_id' => $order->public_id,
            'product_name' => $item->name,
            'destination_name' => $item->product?->destination?->name,
            'partner_name' => $order->partner?->name,
            'participants' => $order->policy_snapshot['participants'] ?? [],
            'can_override' => $isSuperAdmin,
            'has_refund' => RefundRequest::where('order_id', $order->id)->whereIn('status', ['requested', 'approved', 'processing'])->exists(),
        ];
    }

    public function redeem(string $token, User $staff, ?string $overrideReason = null): Voucher
    {
        return DB::transaction(function () use ($token, $staff, $overrideReason): Voucher {
            $override = $overrideReason !== null;
            abort_if($override && $staff->platform_role !== 'super_admin', 403);
            $voucher = Voucher::where('token_hash', hash('sha256', $token))->firstOrFail();
            $item = OrderItem::findOrFail($voucher->order_item_id);

            $isSuperAdmin = $staff->platform_role === 'super_admin';
            if (! $override && ! $isSuperAdmin) {
                $member = $staff->partnerMemberships()->where('partner_id', $voucher->partner_id)->where('is_active', true)->whereIn('role', ['owner', 'manager', 'staff'])->first();
                abort_unless($member !== null, 404);
                $destinationId = $item->product->destination_id;
                abort_if($member->role === 'staff' && $destinationId !== null && (int) $member->destination_id !== (int) $destinationId, 404);
            }

            $order = Order::query()->lockForUpdate()->findOrFail($item->order_id);
            $voucher = Voucher::query()->lockForUpdate()->findOrFail($voucher->id);
            abort_if(RefundRequest::where('order_id', $order->id)->whereIn('status', ['requested', 'approved', 'processing'])->exists(), 409, 'Voucher ditahan selama pengajuan refund ditinjau.');

            abort_unless($order->status === 'paid', 409, 'Pesanan voucher belum lunas atau dibatalkan.');
            abort_unless($voucher->status === 'active', 409, $voucher->status === 'redeemed' ? 'Voucher ini sudah pernah divalidasi dan digunakan.' : 'Status voucher tidak aktif.');

            $isToday = $voucher->service_date->toDateString() === now('Asia/Jakarta')->toDateString();
            abort_unless($override || $isToday, 409, 'Tanggal kunjungan voucher (' . $voucher->service_date->format('d/m/Y') . ') tidak sesuai dengan hari ini (' . now('Asia/Jakarta')->format('d/m/Y') . '). Hubungi administrator untuk persetujuan validasi di luar jadwal.');

            $voucher->update([
                'status' => 'redeemed',
                'used_admissions' => $voucher->admissions,
                'redeemed_by' => $staff->id,
                'redeemed_at' => now(),
            ]);

            VoucherCheckIn::create([
                'voucher_id' => $voucher->id,
                'user_id' => $staff->id,
                'admissions' => $voucher->admissions,
                'override_reason' => $overrideReason,
            ]);

            return $voucher;
        });
    }
}
