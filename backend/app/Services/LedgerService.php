<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Order;
use App\Models\RefundRequest;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    public function recordPayment(Order $order): JournalEntry
    {
        return DB::transaction(function () use ($order): JournalEntry {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $existing = JournalEntry::query()
                ->where('reference_id', $order->id)
                ->where(function ($query): void {
                    $query->where('reference_type', 'payment')
                        ->orWhere(function ($legacy): void {
                            $legacy->where('reference_type', Order::class)
                                ->where('description', 'like', 'Payment received%');
                        });
                })
                ->first();

            if ($existing) {
                return $existing;
            }

            $entry = JournalEntry::create([
                'order_id' => $order->id,
                'reference_type' => 'payment',
                'reference_id' => $order->id,
                'description' => 'Payment received for Order '.$order->public_id,
            ]);

            $commissionAmount = min((int) $order->total, (int) $order->items()->sum('commission_amount'));
            $partnerLiability = (int) $order->total - $commissionAmount;

            $gatewayAccount = LedgerAccount::firstOrCreate(
                ['code' => 'asset_payment_gateway'],
                ['name' => 'Payment Gateway', 'type' => 'asset']
            );

            $partnerAccount = LedgerAccount::firstOrCreate(
                ['code' => 'liability_partner_'.$order->partner_id],
                ['name' => 'Partner Liability '.$order->partner_id, 'type' => 'liability', 'partner_id' => $order->partner_id]
            );

            $commissionAccount = LedgerAccount::firstOrCreate(
                ['code' => 'revenue_commission'],
                ['name' => 'Commission Revenue', 'type' => 'revenue']
            );

            // Debit Payment Gateway
            $entry->transactions()->create([
                'ledger_account_id' => $gatewayAccount->id,
                'type' => 'debit',
                'amount' => $order->total,
            ]);

            // Credit Partner Liability
            if ($partnerLiability > 0) {
                $entry->transactions()->create([
                    'ledger_account_id' => $partnerAccount->id,
                    'type' => 'credit',
                    'amount' => $partnerLiability,
                ]);
            }

            // Credit Commission Revenue
            if ($commissionAmount > 0) {
                $entry->transactions()->create([
                    'ledger_account_id' => $commissionAccount->id,
                    'type' => 'credit',
                    'amount' => $commissionAmount,
                ]);
            }

            return $entry;
        });
    }

    public function recordRefund(Order $order, RefundRequest $refundRequest): JournalEntry
    {
        return DB::transaction(function () use ($order, $refundRequest): JournalEntry {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $existing = JournalEntry::query()
                ->where('reference_type', RefundRequest::class)
                ->where('reference_id', $refundRequest->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $refundAmount = min((int) $order->total, (int) $refundRequest->refundable_amount);
            $totalCommission = min((int) $order->total, (int) $order->items()->sum('commission_amount'));
            $commissionAmount = $order->total > 0
                ? (int) round($totalCommission * ($refundAmount / (int) $order->total))
                : 0;
            $partnerLiability = $refundAmount - $commissionAmount;

            $entry = JournalEntry::create([
                'order_id' => $order->id,
                'reference_type' => RefundRequest::class,
                'reference_id' => $refundRequest->id,
                'description' => 'Refund for Order '.$order->public_id,
            ]);

            $gatewayAccount = LedgerAccount::firstOrCreate(
                ['code' => 'asset_payment_gateway'],
                ['name' => 'Payment Gateway', 'type' => 'asset']
            );
            $partnerAccount = LedgerAccount::firstOrCreate(
                ['code' => 'liability_partner_'.$order->partner_id],
                ['name' => 'Partner Liability '.$order->partner_id, 'type' => 'liability', 'partner_id' => $order->partner_id]
            );
            $commissionAccount = LedgerAccount::firstOrCreate(
                ['code' => 'revenue_commission'],
                ['name' => 'Commission Revenue', 'type' => 'revenue']
            );

            // Reverse the payment:
            // Credit Payment Gateway
            $entry->transactions()->create([
                'ledger_account_id' => $gatewayAccount->id,
                'type' => 'credit',
                'amount' => $refundAmount,
            ]);

            // Debit Partner Liability
            if ($partnerLiability > 0) {
                $entry->transactions()->create([
                    'ledger_account_id' => $partnerAccount->id,
                    'type' => 'debit',
                    'amount' => $partnerLiability,
                ]);
            }

            // Debit Commission Revenue
            if ($commissionAmount > 0) {
                $entry->transactions()->create([
                    'ledger_account_id' => $commissionAccount->id,
                    'type' => 'debit',
                    'amount' => $commissionAmount,
                ]);
            }

            return $entry;
        });
    }
}
