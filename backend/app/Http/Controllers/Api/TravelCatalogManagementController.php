<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidTourPackageException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CulinaryPlace;
use App\Models\Destination;
use App\Models\Partner;
use App\Models\Product;
use App\Models\UmkmProduct;
use App\Models\User;
use App\Services\TourPackagePublicationService;
use App\Support\ServiceManagementAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TravelCatalogManagementController extends Controller
{
    public function index(Request $request, string $kind): JsonResponse
    {
        $request->validate(['page' => 'nullable|integer|min:1']);
        $query = $kind === 'destinations' ? Destination::query() : Product::query()->where('type', 'package')->with('tourPackage.itineraryItems');
        $items = ServiceManagementAccess::scope($query, $request->user())->latest('id')->paginate(12);

        return response()->json(['data' => $items->getCollection()->map(fn ($item) => $this->present($item)), 'meta' => ['page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total(), 'partners' => ServiceManagementAccess::partners($request->user())]])->header('Cache-Control', 'private, no-store');
    }

    public function options(Request $request): JsonResponse
    {
        $data = ['partners' => ServiceManagementAccess::partners($request->user()),
            'destinations' => Destination::query()->where('publication_status', 'published')->orderBy('name')->get(['id', 'name']),
            'culinary' => CulinaryPlace::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'umkm' => UmkmProduct::query()->where('status', 'published')->orderBy('name')->get(['id', 'name', 'unit'])];

        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, string $kind, ?int $id = null): JsonResponse
    {
        $data = $request->validate($kind === 'destinations' ? $this->destinationRules() : $this->packageRules());
        $request->validate($id ? ['revision' => 'required|string|size:64'] : ['partner_id' => 'required|integer']);
        $item = DB::transaction(function () use ($request, $kind, $id, $data) {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $query = $kind === 'destinations' ? Destination::query() : Product::query()->where('type', 'package');
            $item = $id ? ServiceManagementAccess::scope($query, $request->user())->lockForUpdate()->findOrFail($id) : null;
            $partnerId = $item?->partner_id ?? ServiceManagementAccess::assignment($request->user(), $request->integer('partner_id'));
            Partner::query()->whereKey($partnerId)->where('status', 'approved')->lockForUpdate()->firstOrFail();
            if ($item) {
                abort_unless(hash_equals($this->revision($item), $request->string('revision')->toString()), 409, 'Data berubah. Muat ulang sebelum menyimpan.');
            }
            if ($kind === 'destinations') {
                $item ??= new Destination(['partner_id' => $partnerId, 'slug' => 'destinasi-'.Str::uuid()]);
                $item->fill($data)->save();
            } else {
                abort_if($item?->tourPackage?->crossVillagePackages()->exists(), 409, 'Paket lintas desa memiliki kesepakatan tersendiri; gunakan pengelolaan lintas desa.');
                $this->assertItems($data);
                $item ??= new Product(['partner_id' => $partnerId, 'slug' => 'paket-'.Str::uuid(), 'type' => 'package', 'currency' => 'IDR']);
                $firstDestination = collect($data['items'])->first(fn ($row) => ! empty($row['destination_id']));
                $item->fill(['name' => $data['name'], 'base_price' => $data['base_price'], 'destination_id' => $firstDestination['destination_id'], 'status' => $data['status']])->save();
                $package = $item->tourPackage()->updateOrCreate([], collect($data)->only(['description', 'duration_days', 'meeting_point', 'transportation', 'guide_information', 'minimum_participants', 'maximum_participants', 'inclusions', 'exclusions'])->all() + ['pricing_mode' => $item->tourPackage?->pricing_mode ?? 'per_person', 'departure_type' => $item->tourPackage?->departure_type ?? 'open', 'status' => $data['status']]);
                $package->itineraryItems()->delete();
                foreach ($data['items'] as $sequence => $row) {
                    $key = ! empty($row['destination_id']) ? 'destination_id' : (! empty($row['culinary_place_id']) ? 'culinary_place_id' : 'umkm_product_id');
                    $model = match ($key) {
                        'destination_id' => Destination::class, 'culinary_place_id' => CulinaryPlace::class, default => UmkmProduct::class
                    };
                    $name = $model::query()->findOrFail($row[$key])->name;
                    $package->itineraryItems()->create([...$row, 'title' => $name, 'sequence' => $sequence + 1]);
                }
                if ($data['status'] === 'published') {
                    try {
                        app(TourPackagePublicationService::class)->publish($package->fresh());
                    } catch (InvalidTourPackageException $exception) {
                        throw ValidationException::withMessages(['items' => $exception->getMessage()]);
                    }
                }
            }
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'travel_catalog.saved', 'auditable_type' => $item::class, 'auditable_id' => $item->id, 'metadata' => ['kind' => $kind]]);

            return $item->fresh();
        }, 3);

        return response()->json(['data' => $this->present($item)], $id ? 200 : 201)->header('Cache-Control', 'private, no-store');
    }

    private function assertItems(array $data): void
    {
        $destinations = 0;
        foreach ($data['items'] as $row) {
            $keys = array_filter(['destination_id', 'culinary_place_id', 'umkm_product_id'], fn ($key) => ! empty($row[$key]));
            if (count($keys) !== 1 || $row['day_number'] > $data['duration_days'] || ($row['included'] && ($row['additional_cost'] ?? 0) > 0)) {
                throw ValidationException::withMessages(['items' => 'Pilih satu tempat/produk per aktivitas, hari sesuai durasi, dan biaya tambahan nol untuk yang termasuk harga.']);
            }
            $key = array_values($keys)[0];
            $query = match ($key) {
                'destination_id' => Destination::query()->where('publication_status', 'published'),
                'culinary_place_id' => CulinaryPlace::query()->where('is_active', true),
                default => UmkmProduct::query()->where('status', 'published'),
            };
            if (! $query->whereKey($row[$key])->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['items' => 'Tempat atau produk sudah tidak tersedia. Muat ulang pilihan katalog.']);
            }
            $destinations += ! empty($row['destination_id']) ? 1 : 0;
            $minutes = ((int) substr($row['starts_at'], 0, 2)) * 60 + (int) substr($row['starts_at'], 3, 2);
            if ($minutes + $row['duration_minutes'] > 1440) {
                throw ValidationException::withMessages(['items' => 'Aktivitas tidak boleh melewati tengah malam. Pisahkan ke hari berikutnya.']);
            }
        }
        if ($destinations === 0) {
            throw ValidationException::withMessages(['items' => 'Paket membutuhkan minimal satu destinasi. Kuliner dan UMKM opsional.']);
        }
    }

    private function destinationRules(): array
    {
        return ['location_is_demo' => 'sometimes|boolean', 'name' => 'required|string|min:2|max:255', 'region_id' => 'required|integer|exists:regions,id', 'category_id' => 'nullable|integer|exists:categories,id', 'summary' => 'required|string|max:1000', 'description' => 'required|string|max:10000', 'address' => 'required|string|max:255', 'latitude' => 'nullable|numeric|between:-90,90|required_with:longitude', 'longitude' => 'nullable|numeric|between:-180,180|required_with:latitude', 'publication_status' => 'required|in:draft,published'];
    }

    private function packageRules(): array
    {
        return ['name' => 'required|string|min:2|max:255', 'description' => 'required|string|max:10000', 'base_price' => 'required|integer|min:1|max:1000000000', 'status' => 'required|in:draft,published', 'duration_days' => 'required|integer|min:1|max:30', 'meeting_point' => 'required|string|max:255', 'transportation' => 'nullable|string|max:2000', 'guide_information' => 'nullable|string|max:2000', 'minimum_participants' => 'required|integer|min:1|max:1000', 'maximum_participants' => 'required|integer|gte:minimum_participants|max:1000', 'inclusions' => 'present|array|max:30', 'inclusions.*' => 'required|string|max:255', 'exclusions' => 'present|array|max:30', 'exclusions.*' => 'required|string|max:255', 'items' => 'required|array|min:1|max:100', 'items.*' => 'array:destination_id,culinary_place_id,umkm_product_id,day_number,starts_at,duration_minutes,description,quantity,included,additional_cost',
            'items.*.destination_id' => ['nullable', 'integer', Rule::exists('destinations', 'id')->where('publication_status', 'published')->whereNull('deleted_at')],
            'items.*.culinary_place_id' => ['nullable', 'integer', Rule::exists('culinary_places', 'id')->where('is_active', true)],
            'items.*.umkm_product_id' => ['nullable', 'integer', Rule::exists('umkm_products', 'id')->where('status', 'published')->whereNull('deleted_at')],
            'items.*.day_number' => 'required|integer|min:1|max:30', 'items.*.starts_at' => 'required|date_format:H:i', 'items.*.duration_minutes' => 'required|integer|min:1|max:1440', 'items.*.description' => 'nullable|string|max:2000', 'items.*.quantity' => 'required|integer|min:1|max:1000', 'items.*.included' => 'required|boolean', 'items.*.additional_cost' => 'required|integer|min:0|max:1000000000'];
    }

    private function revision(Destination|Product $item): string
    {
        $item->loadMissing($item instanceof Product ? ['tourPackage.itineraryItems'] : []);

        return hash('sha256', $item->toJson());
    }

    private function present(Destination|Product $item): array
    {
        $revision = $this->revision($item);

        return [...$item->toArray(), 'revision' => $revision];
    }
}
