<?php

namespace App\Services\Refund;

use App\Models\RefundRequest;

class SandboxRefundAdapter implements RefundAdapterInterface
{
    public function process(RefundRequest $refundRequest): bool
    {
        // In sandbox, we just return true to simulate successful processing
        // Real implementation would call payment gateway API
        return true;
    }
}
