<?php

namespace App\Payouts;

use App\Models\PartnerBankAccount;
use App\Models\PayoutBatch;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class IrisPayoutGateway implements PayoutGatewayInterface
{
    public function isConfigured(): bool
    {
        $apiKey = config('services.payout.iris.api_key');
        $merchantKey = config('services.payout.iris.merchant_key');

        return ! empty($apiKey) && ! empty($merchantKey);
    }

    public function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Midtrans Iris payout credentials tidak dikonfigurasi.');
        }
    }

    public function disburse(PayoutBatch $batch): array
    {
        $this->assertConfigured();

        $apiKey = (string) config('services.payout.iris.api_key');
        $baseUrl = (string) config('services.payout.iris.base_url', 'https://app.sandbox.midtrans.com/iris/api/v1');

        $payoutItems = [];
        foreach ($batch->items as $item) {
            $bankAccount = PartnerBankAccount::where('partner_id', $item->partner_id)
                ->where('is_verified', true)
                ->where('is_active', true)
                ->first();

            $payoutItems[] = [
                'beneficiary_name' => $bankAccount?->account_name ?? 'Mitra '.$item->partner_id,
                'beneficiary_account' => $bankAccount?->account_number ?? '',
                'beneficiary_bank' => strtolower($bankAccount?->bank_name ?? 'bca'),
                'beneficiary_email' => $bankAccount?->partner?->email ?? 'partner@wisatadaerah.id',
                'amount' => (string) $item->amount,
                'notes' => 'Payout Batch '.$batch->batch_number,
            ];
        }

        $payload = [
            'payouts' => $payoutItems,
        ];

        $response = Http::withBasicAuth($apiKey, '')
            ->timeout(15)
            ->withHeaders([
                'X-Idempotency-Key' => $batch->batch_number,
                'Content-Type' => 'application/json',
            ])
            ->post("{$baseUrl}/payouts", $payload);

        if (! $response->successful()) {
            return [
                'status' => 'failed',
                'provider_reference' => $batch->batch_number,
                'payload' => $response->json() ?? [],
                'failure_reason' => $response->json('message') ?? 'Gagal membuat payout pada provider Iris.',
            ];
        }

        $body = $response->json() ?? [];
        $payoutsResponse = $body['payouts'] ?? [];
        $reference = $payoutsResponse[0]['reference_no'] ?? ('IRIS-'.$batch->batch_number);

        return [
            'status' => 'processing',
            'provider_reference' => $reference,
            'payload' => $body,
            'failure_reason' => null,
        ];
    }

    public function lookup(PayoutBatch $batch): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $apiKey = (string) config('services.payout.iris.api_key');
        $baseUrl = (string) config('services.payout.iris.base_url', 'https://app.sandbox.midtrans.com/iris/api/v1');
        $reference = $batch->provider_reference ?? $batch->batch_number;

        try {
            $response = Http::withBasicAuth($apiKey, '')
                ->timeout(10)
                ->get("{$baseUrl}/payouts/{$reference}");

            if (! $response->successful()) {
                return null;
            }

            $body = $response->json() ?? [];
            $status = match (strtolower($body['status'] ?? '')) {
                'completed' => 'completed',
                'rejected', 'failed' => 'failed',
                default => 'processing',
            };

            return [
                'status' => $status,
                'provider_reference' => $reference,
                'payload' => $body,
                'failure_reason' => $status === 'failed' ? ($body['error_message'] ?? 'Ditolak provider') : null,
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
