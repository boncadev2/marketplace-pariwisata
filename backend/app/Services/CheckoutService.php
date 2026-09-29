<?php

namespace App\Services;

use App\Exceptions\InventoryUnavailableException;
use App\Models\InventoryBucket;
use App\Models\Order;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CheckoutService
{
    public function create(Product $product, CarbonImmutable $visitDate, int $quantity, string $name, string $email, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($product, $visitDate, $quantity, $name, $email, $idempotencyKey): array {
            $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return [$existing, null];
            }

            $quote = app(PriceQuoteService::class)->quote($product, $visitDate, $quantity);
            $bucket = InventoryBucket::query()->where('product_id', $product->id)->whereDate('service_date', $visitDate->toDateString())->where('session_key', 'default')->first();
            if ($bucket === null) {
                throw new InventoryUnavailableException('Inventori tanggal ini belum tersedia.');
            }
            $hold = app(InventoryReservationService::class)->reserve($bucket, $quantity, CarbonImmutable::now()->addMinutes(15));
            $guestToken = Str::random(48);
            $order = Order::create(['public_id' => (string) Str::uuid(), 'partner_id' => $product->partner_id, 'idempotency_key' => $idempotencyKey, 'guest_access_hash' => Hash::make($guestToken), 'customer_name' => $name, 'customer_email' => $email, 'currency' => $quote['currency'], 'total' => $quote['total'], 'policy_snapshot' => ['visit_date' => $visitDate->toDateString()]]);
            $commission = app(\App\Services\CommissionService::class)->calculate($product, $quote['total'], $visitDate);
            $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'quantity' => $quantity, 'unit_price' => $quote['unit_price'], 'total' => $quote['total'], 'commission_rule_id' => $commission['commission_rule_id'], 'commission_amount' => $commission['commission_amount'], 'snapshot' => ['product_slug' => $product->slug, 'visit_date' => $visitDate->toDateString(), 'inventory_hold_id' => $hold->id]]);

            app(TransactionOutbox::class)->record($order, 'awaiting_payment', 'created', 'Batas pembayaran: '.$hold->expires_at.'. Simpan kode akses dari checkout.');

            return [$order, $guestToken];
        });
    }
}
