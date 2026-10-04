<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'partner_id',
        'status',
        'subtotal',
        'commission_amount',
        'partner_revenue',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'partner_revenue' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
