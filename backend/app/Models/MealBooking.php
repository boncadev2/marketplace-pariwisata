<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MealBooking extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'meal_slot_id', 'quantity', 'unit_price', 'total_price', 'package_name', 'time_slot', 'status', 'idempotency_key'];

    protected $hidden = ['user_id', 'idempotency_key'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'completed_at' => 'datetime', 'quantity' => 'integer', 'unit_price' => 'decimal:2', 'total_price' => 'decimal:2', 'time_slot' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mealSlot(): BelongsTo
    {
        return $this->belongsTo(MealSlot::class);
    }

    public function reservationPayment(): HasOne
    {
        return $this->hasOne(ReservationPayment::class, 'booking_id')->where('kind', 'culinary');
    }
}
