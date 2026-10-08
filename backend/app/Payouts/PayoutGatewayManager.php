<?php

namespace App\Payouts;

use RuntimeException;

class PayoutGatewayManager
{
    public function driver(): string
    {
        return (string) config('services.payout.driver', 'unconfigured');
    }

    public function isConfigured(): bool
    {
        $driver = $this->driver();

        if (in_array($driver, ['unconfigured', 'none', ''], true)) {
            return false;
        }

        if ($driver === 'iris') {
            return app(IrisPayoutGateway::class)->isConfigured();
        }

        return true;
    }

    public function gateway(?string $driver = null): PayoutGatewayInterface
    {
        $driver = $driver ?? $this->driver();

        return match ($driver) {
            'sandbox', 'mock' => app(SandboxPayoutGateway::class),
            'iris' => app(IrisPayoutGateway::class),
            default => throw new RuntimeException('Payout provider belum dikonfigurasi.'),
        };
    }
}
