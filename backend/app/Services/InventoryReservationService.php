<?php

namespace App\Services;

use App\Exceptions\InventoryUnavailableException;
use App\Models\InventoryBucket;
use App\Models\InventoryHold;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryReservationService
{
    public function reserve(InventoryBucket $bucket, int $quantity, CarbonImmutable $expiresAt): InventoryHold
    {
        if ($quantity < 1) {
            throw new InventoryUnavailableException('Kuantitas wajib positif.');
        }

        return DB::transaction(function () use ($bucket, $quantity, $expiresAt): InventoryHold {
            $bucket = InventoryBucket::query()->lockForUpdate()->findOrFail($bucket->id);
            $this->expireBucketHolds($bucket, CarbonImmutable::now());

            if ($bucket->is_closed || $quantity > $bucket->available()) {
                throw new InventoryUnavailableException('Inventori tidak tersedia.');
            }

            $hold = $bucket->holds()->create([
                'public_id' => (string) Str::uuid(),
                'quantity' => $quantity,
                'expires_at' => $expiresAt,
            ]);
            $bucket->increment('held', $quantity);

            return $hold->fresh();
        });
    }

    public function confirm(InventoryHold $hold): InventoryHold
    {
        return DB::transaction(function () use ($hold): InventoryHold {
            $bucket = InventoryBucket::query()->lockForUpdate()->findOrFail($hold->inventory_bucket_id);
            $hold = InventoryHold::query()->lockForUpdate()->findOrFail($hold->id);

            if ($hold->state !== 'active') {
                return $hold;
            }

            $this->expireBucketHolds($bucket, CarbonImmutable::now());
            $hold->refresh();

            if ($hold->state !== 'active') {
                return $hold;
            }

            $bucket->decrement('held', $hold->quantity);
            $bucket->increment('confirmed', $hold->quantity);
            $hold->update(['state' => 'confirmed']);

            return $hold->fresh();
        });
    }

    public function release(InventoryHold $hold, string $state = 'released'): InventoryHold
    {
        return DB::transaction(function () use ($hold, $state): InventoryHold {
            $bucket = InventoryBucket::query()->lockForUpdate()->findOrFail($hold->inventory_bucket_id);
            $hold = InventoryHold::query()->lockForUpdate()->findOrFail($hold->id);

            if ($hold->state !== 'active') {
                return $hold;
            }

            $bucket->decrement('held', $hold->quantity);
            $hold->update(['state' => $state, 'released_at' => CarbonImmutable::now()]);

            return $hold->fresh();
        });
    }

    public function releaseExpired(): int
    {
        $holds = InventoryHold::query()->where('state', 'active')->where('expires_at', '<=', CarbonImmutable::now())->get();

        foreach ($holds as $hold) {
            $this->release($hold, 'expired');
        }

        return $holds->count();
    }

    private function expireBucketHolds(InventoryBucket $bucket, CarbonImmutable $now): void
    {
        $expiredHolds = $bucket->holds()->where('state', 'active')->where('expires_at', '<=', $now)->lockForUpdate()->get();

        foreach ($expiredHolds as $hold) {
            $bucket->decrement('held', $hold->quantity);
            $hold->update(['state' => 'expired', 'released_at' => $now]);
        }

        $bucket->refresh();
    }
}
