<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'discrepancy_types' => 'array',
            'provider_payload' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ReconciliationBatch::class, 'batch_id');
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'internal_payment_attempt_id');
    }
}
