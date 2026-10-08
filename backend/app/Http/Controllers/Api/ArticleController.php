<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Article::query()->published();

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        $perPage = min(max((int) $request->input('per_page', 9), 1), 50);
        $articles = $query->orderByDesc('published_at')->paginate($perPage);

        $categories = Article::query()
            ->published()
            ->whereNotNull('category')
            ->select('category')
            ->distinct()
            ->pluck('category');

        return response()->json([
            'data' => $articles->items(),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'per_page' => $articles->perPage(),
                'total' => $articles->total(),
            ],
            'categories' => $categories,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $article = Article::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $related = Article::query()
            ->published()
            ->where('id', '!=', $article->id)
            ->when($article->category, fn ($q) => $q->where('category', $article->category))
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        if ($related->isEmpty()) {
            $related = Article::query()
                ->published()
                ->where('id', '!=', $article->id)
                ->orderByDesc('published_at')
                ->limit(3)
                ->get();
        }

        return response()->json([
            'data' => $article,
            'related' => $related,
        ]);
    }
}
