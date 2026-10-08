<?php

namespace App\Payouts;

use App\Models\PayoutBatch;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class SandboxPayoutGateway implements PayoutGatewayInterface
{
    private static bool $simulateTimeout = false;

    private static bool $simulateLookupFailure = false;

    private static bool $simulateFailure = false;

    private static ?string $forcedStatus = null;

    public static function simulateTimeout(bool $simulate = true): void
    {
        self::$simulateTimeout = $simulate;
    }

    public static function simulateLookupFailure(bool $simulate = true): void
    {
        self::$simulateLookupFailure = $simulate;
    }

    public static function simulateFailure(bool $simulate = true): void
    {
        self::$simulateFailure = $simulate;
    }

    public static function forceStatus(?string $status): void
    {
        self::$forcedStatus = $status;
    }

    public static function resetSimulation(): void
    {
        self::$simulateTimeout = false;
        self::$simulateLookupFailure = false;
        self::$simulateFailure = false;
        self::$forcedStatus = null;
    }

    public function disburse(PayoutBatch $batch): array
    {
        $reference = 'SANDBOX-PO-'.$batch->batch_number.'-'.substr(md5($batch->id.$batch->total_amount), 0, 8);

        $result = [
            'status' => self::$forcedStatus ?? 'completed',
            'provider_reference' => $reference,
            'payload' => [
                'mode' => 'sandbox',
                'batch_number' => $batch->batch_number,
                'total_amount' => (int) $batch->total_amount,
                'items_count' => $batch->items()->count(),
                'disbursed_at' => now()->toIso8601String(),
            ],
            'failure_reason' => null,
        ];

        // Store in cache to simulate provider recording the transaction
        Cache::put('sandbox-payout:'.$batch->batch_number, $result, now()->addHours(24));

        if (self::$simulateTimeout) {
            throw new RuntimeException('Provider connection timeout during payout disbursement (simulated).');
        }

        if (self::$simulateFailure) {
            $failureResult = [
                'status' => 'failed',
                'provider_reference' => $reference,
                'payload' => [
                    'mode' => 'sandbox',
                    'batch_number' => $batch->batch_number,
                ],
                'failure_reason' => 'Saldo rekening escrow tidak mencukupi untuk batch ini.',
            ];
            Cache::put('sandbox-payout:'.$batch->batch_number, $failureResult, now()->addHours(24));

            return $failureResult;
        }

        return $result;
    }

    public function lookup(PayoutBatch $batch): ?array
    {
        if (self::$simulateLookupFailure) {
            return null;
        }

        $cached = Cache::get('sandbox-payout:'.$batch->batch_number);

        if ($cached !== null) {
            return $cached;
        }

        if ($batch->provider_reference !== null) {
            return [
                'status' => self::$forcedStatus ?? ($batch->status === 'paid' ? 'completed' : 'processing'),
                'provider_reference' => $batch->provider_reference,
                'payload' => [
                    'mode' => 'sandbox',
                    'recovered' => true,
                    'batch_number' => $batch->batch_number,
                ],
                'failure_reason' => null,
            ];
        }

        return null;
    }
}
