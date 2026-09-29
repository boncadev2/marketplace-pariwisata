<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    public function recordPayment(Order $order): JournalEntry
    {
        return DB::transaction(function () use ($order) {
            $entry = JournalEntry::create([
                'order_id' => $order->id,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'description' => 'Payment received for Order ' . $order->public_id,
            ]);

            // Assume order has one item for now, as per current design
            $item = $order->items->first();
            $commissionAmount = $item ? $item->commission_amount : 0;
            $partnerLiability = $order->total - $commissionAmount;

            $gatewayAccount = LedgerAccount::firstOrCreate(
                ['code' => 'asset_payment_gateway'],
                ['name' => 'Payment Gateway', 'type' => 'asset']
            );

            $partnerAccount = LedgerAccount::firstOrCreate(
                ['code' => 'liability_partner_' . $order->partner_id],
                ['name' => 'Partner Liability ' . $order->partner_id, 'type' => 'liability', 'partner_id' => $order->partner_id]
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

    public function recordRefund(Order $order): JournalEntry
    {
        return DB::transaction(function () use ($order) {
            $entry = JournalEntry::create([
                'order_id' => $order->id,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'description' => 'Refund for Order ' . $order->public_id,
            ]);

            $item = $order->items->first();
            $commissionAmount = $item ? $item->commission_amount : 0;
            $partnerLiability = $order->total - $commissionAmount;

            $gatewayAccount = LedgerAccount::where('code', 'asset_payment_gateway')->first();
            $partnerAccount = LedgerAccount::where('code', 'liability_partner_' . $order->partner_id)->first();
            $commissionAccount = LedgerAccount::where('code', 'revenue_commission')->first();

            // Reverse the payment:
            // Credit Payment Gateway
            if ($gatewayAccount) {
                $entry->transactions()->create([
                    'ledger_account_id' => $gatewayAccount->id,
                    'type' => 'credit',
                    'amount' => $order->total,
                ]);
            }

            // Debit Partner Liability
            if ($partnerAccount && $partnerLiability > 0) {
                $entry->transactions()->create([
                    'ledger_account_id' => $partnerAccount->id,
                    'type' => 'debit',
                    'amount' => $partnerLiability,
                ]);
            }

            // Debit Commission Revenue
            if ($commissionAccount && $commissionAmount > 0) {
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
