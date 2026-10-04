<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index()
    {
        return response()->json(Review::query()
            ->where('status', 'approved')
            ->with(['user:id,name', 'product:id,name,slug'])
            ->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'order_id' => 'required|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $eligibleOrder = Order::query()
            ->whereKey($validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['paid', 'refunded'])
            ->whereHas('items', fn ($query) => $query->where('product_id', $validated['product_id']))
            ->exists();

        abort_unless($eligibleOrder, 422, 'Review hanya dapat dibuat untuk produk yang dibeli.');

        $review = Review::firstOrCreate(
            ['user_id' => $request->user()->id, 'order_id' => $validated['order_id'], 'product_id' => $validated['product_id']],
            ['rating' => $validated['rating'], 'comment' => $validated['comment'] ?? null],
        );

        return response()->json($review, $review->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Review $review)
    {
        abort_unless($review->status === 'approved', 404);

        return response()->json($review->load(['user:id,name', 'product:id,name,slug']));
    }
}
