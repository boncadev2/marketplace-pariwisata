<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Region;
use App\Services\PublicCatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LookupController extends Controller
{
    public function regions(Request $request, PublicCatalogCache $catalogCache): JsonResponse
    {
        $data = $request->validate(['parent_id' => ['nullable', 'integer', 'exists:regions,id'], 'type' => ['nullable', 'string', 'max:32']]);
        $cacheKey = 'public:regions:v'.$catalogCache->lookupsVersion().':'.hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $regions = Cache::remember($cacheKey, (int) config('performance.public_catalog_cache_seconds'), function () use ($data): array {
            return Region::query()
                ->where('is_active', true)
                ->when(array_key_exists('parent_id', $data), fn ($query) => $query->where('parent_id', $data['parent_id']))
                ->when(isset($data['type']), fn ($query) => $query->where('type', $data['type']))
                ->orderBy('name')
                ->get(['id', 'parent_id', 'code', 'name', 'type'])
                ->toArray();
        });

        return $this->publicResponse($request, ['data' => $regions]);
    }

    public function categories(Request $request, PublicCatalogCache $catalogCache): JsonResponse
    {
        $cacheKey = 'public:categories:v'.$catalogCache->lookupsVersion();
        $categories = Cache::remember($cacheKey, (int) config('performance.public_catalog_cache_seconds'), function (): array {
            return Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug'])->toArray();
        });

        return $this->publicResponse($request, ['data' => $categories]);
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
