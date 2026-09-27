<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class GuestVoucherController extends Controller
{
    public function show(Request $request, string $publicId): JsonResponse
    {
        $order = Order::where('public_id', $publicId)->firstOrFail();
        $token = $request->header('X-Guest-Access-Token');
        abort_unless(is_string($token) && strlen($token) === 48 && Hash::check($token, $order->guest_access_hash), 404);
        $vouchers = $order->status === 'paid'
            ? Voucher::whereIn('order_item_id', $order->items()->pluck('id'))->get()->map(fn (Voucher $voucher) => ['token' => $voucher->token, 'status' => $voucher->status, 'service_date' => $voucher->service_date->toDateString(), 'admissions' => $voucher->admissions, 'used_admissions' => $voucher->used_admissions])
            : [];

        return response()->json(['data' => ['order_id' => $order->public_id, 'status' => $order->status, 'vouchers' => $vouchers]])->header('Cache-Control', 'private, no-store');
    }
}
