<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomInventory extends Model
{
    use HasFactory;

    protected $fillable = ['room_type_id', 'date', 'stock'];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
