<?php

namespace App\Services;

use App\Models\Destination;
use App\Models\Product;
use App\Payments\MidtransProductionGateway;
use App\Services\Shipping\BiteshipClient;
use Illuminate\Support\Facades\Cache;

class ProductionReadinessService
{
    public function report(): array
    {
        $https = fn ($url) => is_string($url) && parse_url($url, PHP_URL_SCHEME) === 'https' && filled(parse_url($url, PHP_URL_HOST)) && ! in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1'], true);
        $heartbeat = Cache::get('health:scheduler:last_seen');
        $checks = [
            ['label' => 'Lingkungan produksi', 'ready' => app()->isProduction(), 'instruction' => 'Atur APP_ENV=production pada deployment.'],
            ['label' => 'Debug dinonaktifkan', 'ready' => ! config('app.debug'), 'instruction' => 'Atur APP_DEBUG=false.'],
            ['label' => 'Domain HTTPS aplikasi', 'ready' => $https(config('app.url')) && $https(config('services.frontend_url')), 'instruction' => 'Isi APP_URL dan FRONTEND_URL dengan domain HTTPS tetap.'],
            ['label' => 'Webhook pembayaran', 'ready' => $https(config('services.midtrans.webhook_url')) && str_ends_with((string) config('services.midtrans.webhook_url'), '/api/v1/webhooks/payments/midtrans'), 'instruction' => 'Isi MIDTRANS_WEBHOOK_URL dan daftarkan URL yang sama di dashboard Midtrans. Keterjangkauan harus diuji dari Midtrans.'],
            ['label' => 'Midtrans produksi', 'ready' => config('services.payment_gateway.driver') === 'midtrans_production' && app(MidtransProductionGateway::class)->isConfigured(), 'instruction' => 'Isi MIDTRANS_PRODUCTION_SERVER_KEY di server; atur PAYMENT_GATEWAY=midtrans_production dan MIDTRANS_PRODUCTION_ENABLED=true setelah akun merchant siap.'],
            ['label' => 'Pemesanan produksi', 'ready' => (bool) config('services.commerce.production_enabled'), 'instruction' => 'Atur COMMERCE_PRODUCTION_ENABLED=true setelah uji staging dan kesiapan operasional.'],
            ['label' => 'Queue dan scheduler', 'ready' => config('queue.default') !== 'sync' && $heartbeat && abs(now()->timestamp - (int) $heartbeat) < 180, 'instruction' => 'Jalankan worker dan scheduler terus-menerus; cek heartbeat.'],
            ['label' => 'Email pelanggan', 'ready' => ! in_array(config('mail.default'), ['log', 'array'], true) && ! in_array(config('mail.mailers.smtp.host'), ['mailpit', 'localhost', '127.0.0.1'], true), 'instruction' => 'Konfigurasikan layanan email produksi dan uji email verifikasi.'],
            ['label' => 'Refund Midtrans', 'ready' => config('services.refund.driver') === 'midtrans_production' && (bool) config('services.midtrans.refunds_enabled'), 'instruction' => 'Aktifkan kemampuan refund merchant, REFUND_DRIVER=midtrans_production dan MIDTRANS_REFUNDS_ENABLED=true.'],
            ['label' => 'Ekspedisi terhubung', 'ready' => app(BiteshipClient::class)->configured(), 'instruction' => 'Konfigurasikan penyedia pengiriman; adapter Biteship tersedia dengan BITESHIP_API_KEY, SHIPPING_DRIVER=biteship dan SHIPPING_PROVIDER_ENABLED=true.'],
            ['label' => 'WhatsApp Gateway', 'ready' => filled(config('services.whatsapp.token')) || config('services.whatsapp.driver') === 'log', 'instruction' => 'Konfigurasikan token WhatsApp provider di WHATSAPP_TOKEN untuk pengiriman tiket via pesan instan.'],
            ['label' => 'Cadangan database', 'ready' => count(glob(storage_path('app/backups/backup-*.sql*'))) > 0, 'instruction' => 'Jalankan php artisan app:backup-database --compress untuk memastikan cadangan tersedia.'],
            ['label' => 'Konten destinasi nyata', 'ready' => ! Destination::query()->where('publication_status', 'published')->where('location_is_demo', true)->exists(), 'instruction' => 'Ganti data demo dengan informasi nyata atau ubah statusnya menjadi draft sebelum peluncuran.'],
            ['label' => 'Galeri katalog', 'ready' => ! Destination::query()->where('publication_status', 'published')->whereDoesntHave('photos', fn ($query) => $query->where('is_illustration', false))->exists() && ! Product::query()->where('type', 'package')->where('status', 'published')->whereDoesntHave('photos', fn ($query) => $query->where('is_illustration', false))->exists(), 'instruction' => 'Unggah foto asli destinasi dan paket melalui dashboard.'],
        ];

        return ['ready' => collect($checks)->every(fn ($check) => $check['ready'] === true), 'checks' => $checks, 'note' => 'Pemeriksaan konfigurasi tidak membuktikan webhook dapat diakses, backup dapat dipulihkan, atau transaksi provider berhasil. Uji staging tetap diperlukan.'];
    }
}
