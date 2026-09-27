<?php

namespace App\Models;

use Database\Factories\DepartureParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartureParticipant extends Model
{
    /** @use HasFactory<DepartureParticipantFactory> */
    use HasFactory;

    protected $guarded = [];

    public function departure(): BelongsTo
    {
        return $this->belongsTo(PackageDeparture::class, 'package_departure_id');
    }
}
