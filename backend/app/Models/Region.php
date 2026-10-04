<?php

namespace App\Models;

use App\Services\PublicCatalogCache;
use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        $invalidate = function (): void {
            app(PublicCatalogCache::class)->bumpLookups();
            app(PublicCatalogCache::class)->bumpDestinations();
        };

        static::saved($invalidate);
        static::deleted($invalidate);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(Destination::class);
    }
}
