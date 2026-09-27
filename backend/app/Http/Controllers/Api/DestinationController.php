<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $destinations = Destination::query()->where('publication_status', 'published')->when(isset($data['q']), fn ($query) => $query->where('name', 'like', '%'.$data['q'].'%'))->orderBy('name')->paginate($data['per_page'] ?? 20);

        return response()->json(['data' => $destinations->items(), 'meta' => ['page' => $destinations->currentPage(), 'per_page' => $destinations->perPage(), 'total' => $destinations->total()]]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
}
