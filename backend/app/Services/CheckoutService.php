<?php

namespace App\Services;

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
            $guestToken = Str::random(48);
            $order = Order::create(['public_id' => (string) Str::uuid(), 'partner_id' => $product->partner_id, 'idempotency_key' => $idempotencyKey, 'guest_access_hash' => Hash::make($guestToken), 'customer_name' => $name, 'customer_email' => $email, 'currency' => $quote['currency'], 'total' => $quote['total'], 'policy_snapshot' => ['visit_date' => $visitDate->toDateString()]]);
            $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'quantity' => $quantity, 'unit_price' => $quote['unit_price'], 'total' => $quote['total'], 'snapshot' => ['product_slug' => $product->slug, 'visit_date' => $visitDate->toDateString()]]);

            return [$order, $guestToken];
        });
    }
}
