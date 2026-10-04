<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealSlot extends Model
{
    use HasFactory;

    protected $fillable = ['culinary_place_id', 'time_slot', 'package_name', 'price', 'capacity', 'reserved', 'is_active'];

    protected function casts(): array
    {
        return ['time_slot' => 'datetime', 'price' => 'decimal:2', 'capacity' => 'integer', 'reserved' => 'integer', 'is_active' => 'boolean'];
    }

    public function culinaryPlace(): BelongsTo
    {
        return $this->belongsTo(CulinaryPlace::class);
    }
}
