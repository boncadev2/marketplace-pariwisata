<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\PaymentAttempt;

interface PaymentGateway
{
    /** @return array{provider_reference:string,checkout_url:?string,payload:array<string,mixed>} */
    public function createCheckout(Order $order): array;

    /** @return array{provider_reference:string,checkout_url:?string,payload:array<string,mixed>}|null */
    public function findCheckout(Order $order): ?array;

    /**
     * @return array{reference:string,status:string,amount:int,currency:string,fee:int,settlement_reference:?string,payload:array<string,mixed>}
     */
    public function fetchPaymentStatus(PaymentAttempt $paymentAttempt): array;
}
