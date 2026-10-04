<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\PaymentAttempt;

class SandboxPaymentGateway implements PaymentGateway
{
    public function createCheckout(Order $order): array
    {
        $reference = 'sandbox_'.substr(hash('sha256', $order->public_id), 0, 24);

        return ['provider_reference' => $reference, 'checkout_url' => config('services.frontend_url').'/pembayaran/'.$reference, 'payload' => ['mode' => 'sandbox', 'merchant_reference' => $order->public_id]];
    }

    public function findCheckout(Order $order): ?array
    {
        return null;
    }

    public function fetchPaymentStatus(PaymentAttempt $paymentAttempt): array
    {
        $payload = $paymentAttempt->provider_payload ?? [];

        return [
            'reference' => (string) $paymentAttempt->provider_reference,
            'status' => (string) ($payload['provider_status'] ?? $paymentAttempt->status),
            'amount' => (int) ($payload['provider_amount'] ?? $paymentAttempt->amount),
            'currency' => (string) ($payload['provider_currency'] ?? $paymentAttempt->currency),
            'fee' => (int) ($payload['provider_fee'] ?? 0),
            'settlement_reference' => isset($payload['settlement_reference']) ? (string) $payload['settlement_reference'] : null,
            'payload' => ['mode' => 'sandbox', 'checked_via' => 'status_api'],
        ];
    }
}
