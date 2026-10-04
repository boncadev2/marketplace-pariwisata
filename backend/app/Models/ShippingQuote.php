<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingQuote extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'fee' => 'integer', 'quantity' => 'integer'];
    }
}
