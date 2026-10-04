<?php

namespace App\Models;

use Database\Factories\DataDeletionRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataDeletionRequest extends Model
{
    /** @use HasFactory<DataDeletionRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'reason',
        'processing_notes',
        'retained_records',
        'requested_at',
        'processed_at',
        'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'retained_records' => 'array',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
