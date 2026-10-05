<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRegionController extends Controller
{
    /**
     * List all categories with destinations count.
     */
    public function categoriesIndex(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->withCount('destinations')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Store a new category.
     */
    public function categoryStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100', 'unique:categories,name'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:categories,slug'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($data['slug'])) {
            $baseSlug = Str::slug($data['name']);
            $slug = $baseSlug;
            $counter = 1;
            while (Category::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter++;
            }
            $data['slug'] = $slug;
        }

        $data['is_active'] = $data['is_active'] ?? true;

        $category = Category::create($data);

        return response()->json([
            'message' => 'Kategori berhasil ditambahkan.',
            'data' => $category,
        ], 201)->header('Cache-Control', 'private, no-store');
    }

    /**
     * Update an existing category.
     */
    public function categoryUpdate(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100', Rule::unique('categories', 'name')->ignore($category->id)],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('categories', 'slug')->ignore($category->id)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($data['slug'])) {
            $baseSlug = Str::slug($data['name']);
            $slug = $baseSlug;
            $counter = 1;
            while (Category::query()->where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                $slug = $baseSlug.'-'.$counter++;
            }
            $data['slug'] = $slug;
        }

        $category->update($data);

        return response()->json([
            'message' => 'Kategori berhasil diperbarui.',
            'data' => $category->fresh()->loadCount('destinations'),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Delete a category.
     */
    public function categoryDestroy(Request $request, Category $category): JsonResponse
    {
        if ($category->destinations()->exists()) {
            return response()->json([
                'message' => 'Kategori ini tidak dapat dihapus karena masih digunakan oleh destinasi wisata.',
            ], 409);
        }

        $category->delete();

        return response()->json([
            'message' => 'Kategori berhasil dihapus.',
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * List all regions with parent and counts.
     */
    public function regionsIndex(Request $request): JsonResponse
    {
        $regions = Region::query()
            ->with('parent:id,name,code,type')
            ->withCount(['children', 'destinations'])
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $regions])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Store a new region.
     */
    public function regionStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'code' => ['required', 'string', 'min:2', 'max:64', 'unique:regions,code'],
            'type' => ['required', 'string', 'in:regency,city,district,village'],
            'parent_id' => ['nullable', 'integer', 'exists:regions,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $data['is_active'] = $data['is_active'] ?? true;

        $region = Region::create($data);

        return response()->json([
            'message' => 'Wilayah berhasil ditambahkan.',
            'data' => $region->load('parent:id,name,code,type'),
        ], 201)->header('Cache-Control', 'private, no-store');
    }

    /**
     * Update an existing region.
     */
    public function regionUpdate(Request $request, Region $region): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'code' => ['required', 'string', 'min:2', 'max:64', Rule::unique('regions', 'code')->ignore($region->id)],
            'type' => ['required', 'string', 'in:regency,city,district,village'],
            'parent_id' => ['nullable', 'integer', 'exists:regions,id', Rule::notIn([$region->id])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));

        $region->update($data);

        return response()->json([
            'message' => 'Wilayah berhasil diperbarui.',
            'data' => $region->fresh()->load('parent:id,name,code,type')->loadCount(['children', 'destinations']),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Delete a region.
     */
    public function regionDestroy(Request $request, Region $region): JsonResponse
    {
        if ($region->children()->exists()) {
            return response()->json([
                'message' => 'Wilayah ini tidak dapat dihapus karena memiliki wilayah bawahan (sub-region).',
            ], 409);
        }

        if ($region->destinations()->exists()) {
            return response()->json([
                'message' => 'Wilayah ini tidak dapat dihapus karena masih digunakan oleh destinasi wisata.',
            ], 409);
        }

        $region->delete();

        return response()->json([
            'message' => 'Wilayah berhasil dihapus.',
        ])->header('Cache-Control', 'private, no-store');
    }
}
