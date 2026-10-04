<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationRefund extends Model
{
    protected $guarded = [];

    protected $hidden = ['provider_payload'];

    protected function casts(): array
    {
        return ['refundable_amount' => 'integer', 'provider_payload' => 'array', 'processed_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(ReservationPayment::class, 'reservation_payment_id');
    }
}
