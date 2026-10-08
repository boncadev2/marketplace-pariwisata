<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;
use RuntimeException;

class CouponDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Coupon demo data may only be seeded locally or in tests.');
        }

        Coupon::query()->firstOrCreate(['code' => 'DEMO10'], [
            'name' => 'Diskon 10% simulasi — bukan promo nyata',
            'description' => 'Hanya checkout sandbox; tidak boleh digunakan untuk transaksi nyata.',
            'discount_type' => 'percentage',
            'discount_value' => '10.00',
            'minimum_spend' => '10000.00',
            'maximum_discount' => '25000.00',
            'global_quota' => 20,
            'used_quota' => 0,
            'user_quota' => 2,
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ]);
    }
}
