<?php

namespace App\Payments;

class MidtransProductionGateway extends MidtransSandboxGateway
{
    public function isConfigured(): bool
    {
        return app()->environment(['production', 'staging']) && config('services.midtrans.production_enabled')
            && preg_match('/^Mid-server-[A-Za-z0-9_-]+$/D', $this->serverKey()) === 1;
    }

    public function assertConfigured(): void
    {
        abort_unless($this->isConfigured(), 503, 'Midtrans produksi belum diaktifkan atau key produksi belum tersedia.');
    }

    public function provider(): string
    {
        return 'midtrans_production';
    }

    public function serverKey(): string
    {
        return (string) config('services.midtrans.production_server_key');
    }

    public function apiBase(): string
    {
        return 'https://api.midtrans.com';
    }

    protected function snapBase(): string
    {
        return 'https://app.midtrans.com';
    }

    protected function mode(): string
    {
        return 'production';
    }
}
