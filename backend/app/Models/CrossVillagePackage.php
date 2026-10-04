<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrossVillagePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_package_id',
        'partner_id',
        'revenue_share_percentage',
        'is_primary_partner',
    ];

    protected $casts = [
        'revenue_share_percentage' => 'decimal:2',
        'is_primary_partner' => 'boolean',
    ];

    public function tourPackage(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
