<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LodgingBooking extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'room_type_id', 'check_in', 'check_out', 'total_price', 'status', 'quantity', 'guests', 'idempotency_key', 'nightly_prices'];

    protected $hidden = ['idempotency_key', 'user_id'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'completed_at' => 'datetime', 'checked_in_at' => 'datetime', 'check_in' => 'date', 'check_out' => 'date', 'total_price' => 'decimal:2', 'quantity' => 'integer', 'guests' => 'integer', 'nightly_prices' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservationPayment(): HasOne
    {
        return $this->hasOne(ReservationPayment::class, 'booking_id')->where('kind', 'lodging');
    }
}
