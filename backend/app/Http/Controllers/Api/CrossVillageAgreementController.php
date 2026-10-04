<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CrossVillageAgreement;
use App\Models\PartnerMember;
use App\Models\Product;
use App\Models\TourPackage;
use App\Services\CrossVillageAllocationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrossVillageAgreementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->guard($request);
        $memberships = $this->memberships($request)->join('partners', 'partners.id', '=', 'partner_members.partner_id')
            ->get(['partners.id', 'partners.name']);
        $products = Product::query()->where('type', 'package')->whereHas('tourPackage.crossVillagePackages',
            fn ($query) => $query->whereIn('partner_id', $memberships->pluck('id')))
            ->orderBy('id')->paginate(20, ['id', 'name', 'slug']);

        return response()->json(['data' => $products->items(), 'memberships' => $memberships,
            'meta' => ['current_page' => $products->currentPage(), 'last_page' => $products->lastPage()]])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, Product $product, CrossVillageAllocationService $allocations): JsonResponse
    {
        $this->guard($request);
        $package = $product->tourPackage()->firstOrFail();
        $isMember = $package->crossVillagePackages()->whereIn('partner_id', $this->memberships($request)->pluck('partner_id'))->exists();
        abort_unless($isMember || $request->user()->platform_role === 'super_admin', 404);

        return response()->json(['data' => [...$allocations->configuration($package), 'product_name' => $product->name]])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Product $product, CrossVillageAllocationService $allocations): JsonResponse
    {
        $this->guard($request);
        $data = $request->validate(['partner_id' => ['required', 'integer'], 'version' => ['required', 'string', 'size:64'],
            'decision' => ['required', 'in:accepted,rejected'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $result = DB::transaction(function () use ($request, $product, $data, $allocations): array {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            abort_unless($lockedProduct->type === 'package', 404);
            $package = $lockedProduct->tourPackage()->lockForUpdate()->firstOrFail();
            abort_unless($this->memberships($request)->where('partner_id', $data['partner_id'])->lockForUpdate()->first() !== null, 404);
            abort_unless($package->crossVillagePackages()->where('partner_id', $data['partner_id'])->exists(), 404);
            $configuration = $allocations->configuration($package);
            abort_unless(hash_equals($configuration['version'], $data['version']), 409, 'Porsi berubah. Muat ulang proposal.');
            $allocations->allocate($package, 0);
            $existing = CrossVillageAgreement::query()->where('tour_package_id', $package->id)
                ->where('revision', $package->cross_village_revision)->where('partner_id', $data['partner_id'])->first();
            if ($existing?->decision === $data['decision']) {
                return $configuration;
            }
            $agreement = CrossVillageAgreement::updateOrCreate(['tour_package_id' => $package->id, 'revision' => $package->cross_village_revision,
                'partner_id' => $data['partner_id']], ['configuration_version' => $data['version'], 'decision' => $data['decision'],
                    'reason' => $data['reason'], 'decided_by' => $request->user()->id, 'decided_at' => now()]);
            AuditLog::create(['user_id' => $request->user()->id, 'partner_id' => $data['partner_id'], 'action' => 'cross_village.agreement_decided',
                'auditable_type' => TourPackage::class, 'auditable_id' => $package->id,
                'metadata' => ['revision' => $package->cross_village_revision, 'configuration_version' => $data['version'],
                    'previous_decision' => $existing?->decision ?? 'pending', 'decision' => $agreement->decision, 'reason' => $data['reason']],
                'ip_hash' => hash('sha256', (string) $request->ip())]);

            return $allocations->configuration($package);
        }, 3);

        return response()->json(['data' => $result])->header('Cache-Control', 'private, no-store');
    }

    private function guard(Request $request): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        abort_unless($request->user()->hasVerifiedEmail(), 403, 'Verifikasi email diperlukan.');
    }

    private function memberships(Request $request): Builder
    {
        return PartnerMember::query()->where('partner_members.user_id', $request->user()->id)
            ->where('partner_members.is_active', true)->whereIn('partner_members.role', ['owner', 'manager'])
            ->whereHas('partner', fn ($query) => $query->where('status', 'approved'));
    }
}
