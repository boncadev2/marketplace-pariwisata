<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CrossVillageAllocationService;
use App\Services\PriceQuoteService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductQuoteController extends Controller
{
    public function show(Request $request, Product $product, PriceQuoteService $priceQuoteService): JsonResponse
    {
        abort_unless($product->status === 'published', 404);

        $data = $request->validate([
            'visit_date' => ['required', 'date_format:Y-m-d'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $crossVillage = null;
        $package = $product->tourPackage;
        if ($package !== null && $package->crossVillagePackages()->exists()) {
            $configuration = app(CrossVillageAllocationService::class)->configuration($package);
            $crossVillage = ['version' => $configuration['version'], 'revision' => $configuration['revision'],
                'all_accepted' => $configuration['all_accepted'], 'available' => app()->environment(['local', 'testing']), 'simulation_only' => true];
        }

        return response()->json([
            'data' => [...$priceQuoteService->quote(
                $product,
                CarbonImmutable::parse($data['visit_date']),
                $data['quantity'],
            ), 'cross_village' => $crossVillage],
        ])->header('Cache-Control', 'no-store');
    }
}
