<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryBucket;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryHoldController extends Controller
{
    public function calendar(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->status === 'published', 404);

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.now('Asia/Jakarta')->addMonths(3)->toDateString()],
        ]);

        $buckets = InventoryBucket::query()
            ->where('product_id', $product->id)
            ->whereDate('service_date', '>=', $data['from'])->whereDate('service_date', '<=', $data['to'])
            ->orderBy('service_date')
            ->orderBy('session_key')
            ->get()
            ->map(fn (InventoryBucket $bucket) => [
                'service_date' => $bucket->service_date->toDateString(),
                'session_key' => $bucket->session_key,
                'available' => $bucket->is_closed ? 0 : $bucket->available(),
                'is_closed' => $bucket->is_closed,
            ]);

        return response()
            ->json(['data' => $buckets])
            ->header('Cache-Control', 'no-store');
    }
}
