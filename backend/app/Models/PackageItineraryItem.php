<?php

namespace App\Models;

use Database\Factories\PackageItineraryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageItineraryItem extends Model
{
    /** @use HasFactory<PackageItineraryItemFactory> */
    use HasFactory;

    protected $guarded = [];

    public function tourPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class);
    }
}
