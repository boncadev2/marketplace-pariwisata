<?php

namespace App\Services;

use App\Models\Product;
use Carbon\CarbonImmutable;

class PriceQuoteService
{
    /** @return array{unit_price:int,currency:string,quantity:int,total:int} */
    public function quote(Product $product, CarbonImmutable $visitDate, int $quantity): array
    {
        $rule = $product->priceRules()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_on')->orWhere('starts_on', '<=', $visitDate->toDateString()))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $visitDate->toDateString()))
            ->orderByDesc('priority')
            ->first();

        $unitPrice = $rule?->price ?? $product->base_price;

        return [
            'unit_price' => $unitPrice,
            'currency' => $product->currency,
            'quantity' => $quantity,
            'total' => $unitPrice * $quantity,
        ];
    }
}
