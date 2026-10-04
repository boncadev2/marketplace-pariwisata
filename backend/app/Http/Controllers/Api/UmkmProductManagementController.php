<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Partner;
use App\Models\UmkmProduct;
use App\Models\User;
use App\Support\CommerceMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UmkmProductManagementController extends Controller
{
    private function partnerIds(User $user): array
    {
        if ($user->platform_role === 'super_admin') {
            return Partner::query()->where('status', 'approved')->pluck('id')->all();
        }
        $ids = $user->partnerMemberships()->where('is_active', true)->whereIn('role', ['owner', 'manager'])
            ->whereHas('partner', fn ($query) => $query->where('status', 'approved'))->pluck('partner_id')->all();
        abort_if($ids === [], 403, 'Pengelolaan produk hanya untuk pemilik atau manajer mitra yang disetujui.');

        return $ids;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['page' => 'nullable|integer|min:1']);
        $ids = $this->partnerIds($request->user());
        $items = UmkmProduct::query()->with('photo')->whereIn('partner_id', $ids)->latest('id')->paginate(12);

        return response()->json(['data' => $items->getCollection()->map(fn (UmkmProduct $item) => $this->present($item))->all(),
            'meta' => ['page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total(),
                'partners' => Partner::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name']),
                'editing_available' => CommerceMode::enabled()]])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Pengelolaan produk UMKM masih simulasi lokal.');
        $data = $this->validated($request);
        $request->validate(['partner_id' => 'required|integer|min:1']);
        $item = DB::transaction(function () use ($request, $data): UmkmProduct {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $ids = $this->partnerIds($request->user());
            $partner = Partner::query()->whereIn('id', $ids)->whereKey($request->integer('partner_id'))->where('status', 'approved')->lockForUpdate()->firstOrFail();

            $this->assertPhoto($data['photo_media_id'] ?? null, $partner->id);

            return UmkmProduct::create([...$data, 'partner_id' => $partner->id, 'slug' => 'umkm-'.Str::uuid(), 'is_demo' => ! app()->isProduction()]);
        }, 3);

        return response()->json(['data' => $this->present($item->refresh())], 201)->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        abort_unless(CommerceMode::enabled(), 503, 'Pengelolaan produk UMKM masih simulasi lokal.');
        $data = $this->validated($request);
        $request->validate(['revision' => 'required|string|size:64']);
        $item = DB::transaction(function () use ($request, $data, $slug): UmkmProduct {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $ids = $this->partnerIds($request->user());
            $item = UmkmProduct::query()->whereIn('partner_id', $ids)->where('slug', $slug)->lockForUpdate()->firstOrFail();
            Partner::query()->whereKey($item->partner_id)->where('status', 'approved')->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($this->revision($item), $request->string('revision')->toString()), 409, 'Produk atau stok berubah. Muat ulang produk sebelum menyimpan.');
            $this->assertPhoto(array_key_exists('photo_media_id', $data) ? $data['photo_media_id'] : $item->photo_media_id, $item->partner_id);
            $item->update($data);
            $item->unsetRelation('photo');

            return $item;
        }, 3);

        return response()->json(['data' => $this->present($item)])->header('Cache-Control', 'private, no-store');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['origin_postal_code' => ['sometimes', 'nullable', 'regex:/^[0-9]{5}$/'], 'weight_grams' => 'sometimes|nullable|integer|min:1|max:1000000', 'delivery_available' => 'sometimes|boolean', 'shipping_fee' => 'sometimes|integer|min:0|max:1000000', 'name' => 'required|string|min:2|max:255', 'description' => 'required|string|min:10|max:5000',
            'location' => 'required|string|min:2|max:255', 'price' => 'required|integer|min:1|max:1000000000',
            'unit' => 'required|string|min:1|max:50', 'stock' => 'required|integer|min:0|max:1000000', 'status' => 'required|in:draft,published', 'photo_media_id' => 'sometimes|nullable|integer|min:1']);
    }

    public function photo(Request $request, string $slug): StreamedResponse
    {
        $item = UmkmProduct::query()->with('photo')->whereIn('partner_id', $this->partnerIds($request->user()))->where('slug', $slug)->firstOrFail();
        abort_unless($item->photoUrl(true), 404);
        abort_unless(Storage::disk('public')->exists($item->photo->path), 404);

        return Storage::disk('public')->response($item->photo->path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function assertPhoto(?int $mediaId, int $partnerId): void
    {
        if ($mediaId === null) {
            return;
        }
        $media = Media::query()->whereKey($mediaId)->where('partner_id', $partnerId)->where('is_public', true)->where('disk', 'public')->first();
        abort_unless($media && preg_match('/^media\/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)$/D', $media->path) && Storage::disk('public')->exists($media->path), 422, 'Foto tidak tersedia atau bukan milik mitra produk.');
    }

    private function revision(UmkmProduct $item): string
    {
        return hash('sha256', json_encode($item->only(['origin_postal_code', 'weight_grams', 'delivery_available', 'shipping_fee', 'name', 'description', 'location', 'price', 'unit', 'stock', 'status', 'photo_media_id', 'updated_at']), JSON_THROW_ON_ERROR));
    }

    private function present(UmkmProduct $item): array
    {
        return [...$item->only(['origin_postal_code', 'weight_grams', 'delivery_available', 'shipping_fee', 'slug', 'partner_id', 'name', 'description', 'location', 'price', 'unit', 'stock', 'status', 'is_demo', 'photo_media_id']), 'photo_url' => $item->photoUrl(true), 'photo_alt' => $item->photo?->alt_text ?: $item->name, 'revision' => $this->revision($item)];
    }
}
