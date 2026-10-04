<?php

namespace App\Models;

use Database\Factories\WishlistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistItem extends Model
{
    /** @use HasFactory<WishlistItemFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'destination_id'];

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
