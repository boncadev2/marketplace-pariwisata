<?php

namespace App\Models;

use Database\Factories\InventoryHoldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryHold extends Model
{
    /** @use HasFactory<InventoryHoldFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime'];
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(InventoryBucket::class, 'inventory_bucket_id');
    }
}
