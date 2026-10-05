<?php

namespace App\Models;

use App\Services\PublicCatalogCache;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
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

    public function destinations(): HasMany
    {
        return $this->hasMany(Destination::class);
    }
}
