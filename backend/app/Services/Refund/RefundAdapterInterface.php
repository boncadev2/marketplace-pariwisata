<?php

namespace App\Services\Refund;

use App\Models\RefundRequest;

interface RefundAdapterInterface
{
    /**
     * Process the refund with the payment provider.
     */
    public function process(RefundRequest $refundRequest): bool;
}
