<?php

namespace App\Payments;

use App\Models\Order;

interface PaymentGateway
{
    /** @return array{provider_reference:string,checkout_url:string,payload:array<string,mixed>} */
    public function createCheckout(Order $order): array;
}
