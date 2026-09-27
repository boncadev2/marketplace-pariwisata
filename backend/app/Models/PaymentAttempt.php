<?php

namespace App\Models;

use Database\Factories\PaymentAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    /** @use HasFactory<PaymentAttemptFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['provider_payload' => 'array'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
