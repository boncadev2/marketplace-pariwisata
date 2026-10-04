<?php

namespace App\Services;

use App\Models\CrossVillageAgreement;
use App\Models\Partner;
use App\Models\Region;
use App\Models\TourPackage;
use Illuminate\Validation\ValidationException;

class CrossVillageAllocationService
{
    /** @return array<int, array{partner_id:int,region_id:int,share_percentage:string,partner_revenue:int}> */
    public function allocate(TourPackage $package, int $partnerRevenue): array
    {
        $shares = $package->crossVillagePackages()->orderBy('partner_id')->get();
        $partners = Partner::query()->whereIn('id', $shares->pluck('partner_id'))->get()->keyBy('id');
        $villages = Region::query()->whereIn('id', $partners->pluck('region_id'))->where('type', 'village')->pluck('id');
        $primary = $shares->where('is_primary_partner', true);
        if ($partnerRevenue < 0 || $partnerRevenue > 1_000_000_000_000 || $shares->count() < 2
            || $shares->pluck('partner_id')->unique()->count() !== $shares->count()
            || $primary->count() !== 1 || $primary->first()->partner_id !== $package->product->partner_id
            || $partners->count() !== $shares->count() || $partners->contains(fn (Partner $partner) => $partner->status !== 'approved')
            || $partners->pluck('region_id')->unique()->count() < 2
            || $partners->contains(fn (Partner $partner) => ! $villages->contains($partner->region_id))) {
            $this->invalid();
        }
        $allocations = [];
        $totalBasisPoints = 0;
        foreach ($shares as $share) {
            $percentage = (string) $share->revenue_share_percentage;
            if (! preg_match('/^\d{1,3}\.\d{2}$/', $percentage)) {
                $this->invalid();
            }
            [$whole, $fraction] = explode('.', $percentage);
            $basisPoints = ((int) $whole * 100) + (int) $fraction;
            if ($basisPoints < 1 || $basisPoints > 10000) {
                $this->invalid();
            }
            $totalBasisPoints += $basisPoints;
            $allocations[] = ['partner_id' => $share->partner_id, 'region_id' => $partners[$share->partner_id]->region_id,
                'share_percentage' => $percentage, 'partner_revenue' => intdiv($partnerRevenue * $basisPoints, 10000),
                'remainder' => ($partnerRevenue * $basisPoints) % 10000];
        }
        if ($totalBasisPoints !== 10000) {
            $this->invalid();
        }
        $remaining = $partnerRevenue - array_sum(array_column($allocations, 'partner_revenue'));
        $priority = array_keys($allocations);
        usort($priority, fn (int $left, int $right) => ($allocations[$right]['remainder'] <=> $allocations[$left]['remainder'])
            ?: ($allocations[$left]['partner_id'] <=> $allocations[$right]['partner_id']));
        foreach (array_slice($priority, 0, $remaining) as $index) {
            $allocations[$index]['partner_revenue']++;
        }
        foreach ($allocations as &$allocation) {
            unset($allocation['remainder']);
        }

        return $allocations;
    }

    public function configuration(TourPackage $package): array
    {
        $shares = $package->crossVillagePackages()->orderBy('partner_id')->get()
            ->map(fn ($share) => $share->only(['partner_id', 'revenue_share_percentage', 'is_primary_partner']))->all();
        $version = hash('sha256', json_encode([$package->id, $package->product->partner_id, (int) $package->cross_village_revision, $shares], JSON_THROW_ON_ERROR));

        $decisions = CrossVillageAgreement::query()->where('tour_package_id', $package->id)
            ->where('revision', $package->cross_village_revision)->where('configuration_version', $version)->get()->keyBy('partner_id');
        $agreements = array_map(fn ($share) => ['partner_id' => $share['partner_id'],
            'decision' => $decisions->get($share['partner_id'])?->decision ?? 'pending',
            'decided_at' => $decisions->get($share['partner_id'])?->decided_at?->toIso8601String()], $shares);

        return ['shares' => $shares, 'version' => $version, 'revision' => (int) $package->cross_village_revision,
            'agreements' => $agreements, 'all_accepted' => count($shares) >= 2 && collect($agreements)->every(fn ($agreement) => $agreement['decision'] === 'accepted'),
            'simulation_only' => true];
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['allocation' => 'Paket memerlukan mitra disetujui dari minimal dua desa, satu mitra utama pemilik produk, dan porsi positif berjumlah 100%.']);
    }
}
