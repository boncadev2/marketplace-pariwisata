<?php

namespace App\Services\Refund;

use App\Models\RefundRequest;

interface RefundAdapterInterface
{
    /**
     * Process the refund with the payment provider.
     */
    /** @return array{confirmed: bool, provider_reference?: string, payload?: array<string, mixed>, failure_reason?: string} */
    public function process(RefundRequest $refundRequest): array;
}
