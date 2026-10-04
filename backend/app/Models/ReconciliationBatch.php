<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'summary' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ReconciliationEntry::class, 'batch_id')->orderBy('id');
    }
}
