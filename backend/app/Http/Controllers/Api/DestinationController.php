<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Product;
use App\Models\TravelPhoto;
use App\Services\PublicCatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DestinationController extends Controller
{
    public function index(Request $request, PublicCatalogCache $catalogCache): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $filters = [
            'q' => isset($validated['q']) ? Str::squish($validated['q']) : null,
            'region_id' => $validated['region_id'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'page' => $validated['page'] ?? 1,
            'per_page' => $validated['per_page'] ?? 20,
        ];
        $cacheKey = 'public:destinations:v'.$catalogCache->destinationsVersion().':'.hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));

        $payload = Cache::remember($cacheKey, (int) config('performance.public_catalog_cache_seconds'), function () use ($filters): array {
            $destinations = Destination::query()
                ->leftJoin('regions', 'regions.id', '=', 'destinations.region_id')
                ->leftJoin('categories', 'categories.id', '=', 'destinations.category_id')
                ->where('destinations.publication_status', 'published')
                ->when($filters['q'], fn ($query, string $queryText) => $query->where('destinations.name', 'like', '%'.$queryText.'%'))
                ->when($filters['region_id'], fn ($query, int $regionId) => $query->where('destinations.region_id', $regionId))
                ->when($filters['category_id'], fn ($query, int $categoryId) => $query->where('destinations.category_id', $categoryId))
                ->orderBy('destinations.name')
                ->orderBy('destinations.id')
                ->select([
                    'destinations.id',
                    'destinations.name',
                    'destinations.slug',
                    'destinations.publication_status',
                    'destinations.summary',
                    'destinations.region_id',
                    'destinations.category_id',
                    'regions.name as region_name',
                    'categories.name as category_name',
                ])
                ->addSelect([
                    'cover_id' => TravelPhoto::query()->select('id')->whereColumn('destination_id', 'destinations.id')->orderBy('id')->limit(1),
                    'cover_caption' => TravelPhoto::query()->select('caption')->whereColumn('destination_id', 'destinations.id')->orderBy('id')->limit(1),
                    'cover_is_illustration' => TravelPhoto::query()->select('is_illustration')->whereColumn('destination_id', 'destinations.id')->orderBy('id')->limit(1),
                ])
                ->paginate($filters['per_page'], ['*'], 'page', $filters['page']);

            return [
                'data' => $destinations->getCollection()->map(fn (Destination $destination): array => [
                    'id' => $destination->id,
                    'name' => $destination->name,
                    'slug' => $destination->slug,
                    'publication_status' => $destination->publication_status,
                    'summary' => $destination->summary,
                    'photos' => $destination->cover_id ? [['id' => (int) $destination->cover_id, 'caption' => $destination->cover_caption, 'is_illustration' => (bool) $destination->cover_is_illustration, 'url' => route('travel.photos.show', ['photo' => $destination->cover_id], false)]] : [],
                    'region' => ['id' => $destination->region_id, 'name' => $destination->region_name],
                    'category' => $destination->category_id ? ['id' => $destination->category_id, 'name' => $destination->category_name] : null,
                ])->all(),
                'meta' => [
                    'page' => $destinations->currentPage(),
                    'per_page' => $destinations->perPage(),
                    'total' => $destinations->total(),
                ],
            ];
        });

        return $this->publicResponse($request, $payload);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    public function show(Request $request, Destination $destination): JsonResponse
    {
        abort_unless($destination->publication_status === 'published', 404);
        $destination->load(['region:id,name', 'category:id,name', 'photos']);

        return $this->publicResponse($request, ['data' => [
            'id' => $destination->id,
            'name' => $destination->name,
            'slug' => $destination->slug,
            'publication_status' => $destination->publication_status,
            'summary' => $destination->summary,
            'photos' => $destination->photos->toArray(),
            'description' => $destination->description,
            'address' => $destination->address,
            'location_is_demo' => $destination->location_is_demo,
            'region' => $destination->region ? ['id' => $destination->region->id, 'name' => $destination->region->name] : null,
            'category' => $destination->category ? ['id' => $destination->category->id, 'name' => $destination->category->name] : null,
            'latitude' => $destination->latitude === null ? null : (float) $destination->latitude,
            'longitude' => $destination->longitude === null ? null : (float) $destination->longitude,
        ]]);
    }

    public function packages(Request $request, Destination $destination): JsonResponse
    {
        abort_unless($destination->publication_status === 'published', 404);
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $packages = Product::query()->with('photos')
            ->where('type', 'package')->where('status', 'published')
            ->whereHas('tourPackage', fn ($query) => $query->where('status', 'published')
                ->whereHas('itineraryItems', fn ($items) => $items->where('destination_id', $destination->id)))
            ->orderBy('name')->orderBy('id')
            ->paginate($validated['per_page'] ?? 12, ['id', 'name', 'slug', 'currency', 'base_price'], 'page', $validated['page'] ?? 1);

        return response()->json([
            'destination' => ['name' => $destination->name, 'slug' => $destination->slug],
            'data' => $packages->getCollection()->map(fn (Product $product): array => [
                'id' => $product->id, 'name' => $product->name, 'slug' => $product->slug,
                'currency' => $product->currency, 'base_price' => (int) $product->base_price,
                'destination_name' => $destination->name, 'photos' => $product->photos->toArray(),
            ])->all(),
            'meta' => ['page' => $packages->currentPage(), 'per_page' => $packages->perPage(), 'total' => $packages->total()],
        ])->header('Cache-Control', 'no-cache');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    private function publicResponse(Request $request, array $payload): JsonResponse
    {
        $response = response()->json($payload);
        $response->headers->set('Cache-Control', 'public, max-age='.(int) config('performance.public_browser_cache_seconds').', stale-while-revalidate=60');
        $response->setEtag(hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)));
        $response->isNotModified($request);

        return $response;
    }
}
