<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'minimum_spend',
        'maximum_discount',
        'global_quota',
        'used_quota',
        'user_quota',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'global_quota' => 'integer',
        'used_quota' => 'integer',
        'user_quota' => 'integer',
        'discount_value' => 'decimal:2',
        'minimum_spend' => 'decimal:2',
        'maximum_discount' => 'decimal:2',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
