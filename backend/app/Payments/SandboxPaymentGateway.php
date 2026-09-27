<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Support\Str;

class SandboxPaymentGateway implements PaymentGateway
{
    public function createCheckout(Order $order): array
    {
        $reference = 'sandbox_'.Str::lower(Str::random(24));

        return ['provider_reference' => $reference, 'checkout_url' => config('app.url').'/sandbox-payments/'.$reference, 'payload' => ['mode' => 'sandbox']];
    }
}
