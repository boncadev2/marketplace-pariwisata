<?php

namespace App\Models;

use Database\Factories\TourPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourPackage extends Model
{
    /** @use HasFactory<TourPackageFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['inclusions' => 'array', 'exclusions' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function itineraryItems(): HasMany
    {
        return $this->hasMany(PackageItineraryItem::class)->orderBy('day_number')->orderBy('sequence');
    }
}
