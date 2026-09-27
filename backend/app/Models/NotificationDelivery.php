<?php

namespace App\Models;

use Database\Factories\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['recipient', 'snapshot'];

    protected function casts(): array
    {
        return ['recipient' => 'encrypted', 'snapshot' => 'encrypted:array', 'delivery_log' => 'array', 'available_at' => 'datetime', 'claimed_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
