<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\TravelPhoto;
use App\Services\PublicCatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductCatalogController extends Controller
{
    public function index(Request $request, PublicCatalogCache $catalogCache): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:ticket,package,lodging,culinary'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $filters = [
            'type' => $validated['type'] ?? null,
            'page' => $validated['page'] ?? 1,
            'per_page' => $validated['per_page'] ?? 20,
        ];
        $cacheKey = 'public:products:v'.$catalogCache->productsVersion().':'.hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));

        $payload = Cache::remember($cacheKey, (int) config('performance.public_catalog_cache_seconds'), function () use ($filters): array {
            $products = Product::query()
                ->leftJoin('destinations', 'destinations.id', '=', 'products.destination_id')
                ->where('products.status', 'published')
                ->when($filters['type'], fn ($query, string $type) => $query->where('products.type', $type))
                ->orderBy('products.name')
                ->orderBy('products.id')
                ->select([
                    'products.id',
                    'products.name',
                    'products.slug',
                    'products.type',
                    'products.currency',
                    'products.base_price',
                    'destinations.name as destination_name',
                ])
                ->addSelect([
                    'cover_id' => TravelPhoto::query()->select('id')->whereColumn('product_id', 'products.id')->orderBy('id')->limit(1),
                    'cover_caption' => TravelPhoto::query()->select('caption')->whereColumn('product_id', 'products.id')->orderBy('id')->limit(1),
                    'cover_is_illustration' => TravelPhoto::query()->select('is_illustration')->whereColumn('product_id', 'products.id')->orderBy('id')->limit(1),
                ])
                ->paginate($filters['per_page'], ['*'], 'page', $filters['page']);

            return [
                'data' => $products->getCollection()->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'photos' => $product->cover_id ? [['id' => (int) $product->cover_id, 'caption' => $product->cover_caption, 'is_illustration' => (bool) $product->cover_is_illustration, 'url' => route('travel.photos.show', ['photo' => $product->cover_id], false)]] : [],
                    'type' => $product->type,
                    'currency' => $product->currency,
                    'base_price' => (int) $product->base_price,
                    'destination_name' => $product->destination_name,
                ])->all(),
                'meta' => [
                    'page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ];
        });

        $response = response()->json($payload);
        $response->headers->set('Cache-Control', 'public, max-age='.(int) config('performance.public_browser_cache_seconds').', stale-while-revalidate=60');
        $response->setEtag(hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)));
        $response->isNotModified($request);

        return $response;
    }
}
