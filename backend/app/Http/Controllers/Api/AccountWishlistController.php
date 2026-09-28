<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\WishlistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountWishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = WishlistItem::query()->where('user_id', $request->user()->id)->with('destination:id,name,slug,publication_status')->orderByDesc('id')->paginate(20);

        return response()->json([
            'data' => $items->getCollection()->map(fn (WishlistItem $item) => $this->summary($item)),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['destination_slug' => ['required', 'string', 'max:255']]);
        $destination = Destination::query()->where('slug', $data['destination_slug'])->where('publication_status', 'published')->firstOrFail();
        DB::table('wishlist_items')->insertOrIgnore(['user_id' => $request->user()->id, 'destination_id' => $destination->id, 'created_at' => now(), 'updated_at' => now()]);
        $item = WishlistItem::query()->where('user_id', $request->user()->id)->where('destination_id', $destination->id)->with('destination:id,name,slug,publication_status')->firstOrFail();

        return response()->json(['data' => $this->summary($item)])->header('Cache-Control', 'private, no-store');
    }

    public function destroy(Request $request, int $itemId): JsonResponse
    {
        WishlistItem::query()->where('user_id', $request->user()->id)->findOrFail($itemId)->delete();

        return response()->json(status: 204);
    }

    private function summary(WishlistItem $item): array
    {
        $destination = $item->destination;
        $available = $destination !== null && $destination->publication_status === 'published';

        return [
            'id' => $item->id,
            'available' => $available,
            'destination' => $available ? ['name' => $destination->name, 'slug' => $destination->slug] : null,
        ];
    }
}
