<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class TourPackageDetailController extends Controller
{
    public function __invoke(Product $product): JsonResponse
    {
        abort_unless($product->type === 'package' && $product->status === 'published', 404);
        $package = $product->tourPackage()->with('itineraryItems')->firstOrFail();
        abort_unless($package->status === 'published', 404);

        return response()->json(['data' => ['photos' => $product->photos->toArray(), 'name' => $product->name, 'slug' => $product->slug, 'base_price' => (int) $product->base_price, 'currency' => $product->currency, 'description' => $package->description, 'duration_days' => $package->duration_days, 'meeting_point' => $package->meeting_point, 'transportation' => $package->transportation, 'guide_information' => $package->guide_information, 'minimum_participants' => $package->minimum_participants, 'maximum_participants' => $package->maximum_participants, 'pricing_mode' => $package->pricing_mode, 'inclusions' => $package->inclusions ?? [], 'exclusions' => $package->exclusions ?? [], 'items' => $package->itineraryItems->sortBy([['day_number', 'asc'], ['starts_at', 'asc'], ['sequence', 'asc']])->values()->map(fn ($item) => ['title' => $item->title, 'kind' => $item->destination_id ? 'destinasi' : ($item->culinary_place_id ? 'kuliner' : ($item->umkm_product_id ? 'umkm' : 'aktivitas')), 'day_number' => $item->day_number, 'starts_at' => substr($item->starts_at, 0, 5), 'duration_minutes' => $item->duration_minutes, 'description' => $item->description, 'quantity' => $item->quantity, 'included' => (bool) $item->included, 'additional_cost' => (int) $item->additional_cost])]])->header('Cache-Control', 'no-cache');
    }
}
