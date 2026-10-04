<?php

namespace App\Models;

use App\Services\PublicCatalogCache;
use Database\Factories\DestinationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Destination extends Model
{
    /** @use HasFactory<DestinationFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['location_is_demo' => 'boolean'];
    }

    public function photos(): HasMany
    {
        return $this->hasMany(TravelPhoto::class)->orderBy('id');
    }

    protected static function booted(): void
    {
        $invalidate = function (): void {
            app(PublicCatalogCache::class)->bumpDestinations();
            app(PublicCatalogCache::class)->bumpProducts();
        };

        static::saved($invalidate);
        static::deleted($invalidate);
        static::restored($invalidate);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
