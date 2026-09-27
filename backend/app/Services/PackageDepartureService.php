<?php

namespace App\Services;

use App\Exceptions\InventoryUnavailableException;
use App\Models\PackageDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PackageDepartureService
{
    public function reserveParticipant(PackageDeparture $departure, string $name): void
    {
        DB::transaction(function () use ($departure, $name): void {
            $departure = PackageDeparture::query()->lockForUpdate()->findOrFail($departure->id);
            if ($departure->status !== 'guaranteed' || CarbonImmutable::now()->greaterThanOrEqualTo($departure->cutoff_at) || $departure->confirmed >= $departure->capacity) {
                throw new InventoryUnavailableException('Keberangkatan tidak tersedia untuk instant booking.');
            }
            $departure->participants()->create(['name' => $name]);
            $departure->increment('confirmed');
        });
    }
}
