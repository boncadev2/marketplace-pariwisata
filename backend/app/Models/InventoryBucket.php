<?php

namespace App\Models;

use Database\Factories\InventoryBucketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBucket extends Model
{
    /** @use HasFactory<InventoryBucketFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['service_date' => 'date', 'is_closed' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(InventoryHold::class);
    }

    public function available(): int
    {
        return $this->capacity - $this->held - $this->confirmed;
    }
}
