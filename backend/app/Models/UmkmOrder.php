<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UmkmOrder extends Model
{
    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(UmkmProduct::class, 'umkm_product_id')->withTrashed();
    }

    protected function casts(): array
    {
        return ['tracking_snapshot' => 'array', 'tracking_checked_at' => 'datetime', 'snapshot' => 'array', 'quantity' => 'integer', 'total' => 'integer', 'shipping_fee' => 'integer', 'shipped_at' => 'datetime'];
    }

    public function reservationPayment(): HasOne
    {
        return $this->hasOne(ReservationPayment::class, 'booking_id')->where('kind', 'umkm');
    }
}
