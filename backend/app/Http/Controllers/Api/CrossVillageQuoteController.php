<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CommissionService;
use App\Services\CrossVillageAllocationService;
use App\Services\PriceQuoteService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrossVillageQuoteController extends Controller
{
    public function __invoke(Request $request, Product $product, PriceQuoteService $prices, CommissionService $commissions, CrossVillageAllocationService $allocations): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 503, 'Simulasi hanya tersedia di lingkungan lokal.');
        abort_unless($product->status === 'published' && $product->type === 'package' && $product->currency === 'IDR', 404);
        $data = $request->validate(['visit_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100']]);
        $result = DB::transaction(function () use ($product, $data, $prices, $commissions, $allocations): array {
            $package = $product->tourPackage()->lockForUpdate()->firstOrFail();
            abort_unless($package->status === 'published', 404);
            abort_unless($package->pricing_mode === 'per_person', 422, 'Simulasi mendukung harga per peserta.');
            abort_if($data['quantity'] < $package->minimum_participants || $data['quantity'] > $package->maximum_participants, 422, 'Jumlah peserta di luar batas paket.');
            $date = CarbonImmutable::parse($data['visit_date']);
            $quote = $prices->quote($product, $date, $data['quantity']);
            $commission = $commissions->calculate($product, $quote['total'], $date);
            abort_if($commission['commission_amount'] < 0 || $commission['commission_amount'] > $quote['total'], 422, 'Komisi tidak valid.');
            $revenue = $quote['total'] - $commission['commission_amount'];

            return [...$quote, ...$commission, 'partner_revenue' => $revenue,
                'allocations' => $allocations->allocate($package, $revenue), 'simulation_only' => true];
        });

        return response()->json(['data' => $result])->header('Cache-Control', 'no-store');
    }
}
