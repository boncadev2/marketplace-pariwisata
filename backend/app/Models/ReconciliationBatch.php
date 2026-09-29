<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationBatch extends Model
{
    protected $guarded = [];

    public function entries(): HasMany
    {
        return $this->hasMany(ReconciliationEntry::class, 'batch_id');
    }
}
