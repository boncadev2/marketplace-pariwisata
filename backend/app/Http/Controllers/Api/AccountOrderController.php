<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AccountOrderController extends Controller
{
    private const STATUSES = ['pending_payment', 'expired', 'paid', 'payment_exception', 'cancelled', 'refunded'];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', 'string', Rule::in(self::STATUSES)]]);
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->when(isset($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->with('items:id,order_id,name,quantity')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $orders->getCollection()->map(fn (Order $order) => $this->summary($order)),
            'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        $order = Order::query()->where('user_id', $request->user()->id)->where('public_id', $publicId)->with(['items:id,order_id,name,quantity', 'partner:id,name,contact_email,contact_phone'])->firstOrFail();
        $paymentStatus = PaymentAttempt::query()->where('order_id', $order->id)->orderByDesc('id')->value('status');

        return response()->json(['data' => [
            ...$this->summary($order),
            'payment_status' => $paymentStatus,
            'manager' => ['name' => $order->partner->name, 'email' => $order->partner->contact_email, 'phone' => $order->partner->contact_phone],
            'receipt_available' => false,
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function vouchers(Request $request, string $publicId): JsonResponse
    {
        $order = Order::query()->where('user_id', $request->user()->id)->where('public_id', $publicId)->firstOrFail();
        $vouchers = $order->status === 'paid'
            ? Voucher::query()->whereIn('order_item_id', $order->items()->pluck('id'))->get()->map(fn (Voucher $voucher) => [
                'token' => $voucher->token,
                'status' => $voucher->status,
                'service_date' => $voucher->service_date->toDateString(),
                'admissions' => $voucher->admissions,
                'used_admissions' => $voucher->used_admissions,
            ])
            : [];

        return response()->json(['data' => ['order_id' => $order->public_id, 'status' => $order->status, 'vouchers' => $vouchers]])->header('Cache-Control', 'private, no-store');
    }

    public function claim(Request $request): JsonResponse
    {
        $data = $request->validate(['order_id' => ['required', 'string', 'max:128']]);
        $token = $request->header('X-Guest-Access-Token');
        abort_unless(is_string($token) && strlen($token) === 48, 404);
        $user = $request->user();
        abort_unless($user->hasVerifiedEmail(), 403, 'Verifikasi email akun sebelum mengklaim pesanan.');

        $order = DB::transaction(function () use ($data, $token, $user): Order {
            $order = Order::query()->where('public_id', $data['order_id'])->lockForUpdate()->firstOrFail();
            abort_unless(
                ($order->user_id === null || $order->user_id === $user->id)
                && mb_strtolower($order->customer_email) === mb_strtolower($user->email)
                && Hash::check($token, $order->guest_access_hash),
                404
            );

            if ($order->user_id === null) {
                $order->update(['user_id' => $user->id]);
            }

            return $order->load('items:id,order_id,name,quantity');
        }, 3);

        return response()->json(['data' => $this->summary($order)])->header('Cache-Control', 'private, no-store');
    }

    private function summary(Order $order): array
    {
        return [
            'order_id' => $order->public_id,
            'status' => $order->status,
            'currency' => $order->currency,
            'total' => $order->total,
            'visit_date' => $order->policy_snapshot['visit_date'] ?? null,
            'items' => $order->items->map(fn ($item) => ['name' => $item->name, 'quantity' => $item->quantity])->all(),
            'created_at' => $order->created_at->toIso8601String(),
        ];
    }
}
