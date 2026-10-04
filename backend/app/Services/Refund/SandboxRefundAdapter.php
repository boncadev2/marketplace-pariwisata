<?php

namespace App\Services\Refund;

use App\Models\RefundRequest;

class SandboxRefundAdapter implements RefundAdapterInterface
{
    public function process(RefundRequest $refundRequest): array
    {
        if (! app()->environment(['local', 'testing']) || ! config('services.sandbox_refund.enabled')) {
            return ['confirmed' => false, 'failure_reason' => 'Refund provider is not configured.'];
        }

        return [
            'confirmed' => true,
            'provider_reference' => 'sandbox-refund-'.$refundRequest->id,
            'payload' => ['provider' => 'sandbox', 'confirmed' => true],
        ];
    }
}
