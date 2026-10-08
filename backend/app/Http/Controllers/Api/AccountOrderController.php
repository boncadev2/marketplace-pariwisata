<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryHold;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Voucher;
use App\Support\CommerceMode;
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
            ->with(['items:id,order_id,product_id,name,quantity', 'reviews:id,order_id,rating', 'disputes:id,order_id,reason,description,status,resolution'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $orders->getCollection()->map(fn (Order $order) => $this->summary($order)),
            'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        $order = Order::query()->where('user_id', $request->user()->id)->where('public_id', $publicId)->with(['items:id,order_id,name,quantity,snapshot', 'partner:id,name,contact_email,contact_phone', 'disputes'])->firstOrFail();
        $attempt = PaymentAttempt::query()->where('order_id', $order->id)->orderByDesc('id')->first();

        return response()->json(['data' => [
            ...$this->summary($order),
            'payment_status' => $attempt?->status,
            'checkout_url' => $this->resumeUrl($order, $attempt),
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

    public function updateParticipants(Request $request, string $publicId): JsonResponse
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('public_id', $publicId)
            ->firstOrFail();

        $validated = $request->validate([
            'participants' => 'required|array',
            'participants.*.name' => 'required|string|max:120',
            'participants.*.id_number' => 'nullable|string|max:50',
            'participants.*.phone' => 'nullable|string|max:30',
            'participants.*.emergency_contact' => 'nullable|string|max:120',
            'participants.*.notes' => 'nullable|string|max:255',
        ]);

        $snapshot = $order->policy_snapshot ?? [];
        $snapshot['participants'] = $validated['participants'];
        $order->update(['policy_snapshot' => $snapshot]);

        return response()->json([
            'message' => 'Manifest peserta berhasil disimpan.',
            'data' => [
                'order_id' => $order->public_id,
                'participants' => $snapshot['participants'],
            ],
        ])->header('Cache-Control', 'private, no-store');
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

    private function resumeUrl(Order $order, ?PaymentAttempt $attempt): ?string
    {
        if (! (app()->environment(['local', 'testing', 'staging']) || CommerceMode::enabled()) || $order->status !== 'pending_payment' || $attempt?->status !== 'pending' || ! in_array($attempt->provider, ['midtrans_sandbox', 'midtrans_production'], true) || (int) $attempt->amount !== (int) $order->total || $attempt->currency !== $order->currency) {
            return null;
        }
        $url = $attempt->checkout_url;
        if (! is_string($url) || ! preg_match($attempt->provider === 'midtrans_production' ? '~^https://app\.midtrans\.com/snap/v4/redirection/[A-Za-z0-9_-]+$~D' : '~^https://app\.sandbox\.midtrans\.com/snap/v4/redirection/[A-Za-z0-9_-]+$~D', $url)) {
            return null;
        }
        $ids = $order->items->map(fn ($item) => $item->snapshot['inventory_hold_id'] ?? null);
        if ($ids->isEmpty() || $ids->contains(null) || $ids->unique()->count() !== $ids->count()) {
            return null;
        }
        $active = InventoryHold::query()->whereIn('id', $ids)->where('state', 'active')->where('expires_at', '>', now())->count();

        return $active === $ids->count() ? $url : null;
    }

    private function summary(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_id' => $order->public_id,
            'status' => $order->status,
            'currency' => $order->currency,
            'total' => $order->total,
            'customer_name' => $order->customer_name,
            'visit_date' => $order->policy_snapshot['visit_date'] ?? null,
            'participants' => $order->policy_snapshot['participants'] ?? [],
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->name,
                'quantity' => $item->quantity,
            ])->all(),
            'has_review' => $order->relationLoaded('reviews') ? $order->reviews->isNotEmpty() : $order->reviews()->exists(),
            'disputes' => $order->relationLoaded('disputes') ? $order->disputes->map(fn ($d) => [
                'id' => $d->id,
                'reason' => $d->reason,
                'description' => $d->description,
                'status' => $d->status,
                'resolution' => $d->resolution,
            ])->all() : [],
            'created_at' => $order->created_at->toIso8601String(),
        ];
    }
}
