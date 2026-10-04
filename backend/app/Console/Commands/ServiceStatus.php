<?php

namespace App\Console\Commands;

use App\Payments\MidtransProductionGateway;
use App\Payments\MidtransSandboxGateway;
use App\Services\Shipping\BiteshipClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:services-status')]
#[Description('Show service configuration requirements without displaying credentials or calling providers')]
class ServiceStatus extends Command
{
    public function handle(): int
    {
        $driver = config('services.payment_gateway.driver');
        $live = $driver === 'midtrans_production';
        $gateway = $live ? app(MidtransProductionGateway::class) : new MidtransSandboxGateway;
        $midtrans = in_array($driver, ['midtrans_sandbox', 'midtrans_production'], true);
        $webhook = (string) config('services.midtrans.webhook_url');
        $webhookReady = filter_var($webhook, FILTER_VALIDATE_URL) !== false
            && parse_url($webhook, PHP_URL_SCHEME) === 'https'
            && parse_url($webhook, PHP_URL_PATH) === '/api/v1/webhooks/payments/midtrans'
            && ! parse_url($webhook, PHP_URL_USER) && ! parse_url($webhook, PHP_URL_PASS)
            && ! parse_url($webhook, PHP_URL_QUERY) && ! parse_url($webhook, PHP_URL_FRAGMENT);
        $smtp = config('mail.default') === 'smtp';
        $smtpReady = $smtp && filled(config('mail.mailers.smtp.host'))
            && ! in_array(config('mail.mailers.smtp.host'), ['mailpit', 'localhost', '127.0.0.1'], true)
            && filled(config('mail.mailers.smtp.username')) && filled(config('mail.mailers.smtp.password'))
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false;
        $rows = [
            ['Midtrans '.($live ? 'produksi' : 'sandbox'), $midtrans && $gateway->isConfigured(), $live ? 'PAYMENT_GATEWAY, MIDTRANS_PRODUCTION_ENABLED, MIDTRANS_PRODUCTION_SERVER_KEY' : 'PAYMENT_GATEWAY=midtrans_sandbox, MIDTRANS_SERVER_KEY'],
            ['Webhook HTTPS', $webhookReady, 'MIDTRANS_WEBHOOK_URL; daftarkan URL yang sama di dashboard Midtrans'],
            ['Refund Midtrans', $midtrans && $gateway->isConfigured() && config('services.midtrans.refunds_enabled') && config('services.refund.driver') === $driver, 'REFUND_DRIVER harus sama dengan PAYMENT_GATEWAY; MIDTRANS_REFUNDS_ENABLED=true'],
            ['Biteship', app(BiteshipClient::class)->configured(), 'SHIPPING_DRIVER=biteship, SHIPPING_PROVIDER_ENABLED=true, BITESHIP_API_KEY'],
            ['SMTP eksternal', $smtpReady, 'MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT, MAIL_SCHEME, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS'],
        ];
        $this->table(['Layanan', 'Konfigurasi', 'Pengaturan yang diperlukan'], array_map(fn ($row) => [$row[0], $row[1] ? 'Terisi' : 'Belum lengkap', $row[2]], $rows));
        $this->info('Terisi berarti konfigurasi tersedia, bukan bukti layanan berhasil terhubung. Tidak ada permintaan provider atau pengiriman email dari perintah ini.');
        $this->line('Rahasia: backend/.env. Domain dan override host/port SMTP Docker: .env pada root proyek. Setelah perubahan, recreate backend/worker/scheduler dan perbarui config cache.');

        return self::SUCCESS;
    }
}
