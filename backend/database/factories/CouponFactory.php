<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Coupon> */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => strtoupper(fake()->unique()->bothify('PROMO-########')), 'name' => 'Promo Demo', 'discount_type' => 'percentage', 'discount_value' => '10.00', 'minimum_spend' => '0.00', 'maximum_discount' => null, 'global_quota' => 10, 'used_quota' => 0, 'user_quota' => 1, 'starts_at' => now()->subDay(), 'expires_at' => now()->addMonth(), 'is_active' => true];
    }
}
