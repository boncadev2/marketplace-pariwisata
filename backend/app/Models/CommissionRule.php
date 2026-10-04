<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'product_type',
        'percentage_rate',
        'fixed_amount',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'percentage_rate' => 'decimal:2',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
