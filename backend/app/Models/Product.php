<?php

namespace App\Models;

use App\Services\PublicCatalogCache;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    public function photos(): HasMany
    {
        return $this->hasMany(TravelPhoto::class)->orderBy('id');
    }

    protected static function booted(): void
    {
        $invalidate = function (): void {
            app(PublicCatalogCache::class)->bumpProducts();
        };

        static::saved($invalidate);
        static::deleted($invalidate);
        static::restored($invalidate);
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(ProductPriceRule::class);
    }

    public function tourPackage(): HasOne
    {
        return $this->hasOne(TourPackage::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }
}
