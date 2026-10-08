<?php

namespace App\Services;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InventoryUnavailableException;
use App\Models\InventoryBucket;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CheckoutService
{
    public function create(Product $product, CarbonImmutable $visitDate, int $quantity, string $name, string $email, string $idempotencyKey, ?string $couponCode = null, ?User $user = null, ?int $expectedTotal = null, ?string $crossVillageVersion = null, ?array $participants = null, ?string $customerPhone = null): array
    {
        if ($crossVillageVersion !== null) {
            abort_unless(app()->environment(['local', 'testing']), 503, 'Checkout lintas desa hanya tersedia di sandbox lokal.');
        }

        return DB::transaction(function () use ($product, $visitDate, $quantity, $name, $email, $idempotencyKey, $couponCode, $user, $expectedTotal, $crossVillageVersion, $participants, $customerPhone): array {
            if ($couponCode !== null) {
                abort_unless(app()->environment(['local', 'testing']), 503, 'Promo hanya tersedia untuk checkout sandbox lokal.');
                abort_unless($user !== null && $user->hasVerifiedEmail(), 403, 'Kupon membutuhkan akun dengan email terverifikasi.');
                User::query()->lockForUpdate()->findOrFail($user->id);
            }
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $existing = Order::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                $existingItem = $existing->items()->first();
                $matchesOriginalRequest = $existingItem !== null
                    && (int) $existingItem->product_id === (int) $product->id
                    && (int) $existingItem->quantity === $quantity
                    && $existing->customer_name === $name
                    && Str::lower($existing->customer_email) === Str::lower($email)
                    && ($existing->policy_snapshot['visit_date'] ?? null) === $visitDate->toDateString()
                    && ($existing->policy_snapshot['coupon_code'] ?? null) === $couponCode
                    && ($existing->policy_snapshot['cross_village_version'] ?? null) === $crossVillageVersion
                    && ($user === null ? $existing->user_id === null : (int) $existing->user_id === (int) $user->id);

                if (! $matchesOriginalRequest) {
                    throw new IdempotencyConflictException('Idempotency-Key telah digunakan untuk payload checkout yang berbeda.');
                }

                return [$existing, null];
            }

            $package = $product->tourPackage()->lockForUpdate()->first();
            if ($product->type === 'package' && $package !== null) {
                abort_unless($product->status === 'published' && $package->status === 'published'
                    && $quantity >= $package->minimum_participants && $quantity <= $package->maximum_participants
                    && $visitDate->toDateString() >= CarbonImmutable::today('Asia/Jakarta')->toDateString(), 422, 'Tanggal atau jumlah peserta paket tidak valid.');
            }
            $configuration = null;
            $hasCollaboration = $package?->crossVillagePackages()->exists() ?? false;
            if ($hasCollaboration || $crossVillageVersion !== null) {
                abort_unless(app()->environment(['local', 'testing']), 503, 'Checkout lintas desa hanya tersedia di sandbox lokal.');
                abort_unless($hasCollaboration && $crossVillageVersion !== null && $product->type === 'package' && $product->currency === 'IDR', 422, 'Periksa quote paket lintas desa sebelum checkout.');
                abort_unless($product->status === 'published' && $package->status === 'published' && $package->pricing_mode === 'per_person'
                    && $quantity >= $package->minimum_participants && $quantity <= $package->maximum_participants
                    && $visitDate->greaterThanOrEqualTo(CarbonImmutable::today()), 422, 'Tanggal atau jumlah peserta paket tidak valid.');
                $configuration = app(CrossVillageAllocationService::class)->configuration($package);
                abort_unless(hash_equals($configuration['version'], $crossVillageVersion), 409, 'Porsi paket berubah. Periksa quote kembali.');
                abort_unless($configuration['all_accepted'], 409, 'Persetujuan seluruh mitra belum lengkap.');
                app(CrossVillageAllocationService::class)->allocate($package, 0);
            }

            $quote = app(PriceQuoteService::class)->quote($product, $visitDate, $quantity);
            $subtotal = $quote['total'];
            $promotion = null;
            $coupon = null;
            if ($couponCode !== null) {
                abort_unless($quote['currency'] === 'IDR', 422, 'Kupon hanya mendukung IDR.');
                $coupons = app(CouponService::class);
                $coupon = $coupons->lockCoupon($couponCode);
                $promotion = $coupons->calculate($coupon, $user, $subtotal);
                abort_unless($expectedTotal === $promotion['total'], 409, 'Harga promo berubah. Periksa harga kembali.');
                $quote['total'] = $promotion['total'];
            }
            if ($configuration !== null) {
                abort_unless($expectedTotal === $quote['total'], 409, 'Harga berubah. Periksa quote kembali.');
            }

            $bucket = InventoryBucket::query()->where('product_id', $product->id)->whereDate('service_date', $visitDate->toDateString())->where('session_key', 'default')->first();
            if ($bucket === null) {
                throw new InventoryUnavailableException('Inventori tanggal ini belum tersedia.');
            }
            $hold = app(InventoryReservationService::class)->reserve($bucket, $quantity, CarbonImmutable::now()->addMinutes(15));
            $guestToken = Str::random(48);
            $order = Order::create(['public_id' => (string) Str::uuid(), 'partner_id' => $product->partner_id, 'idempotency_key' => $idempotencyKey, 'guest_access_hash' => Hash::make($guestToken), 'customer_name' => $name, 'customer_email' => $email, 'currency' => $quote['currency'], 'total' => $quote['total'], 'user_id' => $user?->id, 'policy_snapshot' => ['visit_date' => $visitDate->toDateString(), 'coupon_code' => $couponCode, 'promotion' => $promotion, 'cross_village_version' => $crossVillageVersion, 'participants' => $participants ?: [], 'customer_phone' => $customerPhone]]);
            $commission = app(CommissionService::class)->calculate($product, $quote['total'], $visitDate);
            $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'quantity' => $quantity, 'unit_price' => $quote['unit_price'], 'total' => $quote['total'], 'commission_rule_id' => $commission['commission_rule_id'], 'commission_amount' => $commission['commission_amount'], 'snapshot' => ['product_slug' => $product->slug, 'visit_date' => $visitDate->toDateString(), 'inventory_hold_id' => $hold->id, 'subtotal' => $subtotal, 'discount' => $promotion['discount'] ?? 0]]);

            if ($configuration !== null) {
                app(CrossVillageOrderSnapshotService::class)->captureAtCheckout($order, $package, $configuration, $user);
            }

            if ($coupon !== null) {
                app(CouponService::class)->record($coupon, $user, $order, $promotion['discount']);
            }

            app(TransactionOutbox::class)->record($order, 'awaiting_payment', 'created', 'Batas pembayaran: '.$hold->expires_at.'. Simpan kode akses dari checkout.');

            return [$order, $guestToken];
        }, 3);
    }
}
