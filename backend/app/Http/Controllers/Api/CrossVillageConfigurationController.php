<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\Product;
use App\Models\TourPackage;
use App\Services\CrossVillageAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrossVillageConfigurationController extends Controller
{
    public function show(Request $request, Product $product): JsonResponse
    {
        $this->guard($product);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $package = $product->tourPackage()->firstOrFail();
        $configuration = $this->configuration($package);
        $partners = Partner::query()->join('regions', 'regions.id', '=', 'partners.region_id')
            ->where('partners.status', 'approved')->where('regions.type', 'village')
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('partners.name', 'like', '%'.$search.'%'))
            ->orderBy('partners.name')->limit(30)->get(['partners.id', 'partners.name', 'partners.region_id', 'regions.name as village_name']);

        return response()->json(['data' => [...$configuration, 'product_name' => $product->name,
            'owner_partner_id' => $product->partner_id, 'candidates' => $partners]])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, Product $product, CrossVillageAllocationService $allocations): JsonResponse
    {
        $this->guard($product);
        $data = $request->validate([
            'version' => ['required', 'string', 'size:64'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'shares' => ['required', 'array', 'min:2', 'max:20'],
            'shares.*' => ['required', 'array:partner_id,revenue_share_percentage,is_primary_partner'],
            'shares.*.partner_id' => ['required', 'integer', 'distinct', 'exists:partners,id'],
            'shares.*.revenue_share_percentage' => ['required', 'string', 'regex:/^\d{1,3}\.\d{2}$/'],
            'shares.*.is_primary_partner' => ['required', 'boolean'],
        ]);
        $result = DB::transaction(function () use ($request, $product, $data, $allocations): array {
            $currentProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $package = $currentProduct->tourPackage()->lockForUpdate()->firstOrFail();
            $before = $this->configuration($package);
            abort_unless(hash_equals($before['version'], $data['version']), 409, 'Konfigurasi berubah. Muat ulang sebelum menyimpan.');
            $partners = Partner::query()->whereIn('id', array_column($data['shares'], 'partner_id'))->orderBy('id')->lockForUpdate()->get();
            abort_unless($partners->count() === count($data['shares']), 422, 'Mitra tidak tersedia.');
            $package->increment('cross_village_revision');
            $package->crossVillagePackages()->delete();
            $package->crossVillagePackages()->createMany($data['shares']);
            $allocations->allocate($package, 0);
            $after = $this->configuration($package);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'cross_village.configuration_updated',
                'auditable_type' => TourPackage::class, 'auditable_id' => $package->id,
                'metadata' => ['reason' => $data['reason'], 'before' => $before['shares'], 'after' => $after['shares'], 'previous_revision' => $before['revision'], 'revision' => $after['revision']],
                'ip_hash' => hash('sha256', (string) $request->ip())]);

            return $after;
        }, 3);

        return response()->json(['data' => $result])->header('Cache-Control', 'private, no-store');
    }

    private function guard(Product $product): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503, 'Konfigurasi simulasi hanya tersedia di lingkungan lokal.');
        abort_unless($product->type === 'package', 404);
    }

    private function configuration(TourPackage $package): array
    {
        return app(CrossVillageAllocationService::class)->configuration($package);
    }
}
