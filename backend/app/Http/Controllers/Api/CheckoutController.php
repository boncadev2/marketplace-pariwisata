<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CheckoutService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function store(Request $request, CheckoutService $checkoutService): JsonResponse
    {
        $data = $request->validate(['product_slug' => ['required', 'string'], 'visit_date' => ['required', 'date_format:Y-m-d'], 'quantity' => ['required', 'integer', 'min:1', 'max:100'], 'customer_name' => ['required', 'string', 'max:120'], 'customer_email' => ['required', 'email:rfc', 'max:255']]);
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && strlen($key) >= 16, 422, 'Idempotency-Key wajib diisi.');
        $product = Product::query()->where('slug', $data['product_slug'])->where('status', 'published')->firstOrFail();
        [$order, $guestToken] = $checkoutService->create($product, CarbonImmutable::parse($data['visit_date']), $data['quantity'], $data['customer_name'], $data['customer_email'], $key);

        return response()->json(['data' => ['order_id' => $order->public_id, 'status' => $order->status, 'total' => $order->total, 'currency' => $order->currency, 'guest_access_token' => $guestToken]], $guestToken ? 201 : 200);
    }
}
