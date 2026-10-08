<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Payouts\PayoutGatewayManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PayoutService
{
    public function __construct(
        private PayoutGatewayManager $gatewayManager,
        private LedgerService $ledger,
    ) {}

    /**
     * Process a payout batch through the configured gateway adapter.
     *
     * @return array{status: string, batch: PayoutBatch, message?: string}
     */
    public function process(PayoutBatch $batch, ?User $actor = null): array
    {
        return Cache::lock('payout-process:'.$batch->id, 60)->block(5, function () use ($batch, $actor): array {
            $batch = PayoutBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($batch->status === 'paid') {
                return [
                    'status' => 'paid',
                    'batch' => $batch->load('items'),
                    'message' => 'Batch sudah dibayarkan sebelumnya.',
                ];
            }

            abort_unless(
                in_array($batch->status, ['approved', 'processing'], true),
                409,
                'Batch belum disetujui atau sedang dalam status lain.'
            );

            $gateway = $this->gatewayManager->gateway();

            // T13: Lookup before retry if already in processing or has provider reference
            if ($batch->status === 'processing' || ! empty($batch->provider_reference)) {
                $lookupResult = $gateway->lookup($batch);

                if ($lookupResult !== null) {
                    return $this->applyProviderResult($batch, $lookupResult, $actor);
                }
            }

            // Mark processing before external call
            $batch->update([
                'status' => 'processing',
                'failure_reason' => null,
            ]);

            try {
                $disburseResult = $gateway->disburse($batch);
            } catch (Throwable $e) {
                Log::warning('Payout disburse threw exception, attempting lookup before retry.', [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);

                // Immediately try lookup to verify if the provider received the transaction
                try {
                    $recoveredResult = $gateway->lookup($batch);
                } catch (Throwable) {
                    $recoveredResult = null;
                }

                if ($recoveredResult !== null) {
                    return $this->applyProviderResult($batch, $recoveredResult, $actor);
                }

                // Keep batch in processing status with warning so retry performs lookup first
                $batch->update([
                    'status' => 'processing',
                    'failure_reason' => 'Timeout provider saat eksekusi payout; lookup diperlukan sebelum retry.',
                ]);

                return [
                    'status' => 'uncertain',
                    'batch' => $batch->fresh()->load('items'),
                    'message' => 'Timeout provider saat eksekusi payout; lookup diperlukan sebelum retry.',
                ];
            }

            return $this->applyProviderResult($batch, $disburseResult, $actor);
        });
    }

    /**
     * Apply the result from the payout provider atomically.
     *
     * @param  array{status: string, provider_reference?: string, payload?: array<string, mixed>, failure_reason?: string|null}  $result
     * @return array{status: string, batch: PayoutBatch}
     */
    private function applyProviderResult(PayoutBatch $batch, array $result, ?User $actor = null): array
    {
        return DB::transaction(function () use ($batch, $result, $actor): array {
            $batch = PayoutBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($batch->status === 'paid') {
                return [
                    'status' => 'paid',
                    'batch' => $batch->load('items'),
                ];
            }

            $providerStatus = $result['status'] ?? 'processing';
            $providerReference = $result['provider_reference'] ?? $batch->provider_reference;
            $payload = $result['payload'] ?? [];
            $failureReason = $result['failure_reason'] ?? null;

            if ($providerStatus === 'completed') {
                $batch->update([
                    'status' => 'paid',
                    'provider_reference' => $providerReference,
                    'provider_payload' => $payload,
                    'failure_reason' => null,
                    'completed_at' => now(),
                ]);

                $batch->items()->update([
                    'status' => 'paid',
                    'provider_reference' => $providerReference,
                ]);

                $orderIds = $batch->items()->pluck('order_id')->all();
                Order::whereIn('id', $orderIds)->update(['payout_status' => 'paid']);

                $this->ledger->recordPayout($batch);

                $this->audit($batch, 'payout_batch.paid', $actor, [
                    'provider_reference' => $providerReference,
                    'amount' => $batch->total_amount,
                ]);

                return [
                    'status' => 'paid',
                    'batch' => $batch->fresh()->load('items'),
                ];
            }

            if ($providerStatus === 'failed') {
                $batch->update([
                    'status' => 'failed',
                    'provider_reference' => $providerReference,
                    'provider_payload' => $payload,
                    'failure_reason' => $failureReason ?? 'Ditolak oleh provider payout.',
                ]);

                $batch->items()->update(['status' => 'failed']);

                $orderIds = $batch->items()->pluck('order_id')->all();
                Order::whereIn('id', $orderIds)->update(['payout_status' => 'eligible']);

                $this->audit($batch, 'payout_batch.failed', $actor, [
                    'failure_reason' => $failureReason,
                ]);

                return [
                    'status' => 'failed',
                    'batch' => $batch->fresh()->load('items'),
                ];
            }

            // Still processing
            $batch->update([
                'status' => 'processing',
                'provider_reference' => $providerReference,
                'provider_payload' => $payload,
                'failure_reason' => null,
                'processed_at' => now(),
            ]);

            $batch->items()->update([
                'status' => 'processing',
                'provider_reference' => $providerReference,
            ]);

            $this->audit($batch, 'payout_batch.processing', $actor, [
                'provider_reference' => $providerReference,
            ]);

            return [
                'status' => 'processing',
                'batch' => $batch->fresh()->load('items'),
            ];
        });
    }

    /**
     * Complete payout batch with verified transfer proof.
     *
     * @param  array{proof_reference?: string, reference?: string, notes?: string}  $proofData
     */
    public function complete(PayoutBatch $batch, array $proofData, ?User $actor = null): PayoutBatch
    {
        return DB::transaction(function () use ($batch, $proofData, $actor): PayoutBatch {
            $batch = PayoutBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($batch->status === 'paid') {
                return $batch->load('items');
            }

            abort_unless(
                in_array($batch->status, ['approved', 'processing'], true),
                409,
                'Batch harus berstatus approved atau processing untuk diselesaikan.'
            );

            $proofReference = $proofData['proof_reference'] ?? $proofData['reference'] ?? null;
            abort_unless(! empty($proofReference), 422, 'Nomor referensi bukti transfer wajib disertakan.');

            $batch->update([
                'status' => 'paid',
                'provider_reference' => $proofReference,
                'provider_payload' => array_merge($batch->provider_payload ?? [], ['proof_data' => $proofData]),
                'notes' => $proofData['notes'] ?? $batch->notes,
                'failure_reason' => null,
                'completed_at' => now(),
            ]);

            $batch->items()->update([
                'status' => 'paid',
                'provider_reference' => $proofReference,
            ]);

            $orderIds = $batch->items()->pluck('order_id')->all();
            Order::whereIn('id', $orderIds)->update(['payout_status' => 'paid']);

            $this->ledger->recordPayout($batch);

            $this->audit($batch, 'payout_batch.completed_manually', $actor, [
                'proof_reference' => $proofReference,
                'amount' => $batch->total_amount,
            ]);

            return $batch->fresh()->load('items');
        });
    }

    private function audit(PayoutBatch $batch, string $action, ?User $actor, array $extra = []): void
    {
        if ($actor === null) {
            return;
        }

        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => PayoutBatch::class,
            'auditable_id' => $batch->id,
            'metadata' => array_merge([
                'status' => $batch->status,
                'batch_number' => $batch->batch_number,
                'item_count' => $batch->items()->count(),
            ], $extra),
            'ip_hash' => hash_hmac('sha256', request()->ip() ?? '127.0.0.1', (string) config('app.key')),
        ]);
    }
}
