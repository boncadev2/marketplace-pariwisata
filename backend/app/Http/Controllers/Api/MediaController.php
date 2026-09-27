<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'alt_text' => ['nullable', 'string', 'max:255']]);
        $path = $data['file']->store('media', 'public');
        $media = Media::create(['disk' => 'public', 'path' => $path, 'alt_text' => $data['alt_text'] ?? null]);

        return response()->json(['data' => $media], 201);
    }
}
