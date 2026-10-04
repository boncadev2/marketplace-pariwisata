<?php

namespace App\Payments;

class PaymentGatewayManager
{
    public function driver(): string
    {
        return (string) config('services.payment_gateway.driver', 'sandbox');
    }

    public function forProvider(string $provider): PaymentGateway
    {
        return match ($provider) {
            'sandbox' => app(SandboxPaymentGateway::class),
            'midtrans_sandbox' => new MidtransSandboxGateway,
            'midtrans_production' => app(MidtransProductionGateway::class),
            default => abort(503, 'Payment gateway tidak dikenal.'),
        };
    }
}
