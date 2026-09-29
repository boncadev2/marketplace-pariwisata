<?php

namespace App\Services;

use App\Models\JournalTransaction;
use App\Models\LedgerAccount;

class RevenueReportingService
{
    public function getMetrics(?int $partnerId = null): array
    {
        $gatewayAccount = LedgerAccount::where('code', 'asset_payment_gateway')->first();
        $commissionAccount = LedgerAccount::where('code', 'revenue_commission')->first();
        
        $partnerAccounts = LedgerAccount::where('type', 'liability')
            ->where('code', 'like', 'liability_partner_%');
        
        if ($partnerId) {
            $partnerAccounts->where('partner_id', $partnerId);
        }
        
        $partnerAccountIds = $partnerAccounts->pluck('id');

        // Gross Booking Value & Received Payments
        $receivedPayments = 0;
        $refunds = 0;
        if ($gatewayAccount) {
            $receivedPayments = JournalTransaction::where('ledger_account_id', $gatewayAccount->id)
                ->where('type', 'debit')
                ->sum('amount');
            
            $refunds = JournalTransaction::where('ledger_account_id', $gatewayAccount->id)
                ->where('type', 'credit')
                ->sum('amount');
        }
        
        // Platform Revenue
        $platformRevenue = 0;
        if ($commissionAccount && !$partnerId) {
            $revenueCredits = JournalTransaction::where('ledger_account_id', $commissionAccount->id)
                ->where('type', 'credit')
                ->sum('amount');
            $revenueDebits = JournalTransaction::where('ledger_account_id', $commissionAccount->id)
                ->where('type', 'debit')
                ->sum('amount');
            $platformRevenue = $revenueCredits - $revenueDebits;
        }

        // Funds Ready for Payout (Partner Liability Balance)
        $payoutCredits = JournalTransaction::whereIn('ledger_account_id', $partnerAccountIds)
            ->where('type', 'credit')
            ->sum('amount');
        $payoutDebits = JournalTransaction::whereIn('ledger_account_id', $partnerAccountIds)
            ->where('type', 'debit')
            ->sum('amount');
        
        $fundsReadyForPayout = $payoutCredits - $payoutDebits;

        return [
            'gross_booking_value' => $receivedPayments, // Assuming GBV = all received payments including refunded ones
            'received_payments' => $receivedPayments,
            'refunds' => $refunds,
            'platform_revenue' => $platformRevenue,
            'funds_ready_for_payout' => $fundsReadyForPayout,
        ];
    }
}
