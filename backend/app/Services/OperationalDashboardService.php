<?php

namespace App\Services;

use App\Models\InventoryBucket;
use App\Models\JournalTransaction;
use App\Models\NotificationDelivery;
use App\Models\OperationalDispute;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PaymentAttempt;
use App\Models\PayoutItem;
use App\Models\ReconciliationEntry;
use App\Models\RefundRequest;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class OperationalDashboardService
{
    /** @return array<string, mixed> */
    public function summary(User $user, array $filters): array
    {
        $context = $this->context($user, $filters);
        $partnerId = $context['partner_id'];
        [$fromUtc, $toUtc] = $this->dateRange($filters, $context['timezone']);

        $orders = $this->ordersQuery($partnerId, $fromUtc, $toUtc, $filters);
        $orderCounts = (clone $orders)->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw("count(case when status = 'paid' then 1 end) as paid")
            ->selectRaw("count(case when status = 'pending_payment' then 1 end) as pending_payment")
            ->selectRaw("count(case when status = 'refunded' then 1 end) as refunded")
            ->selectRaw("count(case when status = 'payment_exception' then 1 end) as payment_exception")
            ->first();

        $ledger = $this->ledgerTotals($partnerId, $fromUtc, $toUtc);
        $refunds = RefundRequest::query()
            ->join('orders', 'orders.id', '=', 'refund_requests.order_id')
            ->whereBetween('refund_requests.created_at', [$fromUtc, $toUtc])
            ->whereIn('refund_requests.status', ['requested', 'approved', 'processing'])
            ->when($partnerId, fn ($query) => $query->where('orders.partner_id', $partnerId))
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(refundable_amount), 0) as amount')
            ->first();
        $payouts = PayoutItem::query()
            ->whereBetween('payout_items.created_at', [$fromUtc, $toUtc])
            ->whereIn('status', ['requested', 'approved', 'processing'])
            ->when($partnerId, fn ($query) => $query->where('partner_id', $partnerId))
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(amount), 0) as amount')
            ->first();
        $quota = InventoryBucket::query()
            ->join('products', 'products.id', '=', 'inventory_buckets.product_id')
            ->whereBetween('inventory_buckets.service_date', [
                $fromUtc->setTimezone($context['timezone'])->toDateString(),
                $toUtc->setTimezone($context['timezone'])->toDateString(),
            ])
            ->when($partnerId, fn ($query) => $query->where('products.partner_id', $partnerId))
            ->selectRaw('coalesce(sum(capacity), 0) as capacity')
            ->selectRaw('coalesce(sum(held), 0) as held')
            ->selectRaw('coalesce(sum(confirmed), 0) as confirmed')
            ->first();
        $visits = \DB::table('voucher_check_ins')
            ->join('vouchers', 'vouchers.id', '=', 'voucher_check_ins.voucher_id')
            ->join('order_items', 'order_items.id', '=', 'vouchers.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('voucher_check_ins.created_at', [$fromUtc, $toUtc])
            ->when($partnerId, fn ($query) => $query->where('orders.partner_id', $partnerId))
            ->sum('voucher_check_ins.admissions');
        $upcomingOrders = \DB::table('vouchers')
            ->join('order_items', 'order_items.id', '=', 'vouchers.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'paid')
            ->where('vouchers.status', 'active')
            ->whereBetween('vouchers.service_date', [
                now($context['timezone'])->toDateString(),
                now($context['timezone'])->addDays(30)->toDateString(),
            ])
            ->when($partnerId, fn ($query) => $query->where('orders.partner_id', $partnerId))
            ->distinct('orders.id')
            ->count('orders.id');
        $failedPayments = PaymentAttempt::query()
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->whereIn('status', ['failed', 'uncertain'])
            ->when($partnerId, fn (Builder $query) => $query->whereHas('order', fn (Builder $order) => $order->where('partner_id', $partnerId)))
            ->count();
        $openSupportTickets = SupportTicket::query()
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->whereNotIn('status', ['closed', 'resolved'])
            ->when($partnerId, fn (Builder $query) => $query->whereHas('order', fn (Builder $order) => $order->where('partner_id', $partnerId)))
            ->count();
        $openDisputes = OperationalDispute::query()
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->whereNotIn('status', ['resolved', 'closed'])
            ->when($partnerId, fn (Builder $query) => $query->whereHas('order', fn (Builder $order) => $order->where('partner_id', $partnerId)))
            ->count();
        $financialDiscrepancies = ReconciliationEntry::query()
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->whereIn('status', ['mismatched', 'not_found'])
            ->when($partnerId, fn (Builder $query) => $query->whereHas(
                'paymentAttempt.order', fn (Builder $order) => $order->where('partner_id', $partnerId)
            ))
            ->count();
        $completedPayments = (int) $orderCounts->paid + (int) $orderCounts->refunded;

        return [
            'scope' => $context,
            'period' => [
                'from' => $fromUtc->setTimezone($context['timezone'])->toDateString(),
                'to' => $toUtc->setTimezone($context['timezone'])->toDateString(),
                'timezone' => $context['timezone'],
            ],
            'sales' => [
                'gross' => (int) $ledger->received,
                'refunds' => (int) $ledger->refunded,
                'net' => (int) $ledger->received - (int) $ledger->refunded,
                'platform_revenue' => (int) $ledger->commission_credits - (int) $ledger->commission_debits,
                'partner_liability' => (int) $ledger->liability_credits - (int) $ledger->liability_debits,
            ],
            'orders' => [
                'total' => (int) $orderCounts->total,
                'paid' => (int) $orderCounts->paid,
                'pending_payment' => (int) $orderCounts->pending_payment,
                'refunded' => (int) $orderCounts->refunded,
                'payment_exception' => (int) $orderCounts->payment_exception,
                'upcoming_30_days' => (int) $upcomingOrders,
            ],
            'visits' => (int) $visits,
            'quota' => [
                'capacity' => (int) $quota->capacity,
                'held' => (int) $quota->held,
                'confirmed' => (int) $quota->confirmed,
                'available' => max(0, (int) $quota->capacity - (int) $quota->held - (int) $quota->confirmed),
            ],
            'pending_refunds' => ['count' => (int) $refunds->total, 'amount' => (int) $refunds->amount],
            'pending_payouts' => ['count' => (int) $payouts->total, 'amount' => (int) $payouts->amount],
            'pilot_monitoring' => [
                'order_to_payment_percent' => (int) $orderCounts->total > 0
                    ? round($completedPayments / (int) $orderCounts->total * 100, 1)
                    : 0.0,
                'failed_payments' => $failedPayments,
                'transaction_exceptions' => (int) $orderCounts->payment_exception,
                'open_support_tickets' => $openSupportTickets,
                'open_disputes' => $openDisputes,
                'financial_discrepancies' => $financialDiscrepancies,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function transactions(User $user, array $filters): array
    {
        $context = $this->context($user, $filters);
        [$fromUtc, $toUtc] = $this->dateRange($filters, $context['timezone']);
        $paginator = $this->ordersQuery($context['partner_id'], $fromUtc, $toUtc, $filters)
            ->select(['orders.id', 'orders.public_id', 'orders.partner_id', 'orders.status', 'orders.payout_status', 'orders.currency', 'orders.total', 'orders.created_at'])
            ->addSelect([
                'payment_status' => PaymentAttempt::query()
                    ->select('status')
                    ->whereColumn('payment_attempts.order_id', 'orders.id')
                    ->orderByDesc('payment_attempts.id')
                    ->limit(1),
            ])
            ->with(['partner:id,name', 'items:id,order_id,name,quantity,total'])
            ->orderByDesc('orders.created_at')
            ->orderByDesc('orders.id')
            ->paginate((int) ($filters['per_page'] ?? 20));

        $paginator->setCollection($paginator->getCollection()->map(
            fn (Order $order): array => $this->transactionPayload($order, $context['timezone'])
        ));

        return $this->paginatorPayload($paginator);
    }

    /** @return array<string, mixed> */
    public function transaction(User $user, Order $order, array $filters): array
    {
        $context = $this->context($user, $filters);
        abort_if($context['partner_id'] !== null && (int) $order->partner_id !== $context['partner_id'], 404);

        $order->load([
            'partner:id,name',
            'items:id,order_id,name,quantity,unit_price,total,commission_amount',
            'paymentAttempts:id,order_id,provider,status,currency,amount,created_at',
            'refundRequest:id,order_id,status,refundable_amount,created_at,processed_at',
            'payoutItems:id,order_id,payout_batch_id,amount,status,created_at',
        ]);

        return [
            ...$this->transactionPayload($order, $context['timezone']),
            'customer' => [
                'name' => Str::mask((string) $order->customer_name, '*', 1),
                'email' => $this->maskEmail((string) $order->customer_email),
            ],
            'payment_attempts' => $order->paymentAttempts->map(fn (PaymentAttempt $attempt): array => [
                'id' => $attempt->id,
                'provider' => $attempt->provider,
                'status' => $attempt->status,
                'amount' => (int) $attempt->amount,
                'currency' => $attempt->currency,
                'created_at' => $attempt->created_at?->setTimezone($context['timezone'])->toIso8601String(),
            ])->all(),
            'refund' => $order->refundRequest ? [
                'id' => $order->refundRequest->id,
                'status' => $order->refundRequest->status,
                'amount' => (int) $order->refundRequest->refundable_amount,
                'created_at' => $order->refundRequest->created_at?->setTimezone($context['timezone'])->toIso8601String(),
                'processed_at' => $order->refundRequest->processed_at?->setTimezone($context['timezone'])->toIso8601String(),
            ] : null,
            'payouts' => $order->payoutItems->map(fn (PayoutItem $item): array => [
                'id' => $item->id,
                'batch_id' => $item->payout_batch_id,
                'status' => $item->status,
                'amount' => (int) $item->amount,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function exceptions(User $user, array $filters): array
    {
        $context = $this->context($user, $filters);
        $partnerId = $context['partner_id'];
        $items = collect();

        PaymentAttempt::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes(30))
            ->when($partnerId, fn (Builder $query) => $query->whereHas('order', fn (Builder $order) => $order->where('partner_id', $partnerId)))
            ->with('order:id,public_id,partner_id')
            ->latest('created_at')->limit(25)->get()
            ->each(fn (PaymentAttempt $attempt) => $items->push($this->exceptionItem(
                'late_payment', $attempt->id, 'high', 'Pembayaran terlambat',
                "Pembayaran pesanan {$attempt->order?->public_id} masih pending lebih dari 30 menit.",
                $attempt->created_at, 'payment_attempt', $attempt->id, $attempt->order_id
            )));

        NotificationDelivery::query()
            ->where('status', 'failed')
            ->when($partnerId, fn (Builder $query) => $query->whereHas('order', fn (Builder $order) => $order->where('partner_id', $partnerId)))
            ->with('order:id,public_id,partner_id')
            ->latest('updated_at')->limit(25)->get()
            ->each(fn (NotificationDelivery $delivery) => $items->push($this->exceptionItem(
                'failed_notification', $delivery->id, 'medium', 'Notifikasi gagal',
                "Notifikasi {$delivery->type} untuk pesanan {$delivery->order?->public_id} gagal dikirim.",
                $delivery->updated_at, 'notification_delivery', $delivery->id, $delivery->order_id
            )));

        InventoryBucket::query()
            ->join('products', 'products.id', '=', 'inventory_buckets.product_id')
            ->whereRaw('inventory_buckets.held + inventory_buckets.confirmed > inventory_buckets.capacity')
            ->when($partnerId, fn ($query) => $query->where('products.partner_id', $partnerId))
            ->select(['inventory_buckets.*', 'products.name as product_name'])
            ->latest('inventory_buckets.updated_at')->limit(25)->get()
            ->each(fn (InventoryBucket $bucket) => $items->push($this->exceptionItem(
                'quota_anomaly', $bucket->id, 'critical', 'Anomali kuota',
                "Kuota {$bucket->product_name} melebihi kapasitas pada {$bucket->service_date->toDateString()}.",
                $bucket->updated_at, 'inventory_bucket', $bucket->id
            )));

        RefundRequest::query()
            ->whereIn('status', ['requested', 'approved', 'processing'])
            ->where('created_at', '<=', now()->subHours(24))
            ->when($partnerId, fn (Builder $query) => $query->whereHas('order', fn (Builder $order) => $order->where('partner_id', $partnerId)))
            ->with('order:id,public_id,partner_id')
            ->latest('created_at')->limit(25)->get()
            ->each(fn (RefundRequest $refund) => $items->push($this->exceptionItem(
                'pending_refund', $refund->id, 'high', 'Refund tertunda',
                "Refund pesanan {$refund->order?->public_id} belum selesai lebih dari 24 jam.",
                $refund->created_at, 'refund_request', $refund->id, $refund->order_id
            )));

        ReconciliationEntry::query()
            ->whereIn('status', ['mismatched', 'not_found'])
            ->when($partnerId, fn (Builder $query) => $query->whereHas(
                'paymentAttempt.order', fn (Builder $order) => $order->where('partner_id', $partnerId)
            ))
            ->with('paymentAttempt:id,order_id')
            ->latest('created_at')->limit(25)->get()
            ->each(fn (ReconciliationEntry $entry) => $items->push($this->exceptionItem(
                'reconciliation_difference', $entry->id, 'critical', 'Selisih rekonsiliasi',
                "Mutasi {$entry->payment_gateway_reference} berstatus {$entry->status}.",
                $entry->created_at, 'reconciliation_entry', $entry->id, $entry->paymentAttempt?->order_id
            )));

        $sorted = $items->sortByDesc('occurred_at')->take(100)->values();

        return [
            'data' => $sorted->all(),
            'meta' => [
                'total' => $sorted->count(),
                'counts' => $sorted->countBy('type')->all(),
            ],
        ];
    }

    /** @return LazyCollection<int, object> */
    public function exportRows(User $user, array $filters): LazyCollection
    {
        $context = $this->context($user, $filters);
        abort_unless($this->canExport($user, $context['partner_id']), 403);
        [$fromUtc, $toUtc] = $this->dateRange($filters, $context['timezone']);

        return $this->ordersQuery($context['partner_id'], $fromUtc, $toUtc, $filters)
            ->join('partners', 'partners.id', '=', 'orders.partner_id')
            ->select(['orders.id', 'orders.public_id', 'partners.name as partner_name', 'orders.status', 'orders.payout_status', 'orders.currency', 'orders.total', 'orders.created_at'])
            ->addSelect([
                'payment_status' => PaymentAttempt::query()
                    ->select('status')
                    ->whereColumn('payment_attempts.order_id', 'orders.id')
                    ->orderByDesc('payment_attempts.id')
                    ->limit(1),
            ])
            ->orderBy('orders.id')
            ->cursor();
    }

    public function sanitizeCsvCell(mixed $value): string
    {
        $cell = str_replace(["\0", "\r", "\n"], ['', ' ', ' '], (string) $value);

        return preg_match('/^[\p{Z}\s]*[=+\-@]/u', $cell) === 1 ? "'".$cell : $cell;
    }

    /** @return array<string, mixed> */
    public function context(User $user, array $filters): array
    {
        $timezone = $filters['timezone'] ?? 'Asia/Jakarta';
        $requestedPartnerId = isset($filters['partner_id']) ? (int) $filters['partner_id'] : null;

        if ($user->platform_role === 'super_admin') {
            $partners = Partner::query()->select(['id', 'name'])->orderBy('name')->get();
            if ($requestedPartnerId !== null) {
                abort_unless($partners->contains('id', $requestedPartnerId), 404);
            }

            return [
                'role' => 'super_admin',
                'partner_id' => $requestedPartnerId,
                'can_export' => true,
                'available_partners' => $partners->map->only(['id', 'name'])->values()->all(),
                'timezone' => $timezone,
            ];
        }

        $memberships = $user->partnerMemberships()
            ->where('is_active', true)
            ->with('partner:id,name')
            ->get();
        abort_if($memberships->isEmpty(), 403);
        $partnerId = $requestedPartnerId ?? (int) $memberships->first()->partner_id;
        $membership = $memberships->firstWhere('partner_id', $partnerId);
        abort_unless($membership, 404);

        return [
            'role' => 'partner_'.$membership->role,
            'partner_id' => $partnerId,
            'can_export' => in_array($membership->role, ['owner', 'manager'], true),
            'available_partners' => $memberships->map(fn ($item): array => [
                'id' => $item->partner_id,
                'name' => $item->partner->name,
            ])->values()->all(),
            'timezone' => $timezone,
        ];
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function dateRange(array $filters, string $timezone): array
    {
        $today = CarbonImmutable::now($timezone);
        $from = $filters['from'] ?? $today->subDays(29)->toDateString();
        $to = $filters['to'] ?? $today->toDateString();

        return [
            CarbonImmutable::createFromFormat('Y-m-d H:i:s', $from.' 00:00:00', $timezone)->utc(),
            CarbonImmutable::createFromFormat('Y-m-d H:i:s', $to.' 23:59:59', $timezone)->utc(),
        ];
    }

    private function ordersQuery(?int $partnerId, CarbonImmutable $fromUtc, CarbonImmutable $toUtc, array $filters): Builder
    {
        return Order::query()
            ->whereBetween('orders.created_at', [$fromUtc, $toUtc])
            ->when($partnerId, fn (Builder $query) => $query->where('orders.partner_id', $partnerId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('orders.status', $status))
            ->when($filters['q'] ?? null, fn (Builder $query, string $search) => $query->where('orders.public_id', 'like', '%'.$search.'%'));
    }

    private function ledgerTotals(?int $partnerId, CarbonImmutable $fromUtc, CarbonImmutable $toUtc): object
    {
        return JournalTransaction::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_transactions.journal_entry_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_transactions.ledger_account_id')
            ->leftJoin('orders', 'orders.id', '=', 'journal_entries.order_id')
            ->whereBetween('journal_entries.created_at', [$fromUtc, $toUtc])
            ->when($partnerId, fn ($query) => $query->where('orders.partner_id', $partnerId))
            ->selectRaw("coalesce(sum(case when ledger_accounts.code = 'asset_payment_gateway' and journal_transactions.type = 'debit' then journal_transactions.amount else 0 end), 0) as received")
            ->selectRaw("coalesce(sum(case when ledger_accounts.code = 'asset_payment_gateway' and journal_transactions.type = 'credit' then journal_transactions.amount else 0 end), 0) as refunded")
            ->selectRaw("coalesce(sum(case when ledger_accounts.code = 'revenue_commission' and journal_transactions.type = 'credit' then journal_transactions.amount else 0 end), 0) as commission_credits")
            ->selectRaw("coalesce(sum(case when ledger_accounts.code = 'revenue_commission' and journal_transactions.type = 'debit' then journal_transactions.amount else 0 end), 0) as commission_debits")
            ->selectRaw("coalesce(sum(case when ledger_accounts.type = 'liability' and journal_transactions.type = 'credit' then journal_transactions.amount else 0 end), 0) as liability_credits")
            ->selectRaw("coalesce(sum(case when ledger_accounts.type = 'liability' and journal_transactions.type = 'debit' then journal_transactions.amount else 0 end), 0) as liability_debits")
            ->first();
    }

    /** @return array<string, mixed> */
    private function transactionPayload(Order $order, string $timezone): array
    {
        return [
            'id' => $order->id,
            'order_id' => $order->public_id,
            'partner' => $order->partner ? ['id' => $order->partner->id, 'name' => $order->partner->name] : null,
            'status' => $order->status,
            'payment_status' => array_key_exists('payment_status', $order->getAttributes())
                ? $order->getAttribute('payment_status')
                : $order->paymentAttempts->sortByDesc('id')->first()?->status,
            'payout_status' => $order->payout_status,
            'currency' => $order->currency,
            'total' => (int) $order->total,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'participants' => $order->policy_snapshot['participants'] ?? [],
            'created_at' => $order->created_at?->setTimezone($timezone)->toIso8601String(),
            'items' => $order->relationLoaded('items') ? $order->items->map(fn ($item): array => [
                'name' => $item->name,
                'quantity' => (int) $item->quantity,
                'total' => (int) $item->total,
            ])->all() : [],
            'source_url' => '/api/v1/dashboard/transactions/'.$order->id,
        ];
    }

    /** @return array<string, mixed> */
    private function paginatorPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function exceptionItem(
        string $type,
        int $id,
        string $severity,
        string $title,
        string $description,
        mixed $occurredAt,
        string $sourceType,
        int $sourceId,
        ?int $orderId = null,
    ): array {
        return [
            'id' => $type.':'.$id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'occurred_at' => $occurredAt?->toIso8601String(),
            'source' => [
                'type' => $sourceType,
                'id' => $sourceId,
                'order_url' => $orderId ? '/api/v1/dashboard/transactions/'.$orderId : null,
            ],
        ];
    }

    private function canExport(User $user, ?int $partnerId): bool
    {
        if ($user->platform_role === 'super_admin') {
            return true;
        }

        return $user->partnerMemberships()
            ->where('partner_id', $partnerId)
            ->where('is_active', true)
            ->whereIn('role', ['owner', 'manager'])
            ->exists();
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::mask($local, '*', 1).'@'.$domain;
    }
}
