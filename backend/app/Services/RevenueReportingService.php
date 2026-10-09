<?php

namespace App\Services;

use App\Models\JournalTransaction;
use App\Models\LedgerAccount;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class RevenueReportingService
{
    public function getMetrics(?int $partnerId = null, ?string $startDate = null, ?string $endDate = null): array
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
            $receivedQuery = JournalTransaction::where('ledger_account_id', $gatewayAccount->id)
                ->where('type', 'debit');
            $refundQuery = JournalTransaction::where('ledger_account_id', $gatewayAccount->id)
                ->where('type', 'credit');

            if ($startDate) {
                $receivedQuery->where('created_at', '>=', $startDate);
                $refundQuery->where('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $receivedQuery->where('created_at', '<=', $endDate);
                $refundQuery->where('created_at', '<=', $endDate);
            }

            $receivedPayments = (int) $receivedQuery->sum('amount');
            $refunds = (int) $refundQuery->sum('amount');
        }

        // Platform Revenue
        $platformRevenue = 0;
        if ($commissionAccount && ! $partnerId) {
            $revCreditQuery = JournalTransaction::where('ledger_account_id', $commissionAccount->id)
                ->where('type', 'credit');
            $revDebitQuery = JournalTransaction::where('ledger_account_id', $commissionAccount->id)
                ->where('type', 'debit');

            if ($startDate) {
                $revCreditQuery->where('created_at', '>=', $startDate);
                $revDebitQuery->where('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $revCreditQuery->where('created_at', '<=', $endDate);
                $revDebitQuery->where('created_at', '<=', $endDate);
            }

            $revenueCredits = (int) $revCreditQuery->sum('amount');
            $revenueDebits = (int) $revDebitQuery->sum('amount');
            $platformRevenue = max(0, $revenueCredits - $revenueDebits);
        }

        // Funds Ready for Payout (Partner Liability Balance)
        $payoutCredits = (int) JournalTransaction::whereIn('ledger_account_id', $partnerAccountIds)
            ->where('type', 'credit')
            ->sum('amount');
        $payoutDebits = (int) JournalTransaction::whereIn('ledger_account_id', $partnerAccountIds)
            ->where('type', 'debit')
            ->sum('amount');

        $fundsReadyForPayout = max(0, $payoutCredits - $payoutDebits);

        return [
            'gross_booking_value' => $receivedPayments,
            'received_payments' => $receivedPayments,
            'refunds' => $refunds,
            'platform_revenue' => $platformRevenue,
            'funds_ready_for_payout' => $fundsReadyForPayout,
            'net_volume' => max(0, $receivedPayments - $refunds),
        ];
    }

    public function getMonthlyTrends(?int $partnerId = null, int $months = 6): array
    {
        $gatewayAccount = LedgerAccount::where('code', 'asset_payment_gateway')->first();
        $commissionAccount = LedgerAccount::where('code', 'revenue_commission')->first();

        $trends = [];
        $indonesianMonths = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();
            $monthKey = $date->format('Y-m');
            $label = ($indonesianMonths[(int) $date->format('n')] ?? $date->format('M')).' '.$date->format('Y');

            $gbv = 0;
            $revenue = 0;
            $refunds = 0;

            if ($gatewayAccount) {
                $gbv = (int) JournalTransaction::where('ledger_account_id', $gatewayAccount->id)
                    ->where('type', 'debit')
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->sum('amount');

                $refunds = (int) JournalTransaction::where('ledger_account_id', $gatewayAccount->id)
                    ->where('type', 'credit')
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->sum('amount');
            }

            if ($commissionAccount && ! $partnerId) {
                $revCredit = (int) JournalTransaction::where('ledger_account_id', $commissionAccount->id)
                    ->where('type', 'credit')
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->sum('amount');
                $revDebit = (int) JournalTransaction::where('ledger_account_id', $commissionAccount->id)
                    ->where('type', 'debit')
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->sum('amount');
                $revenue = max(0, $revCredit - $revDebit);
            }

            $trends[] = [
                'period' => $monthKey,
                'label' => $label,
                'gross_booking_value' => $gbv,
                'platform_revenue' => $revenue,
                'refunds' => $refunds,
            ];
        }

        return $trends;
    }

    public function getCategoryBreakdown(?int $partnerId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->whereIn('orders.status', ['paid', 'completed']);

        if ($partnerId) {
            $query->where('orders.partner_id', $partnerId);
        }
        if ($startDate) {
            $query->where('orders.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('orders.created_at', '<=', $endDate);
        }

        $items = $query->select(
            'products.type as product_type',
            DB::raw('SUM(order_items.total) as total_amount'),
            DB::raw('COUNT(order_items.id) as item_count')
        )->groupBy('products.type')->get();

        $labels = [
            'package' => 'Paket Wisata',
            'ticket' => 'Tiket & Destinasi',
            'lodging' => 'Penginapan & Homestay',
            'culinary' => 'Wisata Kuliner',
            'umkm' => 'Produk UMKM',
        ];

        $totalAll = $items->sum('total_amount');

        $breakdown = [];
        foreach ($items as $item) {
            $type = $item->product_type ?: 'package';
            $breakdown[] = [
                'type' => $type,
                'label' => $labels[$type] ?? ucfirst($type),
                'total_amount' => (int) $item->total_amount,
                'item_count' => (int) $item->item_count,
                'percentage' => $totalAll > 0 ? round(($item->total_amount / $totalAll) * 100, 1) : 0,
            ];
        }

        if (empty($breakdown)) {
            $breakdown = [
                ['type' => 'package', 'label' => 'Paket Wisata', 'total_amount' => 0, 'item_count' => 0, 'percentage' => 0],
                ['type' => 'ticket', 'label' => 'Tiket & Destinasi', 'total_amount' => 0, 'item_count' => 0, 'percentage' => 0],
                ['type' => 'lodging', 'label' => 'Penginapan & Homestay', 'total_amount' => 0, 'item_count' => 0, 'percentage' => 0],
                ['type' => 'umkm', 'label' => 'Produk UMKM', 'total_amount' => 0, 'item_count' => 0, 'percentage' => 0],
            ];
        }

        return $breakdown;
    }

    public function getRecentTransactions(?int $partnerId = null, int $limit = 25): array
    {
        $query = Order::query()
            ->with(['partner', 'items.product'])
            ->orderBy('id', 'desc');

        if ($partnerId) {
            $query->where('partner_id', $partnerId);
        }

        return $query->limit($limit)->get()->map(function ($order) {
            $commission = (int) $order->items->sum('commission_amount');
            $firstItem = $order->items->first();
            $category = $firstItem?->product?->type ?: 'general';

            return [
                'id' => $order->id,
                'public_id' => $order->public_id,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'partner_name' => $order->partner?->name ?: 'Platform',
                'category' => $category,
                'total' => (int) $order->total,
                'commission_amount' => $commission,
                'partner_amount' => max(0, (int) $order->total - $commission),
                'status' => $order->status,
                'created_at' => $order->created_at?->toISOString() ?: now()->toISOString(),
            ];
        })->toArray();
    }

    public function exportCsv(?int $partnerId = null, ?string $startDate = null, ?string $endDate = null): string
    {
        $query = Order::query()
            ->with(['partner', 'items.product'])
            ->orderBy('id', 'desc');

        if ($partnerId) {
            $query->where('partner_id', $partnerId);
        }
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $orders = $query->get();

        $output = fopen('php://temp', 'r+');
        // UTF-8 BOM
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            'ID Pesanan',
            'Tanggal',
            'Nama Pelanggan',
            'Email Pelanggan',
            'Nama Mitra',
            'Total Transaksi (IDR)',
            'Komisi Platform (IDR)',
            'Hak Mitra (IDR)',
            'Status',
        ]);

        foreach ($orders as $order) {
            $commission = (int) $order->items->sum('commission_amount');
            $partnerAmount = max(0, (int) $order->total - $commission);
            fputcsv($output, [
                $order->public_id,
                $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : '-',
                $order->customer_name,
                $order->customer_email,
                $order->partner?->name ?: 'Platform',
                $order->total,
                $commission,
                $partnerAmount,
                $order->status,
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    public function getFullReport(?int $partnerId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $metrics = $this->getMetrics($partnerId, $startDate, $endDate);

        return array_merge($metrics, [
            'metrics' => $metrics,
            'monthly_trends' => $this->getMonthlyTrends($partnerId),
            'category_breakdown' => $this->getCategoryBreakdown($partnerId, $startDate, $endDate),
            'recent_transactions' => $this->getRecentTransactions($partnerId, 25),
        ]);
    }
}
