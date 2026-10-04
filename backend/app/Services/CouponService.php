<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function lockCoupon(string $code): Coupon
    {
        abort_unless(DB::transactionLevel() > 0, 500, 'Validasi kupon membutuhkan transaksi.');
        $coupon = Coupon::query()->where('code', strtoupper(trim($code)))->lockForUpdate()->first();
        if (! $coupon) {
            throw ValidationException::withMessages(['coupon_code' => 'Kupon tidak tersedia.']);
        }

        return $coupon;
    }

    /** @return array{coupon_code: string, subtotal: int, discount: int, total: int} */
    public function calculate(Coupon $coupon, User $user, int $subtotal): array
    {
        if (! $coupon->is_active || ($coupon->starts_at && $coupon->starts_at->isFuture()) || ($coupon->expires_at && $coupon->expires_at->lessThanOrEqualTo(now()))) {
            throw ValidationException::withMessages(['coupon_code' => 'Kupon belum berlaku atau sudah berakhir.']);
        }
        if ($subtotal < 1 || $subtotal > intdiv(PHP_INT_MAX, 10000)) {
            throw ValidationException::withMessages(['coupon_code' => 'Nilai pesanan tidak mendukung kupon.']);
        }
        if ($subtotal * 100 < $this->minorUnits($coupon->minimum_spend)) {
            throw ValidationException::withMessages(['coupon_code' => 'Nilai pesanan belum memenuhi minimum belanja.']);
        }
        if ($coupon->used_quota < 0 || ($coupon->global_quota !== null && $coupon->used_quota >= $coupon->global_quota)) {
            throw ValidationException::withMessages(['coupon_code' => 'Kuota kupon telah habis.']);
        }
        if ($coupon->user_quota !== null && $coupon->redemptions()->where('user_id', $user->id)->count() >= $coupon->user_quota) {
            throw ValidationException::withMessages(['coupon_code' => 'Batas penggunaan kupon akun telah tercapai.']);
        }
        $value = $this->minorUnits($coupon->discount_value);
        if ($coupon->discount_type === 'percentage' && $value > 0 && $value <= 10000) {
            $discount = intdiv($subtotal * $value, 10000);
        } elseif ($coupon->discount_type === 'fixed' && $value > 0) {
            $discount = intdiv($value, 100);
        } else {
            throw ValidationException::withMessages(['coupon_code' => 'Aturan diskon kupon tidak valid.']);
        }
        if ($coupon->maximum_discount !== null) {
            $discount = min($discount, intdiv($this->minorUnits($coupon->maximum_discount), 100));
        }
        if ($discount < 1 || $discount >= $subtotal) {
            throw ValidationException::withMessages(['coupon_code' => 'Kupon harus menghasilkan diskon positif dan total pembayaran minimal Rp1.']);
        }

        return ['coupon_code' => $coupon->code, 'subtotal' => $subtotal, 'discount' => $discount, 'total' => $subtotal - $discount];
    }

    public function record(Coupon $coupon, User $user, Order $order, int $discount): void
    {
        abort_unless(DB::transactionLevel() > 0, 500, 'Penukaran kupon membutuhkan transaksi.');
        $coupon->redemptions()->create(['user_id' => $user->id, 'order_id' => $order->id, 'discount_amount' => $discount]);
        $coupon->increment('used_quota');
    }

    private function minorUnits(string $amount): int
    {
        if (! preg_match('/^([0-9]{1,13})\.([0-9]{2})$/', $amount, $parts)) {
            throw ValidationException::withMessages(['coupon_code' => 'Aturan nilai kupon tidak valid.']);
        }

        return ((int) $parts[1] * 100) + (int) $parts[2];
    }
}
