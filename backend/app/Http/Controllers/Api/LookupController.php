<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function regions(Request $request): JsonResponse
    {
        $data = $request->validate(['parent_id' => ['nullable', 'integer', 'exists:regions,id'], 'type' => ['nullable', 'string', 'max:32']]);
        $regions = Region::query()->where('is_active', true)->when(array_key_exists('parent_id', $data), fn ($query) => $query->where('parent_id', $data['parent_id']))->when(isset($data['type']), fn ($query) => $query->where('type', $data['type']))->orderBy('name')->get(['id', 'parent_id', 'code', 'name', 'type']);

        return response()->json(['data' => $regions]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug'])]);
    }
}
