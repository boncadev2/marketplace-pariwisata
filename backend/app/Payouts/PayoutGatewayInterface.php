<?php

namespace App\Payouts;

use App\Models\PayoutBatch;

interface PayoutGatewayInterface
{
    /**
     * Disburse a payout batch to the payment/payout provider.
     *
     * @return array{
     *     status: 'processing'|'completed'|'failed',
     *     provider_reference: string,
     *     payload?: array<string, mixed>,
     *     failure_reason?: string|null
     * }
     */
    public function disburse(PayoutBatch $batch): array;

    /**
     * Lookup an existing payout batch at the provider to prevent duplicate payouts before retrying.
     *
     * @return array{
     *     status: 'processing'|'completed'|'failed',
     *     provider_reference: string,
     *     payload?: array<string, mixed>,
     *     failure_reason?: string|null
     * }|null
     */
    public function lookup(PayoutBatch $batch): ?array;
}
