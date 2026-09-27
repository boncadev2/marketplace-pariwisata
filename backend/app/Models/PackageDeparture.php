<?php

namespace App\Models;

use Database\Factories\PackageDepartureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageDeparture extends Model
{
    /** @use HasFactory<PackageDepartureFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['local_date' => 'date', 'cutoff_at' => 'immutable_datetime', 'guaranteed_at' => 'immutable_datetime'];
    }

    public function tourPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(DepartureParticipant::class);
    }
}
