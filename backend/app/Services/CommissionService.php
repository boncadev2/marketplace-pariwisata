<?php

namespace App\Services;

use App\Models\CommissionRule;
use App\Models\Product;
use Carbon\CarbonImmutable;

class CommissionService
{
    /**
     * Calculates the commission amount for a given product and total price.
     * Uses documented Rupiah rounding (round to nearest whole number, half up).
     */
    public function calculate(Product $product, int $totalAmount, CarbonImmutable $date = null): array
    {
        $date = $date ?? CarbonImmutable::now();

        // Find active rule for partner + product_type
        $rule = CommissionRule::query()
            ->where(function ($query) use ($product) {
                $query->where('partner_id', $product->partner_id)
                    ->orWhereNull('partner_id');
            })
            ->where(function ($query) use ($product) {
                // Assuming Product has a category or type, for now we match null as fallback.
                // If Product has a category relationship, we'd check that.
                // Let's use null matching for general fallback.
                $query->where('product_type', 'default')->orWhereNull('product_type'); 
            })
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->where('effective_until', '>=', $date)
                    ->orWhereNull('effective_until');
            })
            ->orderBy('partner_id', 'desc') // specific partner takes precedence over null
            ->orderBy('product_type', 'desc')
            ->first();

        if (!$rule) {
            return [
                'commission_rule_id' => null,
                'commission_amount' => 0,
            ];
        }

        $percentageAmount = ($totalAmount * (float) $rule->percentage_rate) / 100;
        $totalCommission = $percentageAmount + $rule->fixed_amount;
        
        // Documented Rupiah rounding (round to nearest whole Rupiah)
        $roundedCommission = (int) round($totalCommission, 0, PHP_ROUND_HALF_UP);

        return [
            'commission_rule_id' => $rule->id,
            'commission_amount' => $roundedCommission,
        ];
    }
}
