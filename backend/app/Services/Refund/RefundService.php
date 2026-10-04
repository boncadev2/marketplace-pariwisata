<?php

namespace App\Services\Refund;

use App\Models\InventoryHold;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\RefundRequest;
use App\Models\Voucher;
use App\Services\InventoryReservationService;
use App\Services\LedgerService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefundService
{
    public function __construct(
        private RefundAdapterInterface $adapter,
        private InventoryReservationService $inventory,
        private LedgerService $ledger,
    ) {}

    public function requestRefund(Order $order, string $reason): RefundRequest
    {
        return DB::transaction(function () use ($order, $reason): RefundRequest {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== 'paid') {
                throw new DomainException('Hanya pesanan berstatus dibayar yang dapat direfund.');
            }

            if (RefundRequest::query()->where('order_id', $order->id)->exists()) {
                throw new DomainException('Refund untuk pesanan ini sudah pernah diajukan.');
            }

            if (in_array($order->payout_status, ['requested', 'paid'], true)) {
                throw new DomainException('Refund saat payout berjalan memerlukan proses penyesuaian settlement.');
            }

            $redeemedVoucherExists = Voucher::query()
                ->whereHas('orderItem', fn ($query) => $query->where('order_id', $order->id))
                ->where('status', 'redeemed')
                ->exists();

            if ($redeemedVoucherExists) {
                throw new DomainException('Voucher yang sudah digunakan tidak dapat direfund.');
            }

            $refundableAmount = $this->calculateRefundableAmount($order);

            if ($refundableAmount < 1) {
                throw new DomainException('Nilai refund nol atau telah melewati batas waktu.');
            }

            return RefundRequest::create([
                'order_id' => $order->id,
                'reason' => $reason,
                'status' => 'requested',
                'refundable_amount' => $refundableAmount,
            ]);
        });
    }

    public function calculateRefundableAmount(Order $order): int
    {
        $policy = $order->policy_snapshot ?? [];

        if (($policy['is_refundable'] ?? false) !== true) {
            throw new DomainException('Pesanan tidak dapat direfund berdasarkan snapshot kebijakan.');
        }

        if (! isset($policy['refund_percentage'], $policy['refund_cutoff_hours'], $policy['visit_date'])) {
            throw new DomainException('Snapshot kebijakan refund tidak lengkap.');
        }

        $percentage = filter_var($policy['refund_percentage'], FILTER_VALIDATE_INT);
        $cutoffHours = filter_var($policy['refund_cutoff_hours'], FILTER_VALIDATE_INT);

        if ($percentage === false || $percentage < 0 || $percentage > 100 || $cutoffHours === false || $cutoffHours < 0) {
            throw new DomainException('Snapshot kebijakan refund tidak valid.');
        }

        $visitDateTime = CarbonImmutable::parse($policy['visit_date'], 'Asia/Jakarta');

        if (now()->addHours($cutoffHours)->isAfter($visitDateTime)) {
            return 0;
        }

        $paidAmount = (int) PaymentAttempt::query()
            ->where('order_id', $order->id)
            ->where('status', 'succeeded')
            ->sum('amount');
        $maximumRefund = min((int) $order->total, $paidAmount);

        return min($maximumRefund, (int) round($maximumRefund * ($percentage / 100)));
    }

    public function approve(RefundRequest $refundRequest, int $adminId, ?string $notes = null): bool
    {
        DB::transaction(function () use ($refundRequest, $adminId, $notes): void {
            $refundRequest = RefundRequest::query()->lockForUpdate()->findOrFail($refundRequest->id);

            if ($refundRequest->status !== 'requested') {
                throw new DomainException('Refund tidak lagi berstatus requested.');
            }

            $order = Order::query()->lockForUpdate()->findOrFail($refundRequest->order_id);

            if ($order->status !== 'paid' || Voucher::query()->whereHas('orderItem', fn ($query) => $query->where('order_id', $order->id))->where('status', 'redeemed')->exists()) {
                throw new DomainException('Status pesanan atau pemakaian voucher berubah setelah pengajuan refund.');
            }

            if (in_array($order->payout_status, ['requested', 'paid'], true)) {
                throw new DomainException('Refund tidak dapat diproses saat payout berjalan.');
            }

            $refundRequest->update([
                'status' => 'approved',
                'decided_by' => $adminId,
                'decision_notes' => $notes,
            ]);
        });

        // Trigger processing
        return $this->process($refundRequest);
    }

    public function reject(RefundRequest $refundRequest, int $adminId, string $notes): bool
    {
        return DB::transaction(function () use ($refundRequest, $adminId, $notes): bool {
            $refundRequest = RefundRequest::query()->lockForUpdate()->findOrFail($refundRequest->id);

            if ($refundRequest->status !== 'requested') {
                throw new DomainException('Refund tidak lagi berstatus requested.');
            }

            return $refundRequest->update([
                'status' => 'rejected',
                'decided_by' => $adminId,
                'decision_notes' => $notes,
            ]);
        });
    }

    public function process(RefundRequest $refundRequest): bool
    {
        return Cache::lock('refund-process:'.$refundRequest->id, 60)->block(5, fn () => $this->processLocked($refundRequest));
    }

    private function processLocked(RefundRequest $refundRequest): bool
    {
        $refundRequest = DB::transaction(function () use ($refundRequest): RefundRequest {
            $locked = RefundRequest::query()->lockForUpdate()->findOrFail($refundRequest->id);

            if ($locked->status === 'succeeded') {
                return $locked;
            }

            if ($locked->status !== 'approved' && ! ($locked->status === 'processing' && ($locked->provider_payload['dispatch_started'] ?? false))) {
                throw new DomainException('Refund belum disetujui atau sedang diproses.');
            }

            $locked->update(['status' => 'processing', 'failure_reason' => null]);

            return $locked->fresh();
        });

        if ($refundRequest->status === 'succeeded') {
            return true;
        }

        try {
            $result = $this->adapter->process($refundRequest);
        } catch (Throwable $exception) {
            Log::error('Refund provider failed.', ['refund_request_id' => $refundRequest->id, 'exception' => $exception::class]);
            $result = ['confirmed' => false, 'failure_reason' => 'Provider refund tidak dapat dikonfirmasi.'];
        }

        if (($result['pending'] ?? false) === true) {
            $refundRequest->update(['failure_reason' => $result['failure_reason'] ?? 'Menunggu konfirmasi provider.']);

            return false;
        }

        if (($result['confirmed'] ?? false) !== true || empty($result['provider_reference'])) {
            $refundRequest->update([
                'status' => 'failed',
                'failure_reason' => $result['failure_reason'] ?? 'Provider refund tidak memberi bukti keberhasilan.',
            ]);

            return false;
        }

        return DB::transaction(function () use ($refundRequest, $result): bool {
            $refundRequest = RefundRequest::query()->lockForUpdate()->findOrFail($refundRequest->id);

            if ($refundRequest->status === 'succeeded') {
                return true;
            }

            if ($refundRequest->status !== 'processing') {
                throw new DomainException('Status refund berubah saat diproses.');
            }

            $order = Order::query()->lockForUpdate()->findOrFail($refundRequest->order_id);
            $refundRequest->update([
                'status' => 'succeeded',
                'provider_reference' => $result['provider_reference'],
                'provider_payload' => $result['payload'] ?? [],
                'processed_at' => now(),
            ]);
            $order->update(['status' => 'refunded']);

            Voucher::query()->whereHas('orderItem', fn ($query) => $query->where('order_id', $order->id))
                ->where('status', 'active')->update(['status' => 'cancelled']);

            foreach ($order->items as $item) {
                $holdId = $item->snapshot['inventory_hold_id'] ?? null;
                $hold = $holdId ? InventoryHold::query()->find($holdId) : null;
                if ($hold) {
                    $this->inventory->releaseConfirmed($hold);
                }
            }

            $this->ledger->recordRefund($order, $refundRequest);

            return true;
        });
    }
}
