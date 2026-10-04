<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingQuote;
use App\Models\UmkmOrder;
use App\Models\UmkmProduct;
use App\Services\Shipping\BiteshipClient;
use App\Support\ServiceManagementAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UmkmShippingController extends Controller
{
    public function quote(Request $request, BiteshipClient $client): JsonResponse
    {
        $data = $request->validate(['product_slug' => 'required|string|max:255', 'quantity' => 'required|integer|min:1|max:100', 'postal_code' => ['required', 'regex:/^[0-9]{5}$/']]);
        $product = UmkmProduct::query()->where('slug', $data['product_slug'])->where('status', 'published')->where('delivery_available', true)->whereHas('partner', fn ($query) => $query->where('status', 'approved'))->firstOrFail();
        $rates = $client->rates($product, (int) $data['quantity'], $data['postal_code']);
        $quotes = collect($rates)->map(function ($rate) use ($request, $product, $data): array {
            $quote = ShippingQuote::create([...$rate, 'public_id' => Str::uuid(), 'user_id' => $request->user()->id, 'umkm_product_id' => $product->id, 'quantity' => $data['quantity'], 'postal_code' => $data['postal_code'], 'product_revision' => self::revision($product), 'expires_at' => now()->addMinutes(10)]);

            return $quote->only(['public_id', 'courier', 'service', 'fee', 'duration', 'expires_at']);
        });

        return response()->json(['data' => $quotes])->header('Cache-Control', 'private, no-store');
    }

    public function tracking(Request $request, string $publicId, BiteshipClient $client): JsonResponse
    {
        $order = UmkmOrder::query()->where('public_id', $publicId)->firstOrFail();
        abort_unless($order->user_id === $request->user()->id || ServiceManagementAccess::canManage($request->user(), $order->product?->partner_id), 404);
        abort_unless($order->fulfillment === 'delivery' && $order->tracking_number && $order->carrier && $order->provider_tracking_id, 409, 'Resi dan ID pelacakan Biteship belum dicatat penjual.');
        if (! $order->tracking_checked_at || $order->tracking_checked_at->lt(now()->subMinutes(5))) {
            $carrier = strtolower(trim($order->carrier));
            $number = $order->tracking_number;
            $snapshot = $client->tracking($carrier, $number, $order->provider_tracking_id);
            $updated = UmkmOrder::query()->whereKey($order->id)->where('carrier', $order->carrier)->where('tracking_number', $number)->where('provider_tracking_id', $order->provider_tracking_id)->update(['tracking_snapshot' => $snapshot, 'tracking_checked_at' => now()]);
            abort_unless($updated === 1, 409, 'Resi berubah. Muat ulang pesanan.');
            $order->refresh();
        }

        return response()->json(['data' => $order->tracking_snapshot, 'checked_at' => $order->tracking_checked_at])->header('Cache-Control', 'private, no-store');
    }

    public static function revision(UmkmProduct $product): string
    {
        return hash('sha256', json_encode($product->only(['price', 'weight_grams', 'origin_postal_code', 'delivery_available']), JSON_THROW_ON_ERROR));
    }
}
