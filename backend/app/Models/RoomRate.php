<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomRate extends Model
{
    use HasFactory;

    protected $fillable = ['room_type_id', 'date', 'price'];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
