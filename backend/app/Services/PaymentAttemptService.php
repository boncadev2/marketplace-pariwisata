<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Payments\PaymentGateway;

class PaymentAttemptService
{
    public function __construct(private PaymentGateway $paymentGateway) {}

    public function create(Order $order): PaymentAttempt
    {
        $response = $this->paymentGateway->createCheckout($order);

        return PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'sandbox', 'provider_reference' => $response['provider_reference'], 'status' => 'pending', 'currency' => $order->currency, 'amount' => $order->total, 'checkout_url' => $response['checkout_url'], 'provider_payload' => $response['payload']]);
    }
}
