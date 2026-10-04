<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationPayment extends Model
{
    protected $guarded = [];

    protected $visible = ['provider', 'reference', 'amount', 'status', 'checkout_url', 'expires_at', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'snap_token' => 'encrypted', 'expires_at' => 'datetime', 'paid_at' => 'datetime', 'last_checked_at' => 'datetime'];
    }
}
