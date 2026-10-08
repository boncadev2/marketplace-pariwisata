<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Review::query()
            ->where('status', 'approved')
            ->with(['user:id,name', 'product:id,name,slug']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        } elseif ($request->filled('product_slug')) {
            $query->whereHas('product', fn ($q) => $q->where('slug', $request->input('product_slug')));
        } elseif ($request->filled('destination_slug')) {
            $dest = Destination::where('slug', $request->input('destination_slug'))->first();
            if ($dest) {
                $query->whereHas('product', fn ($q) => $q->where('destination_id', $dest->id));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $paginated = $query->latest('id')->paginate(15);

        // Stats summary
        $statsQuery = Review::query()->where('status', 'approved');
        if ($request->filled('product_id')) {
            $statsQuery->where('product_id', $request->input('product_id'));
        } elseif ($request->filled('product_slug')) {
            $statsQuery->whereHas('product', fn ($q) => $q->where('slug', $request->input('product_slug')));
        } elseif ($request->filled('destination_slug')) {
            $dest = Destination::where('slug', $request->input('destination_slug'))->first();
            if ($dest) {
                $statsQuery->whereHas('product', fn ($q) => $q->where('destination_id', $dest->id));
            }
        }

        $averageRating = (float) ($statsQuery->avg('rating') ?: 0);
        $totalReviews = (int) $statsQuery->count();

        return response()->json([
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'summary' => [
                'average_rating' => round($averageRating, 1),
                'total_reviews' => $totalReviews,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'nullable',
            'order_id' => 'required',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $orderInput = $request->input('order_id');
        $order = is_numeric($orderInput)
            ? Order::find($orderInput)
            : Order::where('public_id', $orderInput)->first();

        abort_unless($order && $order->user_id === $request->user()->id, 404, 'Pesanan tidak ditemukan.');

        $productId = $request->input('product_id');
        if (! $productId && $order->items()->count() === 1) {
            $productId = $order->items()->first()->product_id;
        }

        abort_unless($productId && Product::where('id', $productId)->exists(), 422, 'Produk tidak valid.');

        $eligibleOrder = in_array($order->status, ['paid', 'refunded'], true)
            && $order->items()->where('product_id', $productId)->exists();

        abort_unless($eligibleOrder, 422, 'Review hanya dapat dibuat untuk produk yang dibeli.');

        $review = Review::firstOrCreate(
            ['user_id' => $request->user()->id, 'order_id' => $order->id, 'product_id' => $productId],
            [
                'rating' => (int) $request->input('rating'),
                'comment' => $request->input('comment') ?? null,
                'status' => 'approved',
            ],
        );

        return response()->json($review, $review->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Review $review): JsonResponse
    {
        abort_unless($review->status === 'approved', 404);

        return response()->json($review->load(['user:id,name', 'product:id,name,slug']));
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $query = Review::query()->with(['user:id,name,email', 'product:id,name,slug', 'order:id,order_id']);

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', $search)
                  ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $search)->orWhere('email', 'like', $search))
                  ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', $search));
            });
        }

        return response()->json($query->latest('id')->paginate(20));
    }

    public function moderate(Request $request, Review $review): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected,pending',
            'moderation_reason' => 'nullable|string|max:500',
        ]);

        $review->update([
            'status' => $data['status'],
            'moderation_reason' => $data['moderation_reason'] ?? null,
            'moderated_at' => now(),
        ]);

        return response()->json(['data' => $review->fresh()->load(['user:id,name', 'product:id,name'])]);
    }

    public function destroy(Review $review): JsonResponse
    {
        $review->delete();

        return response()->json(null, 204);
    }
}

