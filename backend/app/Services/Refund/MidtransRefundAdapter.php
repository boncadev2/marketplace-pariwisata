<?php

namespace App\Services\Refund;

use App\Models\PaymentAttempt;
use App\Models\RefundRequest;
use App\Payments\MidtransSandboxGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Throwable;

class MidtransRefundAdapter implements RefundAdapterInterface
{
    public function __construct(private MidtransSandboxGateway $gateway) {}

    public function process(RefundRequest $refundRequest): array
    {
        $attempt = PaymentAttempt::query()->where('order_id', $refundRequest->order_id)->where('status', 'succeeded')->where('provider', $this->gateway->provider())->first();
        if (! $attempt || ! $this->gateway->isConfigured() || ! config('services.midtrans.refunds_enabled') || $refundRequest->refundable_amount < 1 || $refundRequest->refundable_amount > $attempt->amount || $attempt->currency !== 'IDR') {
            return ['confirmed' => false, 'failure_reason' => 'Refund Midtrans belum dikonfigurasi atau pembayaran tidak sesuai.'];
        }

        return $this->processRecord($refundRequest, (string) $attempt->provider_reference, (int) $attempt->amount, 'wisata-refund-'.$refundRequest->id);
    }

    public function processRecord(Model $refundRequest, string $reference, int $paidAmount, string $key): array
    {
        if (! $this->gateway->isConfigured() || ! config('services.midtrans.refunds_enabled') || $refundRequest->refundable_amount < 1 || $refundRequest->refundable_amount > $paidAmount) {
            return ['confirmed' => false, 'failure_reason' => 'Refund Midtrans belum dikonfigurasi atau nominal tidak sesuai.'];
        }
        try {
            $actual = $this->gateway->status($reference);
            if (! $actual || ($actual['order_id'] ?? null) !== $reference || ! in_array((string) ($actual['gross_amount'] ?? ''), [(string) $paidAmount, $paidAmount.'.00'], true) || ($actual['currency'] ?? 'IDR') !== 'IDR') {
                return ['confirmed' => false, 'failure_reason' => 'Transaksi refund tidak cocok dengan pembayaran.'];
            }
            $refund = collect($actual['refunds'] ?? [])->first(fn ($row) => ($row['refund_key'] ?? null) === $key);
            if ($refund) {
                $refundRequest->update(['provider_payload' => ['provider' => $this->gateway->provider(), 'refund_key' => $key, 'dispatch_started' => true]]);
                $confirmed = (string) ($refund['refund_amount'] ?? '') === (string) $refundRequest->refundable_amount || (string) ($refund['refund_amount'] ?? '') === $refundRequest->refundable_amount.'.00';
                $confirmed = $confirmed && ($refund['refund_method'] ?? null) === 'online' && ! empty($refund['bank_confirmed_at']) && ! empty($refund['refund_chargeback_id']);

                return ['confirmed' => $confirmed, 'pending' => ! $confirmed, 'provider_reference' => (string) ($refund['refund_chargeback_id'] ?? $key), 'payload' => ['provider' => $this->gateway->provider(), 'refund_key' => $key, 'dispatch_started' => true], 'failure_reason' => 'Refund menunggu konfirmasi bank atau pemeriksaan nominal.'];
            }
            if (($refundRequest->provider_payload['dispatch_started'] ?? false) === true) {
                return ['confirmed' => false, 'pending' => true, 'failure_reason' => 'Permintaan refund sudah dikirim; menunggu bukti provider.'];
            }
            if (($actual['transaction_status'] ?? null) !== 'settlement') {
                return ['confirmed' => false, 'failure_reason' => 'Refund membutuhkan transaksi settlement; metode pembayaran harus mendukung refund Midtrans.'];
            }
            $refundRequest->update(['provider_payload' => ['provider' => $this->gateway->provider(), 'refund_key' => $key, 'dispatch_started' => true]]);
            Http::withBasicAuth($this->gateway->serverKey(), '')->acceptJson()->asJson()->connectTimeout(3)->timeout(10)->withoutRedirecting()
                ->post($this->gateway->apiBase().'/v2/'.rawurlencode($reference).'/refund', ['refund_key' => $key, 'amount' => (int) $refundRequest->refundable_amount, 'reason' => $refundRequest->reason]);

            return ['confirmed' => false, 'pending' => true, 'failure_reason' => 'Refund diajukan; konfirmasi dilakukan melalui status resmi Midtrans.'];
        } catch (Throwable) {
            return ['confirmed' => false, 'pending' => (bool) ($refundRequest->fresh()->provider_payload['dispatch_started'] ?? false), 'failure_reason' => 'Status refund provider belum dapat dipastikan.'];
        }
    }
}
