<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UmkmProduct;
use App\Services\Shipping\BiteshipClient;
use App\Support\CommerceMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UmkmProductController extends Controller
{
    private function catalog(): Builder
    {
        return UmkmProduct::query()->with(['partner:id,name', 'photo'])->where('status', 'published')->whereHas('partner', fn (Builder $query) => $query->where('status', 'approved'));
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:50']);
        $query = $this->catalog();
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('location', 'like', '%'.$search.'%');
            });
        }
        $items = $query->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 12);

        return response()->json(['data' => $items->getCollection()->map(fn (UmkmProduct $item): array => $this->present($item))->all(), 'meta' => ['page' => $items->currentPage(), 'per_page' => $items->perPage(), 'total' => $items->total()]]);
    }

    public function show(string $slug): JsonResponse
    {
        return response()->json(['data' => $this->present($this->catalog()->where('slug', $slug)->firstOrFail())]);
    }

    public function photo(string $slug): StreamedResponse
    {
        $item = $this->catalog()->where('slug', $slug)->firstOrFail();
        abort_unless($item->photoUrl(), 404);
        abort_unless(Storage::disk('public')->exists($item->photo->path), 404);

        return Storage::disk('public')->response($item->photo->path, null, ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function present(UmkmProduct $item): array
    {
        return ['shipping_provider_available' => app(BiteshipClient::class)->configured() && filled($item->origin_postal_code) && $item->weight_grams > 0, 'delivery_available' => $item->delivery_available, 'shipping_fee' => $item->shipping_fee, 'photo_url' => $item->photoUrl(), 'photo_alt' => $item->photo?->alt_text ?: $item->name, 'id' => $item->id, 'slug' => $item->slug, 'name' => $item->name, 'description' => $item->description, 'location' => $item->location, 'price' => $item->price, 'currency' => 'IDR', 'unit' => $item->unit, 'stock' => $item->stock, 'ordering_available' => CommerceMode::enabled(), 'seller' => $item->partner->name, 'is_demo' => $item->is_demo];
    }
}
