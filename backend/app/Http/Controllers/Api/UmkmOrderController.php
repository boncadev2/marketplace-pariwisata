<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\ReservationRefund;
use App\Models\ShippingQuote;
use App\Models\UmkmOrder;
use App\Models\UmkmProduct;
use App\Models\User;
use App\Services\Shipping\BiteshipClient;
use App\Support\CommerceMode;
use App\Support\ServiceManagementAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UmkmOrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Pemesanan UMKM masih tersedia sebagai simulasi lokal.');
        $data = $request->validate(['shipping_quote_id' => 'sometimes|uuid', 'fulfillment' => 'sometimes|in:pickup,delivery', 'shipping_address' => 'required_if:fulfillment,delivery|prohibited_unless:fulfillment,delivery|string|min:10|max:1000', 'postal_code' => ['required_if:fulfillment,delivery', 'prohibited_unless:fulfillment,delivery', 'regex:/^[0-9]{5}$/'], 'expected_shipping_fee' => 'required_if:fulfillment,delivery|prohibited_unless:fulfillment,delivery|integer|min:0|max:1000000', 'product_slug' => 'required|string|max:255', 'quantity' => 'required|integer|min:1|max:100', 'expected_price' => 'required|integer|min:0', 'customer_name' => 'required|string|min:2|max:120', 'customer_phone' => ['required', 'string', 'max:30', 'regex:/^[+0-9 ()-]{7,30}$/'], 'notes' => 'nullable|string|max:500']);
        $data['quantity'] = (int) $data['quantity'];
        $data['expected_price'] = (int) $data['expected_price'];
        if (isset($data['expected_shipping_fee'])) {
            $data['expected_shipping_fee'] = (int) $data['expected_shipping_fee'];
        }
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && strlen($key) >= 16 && strlen($key) <= 100, 422, 'Idempotency-Key wajib 16–100 karakter.');
        $data['notes'] = $data['notes'] ?? null;
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        [$order,$created] = DB::transaction(function () use ($request, $data, $key, $hash): array {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = UmkmOrder::query()->where('user_id', $request->user()->id)->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'Permintaan ulang memiliki data berbeda.');

                return [$existing, false];
            }
            $product = UmkmProduct::query()->where('slug', $data['product_slug'])->where('status', 'published')->lockForUpdate()->firstOrFail();
            $partner = Partner::query()->whereKey($product->partner_id)->where('status', 'approved')->lockForUpdate()->firstOrFail();
            abort_unless($product->price === $data['expected_price'], 409, 'Harga berubah. Muat ulang produk sebelum memesan.');
            abort_unless($product->stock >= $data['quantity'], 409, 'Stok tidak cukup. Kurangi jumlah atau muat ulang produk.');
            $fulfillment = $data['fulfillment'] ?? 'pickup';
            $shippingFee = 0;
            $shippingQuote = null;
            if ($fulfillment === 'delivery') {
                abort_unless($product->delivery_available, 409, 'Pengiriman produk belum tersedia. Pilih ambil di lokasi atau muat ulang produk.');
                if (isset($data['shipping_quote_id'])) {
                    $shippingQuote = ShippingQuote::query()->where('public_id', $data['shipping_quote_id'])->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
                    abort_unless($shippingQuote->umkm_product_id === $product->id && $shippingQuote->quantity === $data['quantity'] && $shippingQuote->postal_code === $data['postal_code'] && $shippingQuote->expires_at->isFuture() && hash_equals($shippingQuote->product_revision, UmkmShippingController::revision($product)), 409, 'Penawaran ongkir berubah atau kedaluwarsa. Periksa ongkir kembali.');
                    $shippingFee = $shippingQuote->fee;
                } else {
                    abort_if(app(BiteshipClient::class)->configured() && $product->origin_postal_code && $product->weight_grams > 0, 422, 'Pilih layanan ekspedisi dan periksa ongkir dahulu.');
                    $shippingFee = $product->shipping_fee;
                }
                abort_unless($shippingFee === (int) $data['expected_shipping_fee'], 409, 'Ongkir berubah. Periksa ongkir kembali.');
            }
            $total = $product->price * $data['quantity'] + $shippingFee;
            abort_unless(is_int($total) && $total <= 9007199254740991, 422, 'Total pesanan di luar batas yang didukung.');
            $product->decrement('stock', $data['quantity']);
            $order = UmkmOrder::create(['public_id' => Str::uuid()->toString(), 'user_id' => $request->user()->id, 'umkm_product_id' => $product->id, 'idempotency_key' => $key, 'payload_hash' => $hash, 'quantity' => $data['quantity'], 'total' => $total, 'fulfillment' => $fulfillment, 'shipping_fee' => $shippingFee, 'shipping_provider' => $shippingQuote ? 'biteship' : null, 'shipping_service' => $shippingQuote?->service, 'carrier' => $shippingQuote?->courier, 'shipping_address' => $data['shipping_address'] ?? null, 'postal_code' => $data['postal_code'] ?? null, 'customer_name' => $data['customer_name'], 'customer_phone' => $data['customer_phone'], 'notes' => $data['notes'], 'status' => 'reserved_sandbox', 'snapshot' => ['name' => $product->name, 'slug' => $product->slug, 'unit' => $product->unit, 'price' => $product->price, 'location' => $product->location, 'seller' => $partner->name, 'fulfillment' => $fulfillment, 'currency' => 'IDR', 'is_demo' => $product->is_demo]]);

            return [$order, true];
        }, 3);

        return response()->json(['data' => $this->present($order)], $created ? 201 : 200)->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['page' => 'nullable|integer|min:1', 'status' => 'nullable|in:reserved_sandbox,processing_sandbox,ready_sandbox,shipped_sandbox,completed_sandbox,cancelled']);
        $orders = UmkmOrder::query()->with('reservationPayment')->where('user_id', $request->user()->id)->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))->latest('id')->paginate(12);

        return response()->json(['data' => $orders->getCollection()->map(fn (UmkmOrder $order): array => $this->present($order))->all(), 'meta' => ['page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()]])->header('Cache-Control', 'private, no-store');
    }

    public function cancel(Request $request, string $publicId): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Pembatalan UMKM masih tersedia sebagai simulasi lokal.');
        $order = DB::transaction(function () use ($request, $publicId): UmkmOrder {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $order = UmkmOrder::query()->where('public_id', $publicId)->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'cancelled') {
                return $order;
            }
            abort_if($order->reservationPayment()->exists(), 409, 'Batalkan pembayaran Midtrans melalui halaman pesanan.');
            abort_unless($order->status === 'reserved_sandbox', 409, 'Pesanan tidak dapat dibatalkan.');
            $product = UmkmProduct::withTrashed()->whereKey($order->umkm_product_id)->lockForUpdate()->firstOrFail();
            $product->increment('stock', $order->quantity);
            $order->update(['status' => 'cancelled']);

            return $order;
        }, 3);

        return response()->json(['data' => $this->present($order)])->header('Cache-Control', 'private, no-store');
    }

    public function partnerIndex(Request $request): JsonResponse
    {
        $filters = $request->validate(['page' => 'nullable|integer|min:1', 'status' => 'nullable|in:reserved_sandbox,processing_sandbox,ready_sandbox,shipped_sandbox,completed_sandbox,cancelled']);
        $partnerIds = $this->managedPartnerIds($request->user());
        $orders = UmkmOrder::query()->with('reservationPayment')->whereHas('product', fn ($query) => $query->whereIn('partner_id', $partnerIds))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))->latest('id')->paginate(12);

        return response()->json(['data' => $orders->getCollection()->map(fn (UmkmOrder $order): array => $this->present($order))->all(), 'meta' => ['page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()]])->header('Cache-Control', 'private, no-store');
    }

    public function partnerUpdate(Request $request, string $publicId): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Pengelolaan pesanan UMKM masih simulasi lokal.');
        $data = $request->validate(['provider_tracking_id' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/D'], 'status' => 'required|in:processing_sandbox,ready_sandbox,shipped_sandbox,completed_sandbox,cancelled', 'revision' => 'sometimes|string|size:64', 'carrier' => 'required_if:status,shipped_sandbox|string|min:2|max:80', 'tracking_number' => ['required_if:status,shipped_sandbox', 'string', 'min:3', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9 ._-]*$/D']]);
        $order = DB::transaction(function () use ($request, $publicId, $data): UmkmOrder {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $partnerIds = $this->managedPartnerIds($request->user());
            $order = UmkmOrder::query()->where('public_id', $publicId)
                ->whereHas('product', fn ($query) => $query->whereIn('partner_id', $partnerIds))->lockForUpdate()->firstOrFail();
            $payment = $order->reservationPayment()->first();
            abort_if($payment && ReservationRefund::where('reservation_payment_id', $payment->id)->whereIn('status', ['requested', 'approved', 'processing'])->exists(), 409, 'Pesanan sedang dalam pengajuan refund; tunggu keputusan admin.');
            abort_if($payment && ($data['status'] === 'cancelled' || $payment->status !== 'paid'), 409, 'Pesanan Midtrans harus dibayar sebelum diproses. Pembatalan pembayaran dilakukan oleh pemesan; pembayaran berhasil memerlukan refund.');
            $shipping = $data['status'] === 'shipped_sandbox';
            $sameShipment = ! $shipping || ($order->carrier === $data['carrier'] && $order->tracking_number === $data['tracking_number'] && $order->provider_tracking_id === ($data['provider_tracking_id'] ?? $order->provider_tracking_id));
            if ($order->status === $data['status'] && $sameShipment) {
                return $order;
            }
            if ($order->fulfillment === 'delivery') {
                abort_unless(isset($data['revision']), 422, 'Muat ulang pesanan untuk mendapatkan revision pengiriman.');
            }
            if (isset($data['revision'])) {
                abort_unless(hash_equals($this->revision($order), $data['revision']), 409, 'Pesanan berubah. Muat ulang sebelum menyimpan.');
            }
            $transitions = [
                'reserved_sandbox' => ['processing_sandbox', 'cancelled'],
                'processing_sandbox' => [$order->fulfillment === 'delivery' ? 'shipped_sandbox' : 'ready_sandbox', 'cancelled'],
                'ready_sandbox' => ['completed_sandbox', 'cancelled'],
                'shipped_sandbox' => ['shipped_sandbox', 'completed_sandbox'],
            ];
            abort_unless(in_array($data['status'], $transitions[$order->status] ?? [], true), 409, 'Perubahan status tidak sesuai urutan pesanan.');
            abort_if($shipping && $order->fulfillment !== 'delivery', 409, 'Resi hanya tersedia untuk pesanan dengan pengiriman.');
            $previousStatus = $order->status;
            if ($data['status'] === 'cancelled') {
                $product = UmkmProduct::withTrashed()->whereKey($order->umkm_product_id)->lockForUpdate()->firstOrFail();
                $product->increment('stock', $order->quantity);
            }
            $changes = ['status' => $data['status']];
            if ($shipping) {
                $changes += ['carrier' => $data['carrier'], 'tracking_number' => $data['tracking_number'], 'provider_tracking_id' => $data['provider_tracking_id'] ?? null, 'tracking_snapshot' => null, 'tracking_checked_at' => null, 'shipped_at' => $order->shipped_at ?? now()];
            }
            $order->update($changes);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => $shipping ? 'umkm.shipment_updated' : 'umkm.order_status_updated', 'auditable_type' => UmkmOrder::class, 'auditable_id' => $order->id, 'metadata' => ['previous_status' => $previousStatus, 'status' => $order->status, 'mode' => 'sandbox']]);

            return $order;
        }, 3);

        return response()->json(['data' => $this->present($order)])->header('Cache-Control', 'private, no-store');
    }

    private function managedPartnerIds(User $user): array
    {
        if (ServiceManagementAccess::admin($user)) {
            return Partner::query()->pluck('id')->all();
        }
        $ids = ServiceManagementAccess::partnerIds($user);
        abort_if($ids === [], 403, 'Akses pengelolaan pesanan UMKM hanya untuk admin terverifikasi atau pemilik/manajer mitra yang disetujui.');

        return $ids;
    }

    private function revision(UmkmOrder $order): string
    {
        return hash('sha256', json_encode($order->only(['status', 'carrier', 'tracking_number', 'provider_tracking_id', 'shipped_at']), JSON_THROW_ON_ERROR));
    }

    private function present(UmkmOrder $order): array
    {
        return ['order_id' => $order->public_id, 'status' => $order->status, 'payment_status' => $order->reservationPayment?->status ?? 'unpaid', 'reservation_payment' => $order->reservationPayment, 'sandbox' => true, 'product' => $order->snapshot, 'quantity' => $order->quantity, 'revision' => $this->revision($order), 'subtotal' => $order->total - $order->shipping_fee, 'shipping' => ['provider' => $order->shipping_provider, 'service' => $order->shipping_service, 'provider_tracking_id' => $order->provider_tracking_id, 'tracking_available' => filled($order->provider_tracking_id) && app(BiteshipClient::class)->configured(), 'method' => $order->fulfillment, 'fee' => $order->shipping_fee, 'address' => $order->shipping_address, 'postal_code' => $order->postal_code, 'carrier' => $order->carrier, 'tracking_number' => $order->tracking_number, 'shipped_at' => $order->shipped_at?->toIso8601String()], 'total' => $order->total, 'customer_name' => $order->customer_name, 'customer_phone' => $order->customer_phone, 'notes' => $order->notes, 'created_at' => $order->created_at->toIso8601String()];
    }
}
