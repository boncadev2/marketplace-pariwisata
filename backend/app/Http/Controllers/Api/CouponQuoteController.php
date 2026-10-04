<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CouponService;
use App\Services\PriceQuoteService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponQuoteController extends Controller
{
    public function __invoke(Request $request, CouponService $coupons, PriceQuoteService $prices): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 503, 'Promo hanya tersedia untuk checkout sandbox lokal.');
        abort_unless($request->user()->hasVerifiedEmail(), 403, 'Verifikasi email akun sebelum memakai kupon.');
        $data = $request->validate([
            'product_slug' => ['required', 'string'], 'visit_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'], 'coupon_code' => ['required', 'string', 'max:100'],
        ]);
        $product = Product::query()->where('slug', $data['product_slug'])->where('status', 'published')->firstOrFail();
        abort_unless($product->currency === 'IDR', 422, 'Kupon hanya mendukung IDR.');
        $quote = DB::transaction(function () use ($request, $coupons, $prices, $product, $data): array {
            $coupon = $coupons->lockCoupon($data['coupon_code']);
            $price = $prices->quote($product, CarbonImmutable::parse($data['visit_date']), (int) $data['quantity']);

            return [...$price, ...$coupons->calculate($coupon, $request->user(), $price['total'])];
        }, 3);

        return response()->json(['data' => $quote])->header('Cache-Control', 'no-store');
    }
}
