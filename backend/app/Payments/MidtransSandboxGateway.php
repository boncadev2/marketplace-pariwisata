<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\ReservationPayment;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransSandboxGateway implements PaymentGateway
{
    public function isConfigured(): bool
    {
        $key = (string) config('services.midtrans.server_key');

        return app()->environment(['local', 'testing', 'staging'])
            && preg_match('/^(?:SB-)?Mid-server-[A-Za-z0-9_-]+$/D', $key) === 1;
    }

    public function assertConfigured(): void
    {
        abort_unless($this->isConfigured(), 503, 'Midtrans sandbox belum dikonfigurasi atau lingkungan tidak mendukung sandbox.');
    }

    public function createCheckout(Order $order): array
    {
        $this->assertConfigured();
        abort_unless($order->currency === 'IDR' && (int) $order->total > 0 && $order->status === 'pending_payment', 422);
        $response = $this->client()->post($this->snapBase().'/snap/v1/transactions', [
            'transaction_details' => ['order_id' => $order->public_id, 'gross_amount' => (int) $order->total],
            'customer_details' => ['first_name' => $order->customer_name, 'email' => $order->customer_email],
            'item_details' => [['id' => $order->public_id, 'name' => 'Pesanan wisata', 'quantity' => 1, 'price' => (int) $order->total]],
            'expiry' => ['duration' => 15, 'unit' => 'minutes'],
            'callbacks' => ['finish' => rtrim((string) config('services.frontend_url'), '/').'/akun'],
        ]);
        if (! $response->successful()) {
            throw new RuntimeException('Midtrans Snap HTTP '.$response->status());
        }
        $url = $response->json('redirect_url');
        if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== parse_url($this->snapBase(), PHP_URL_HOST)) {
            throw new RuntimeException('Midtrans Snap redirect tidak valid.');
        }

        return ['provider_reference' => $order->public_id, 'checkout_url' => $url,
            'payload' => ['mode' => $this->mode(), 'merchant_reference' => $order->public_id]];
    }

    public function createReservationCheckout(ReservationPayment $payment, User $user): array
    {
        $this->assertConfigured();
        $response = $this->client()->post($this->snapBase().'/snap/v1/transactions', [
            'transaction_details' => ['order_id' => $payment->reference, 'gross_amount' => $payment->amount],
            'customer_details' => ['first_name' => $user->name, 'email' => $user->email],
            'item_details' => [['id' => $payment->reference, 'name' => match ($payment->kind) {
                'umkm' => 'Pesanan produk UMKM', 'lodging' => 'Reservasi penginapan', default => 'Reservasi kuliner'
            }, 'quantity' => 1, 'price' => $payment->amount]],
            'expiry' => ['duration' => 15, 'unit' => 'minutes'],
            'callbacks' => ['finish' => rtrim((string) config('services.frontend_url'), '/').($payment->kind === 'umkm' ? '/akun/umkm' : '/akun/reservasi')],
        ]);
        $url = $response->json('redirect_url');
        $token = $response->json('token');
        if (! $response->successful() || ! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== parse_url($this->snapBase(), PHP_URL_HOST)
            || ! is_string($token) || preg_match('/^[A-Za-z0-9_-]{16,200}$/D', $token) !== 1) {
            throw new RuntimeException('Respons checkout Midtrans tidak valid.');
        }

        return ['checkout_url' => $url, 'snap_token' => $token];
    }

    public function cancelReservationCheckout(ReservationPayment $payment): array
    {
        $actual = $this->status($payment->reference);
        if ($actual !== null) {
            $normalized = $this->normalize($actual, $payment->reference);
            if (in_array($normalized['status'], ['succeeded', 'failed'], true)) {
                return $actual;
            }
            abort_unless($normalized['status'] === 'pending', 409, 'Status pembayaran belum pasti. Periksa kembali nanti.');
            $this->client()->post($this->apiBase().'/v2/'.rawurlencode($payment->reference).'/cancel');
            $actual = $this->status($payment->reference);
            abort_unless($actual !== null && in_array($this->normalize($actual, $payment->reference)['status'], ['succeeded', 'failed'], true), 409, 'Midtrans belum mengonfirmasi pembatalan. Stok tetap ditahan.');

            return $actual;
        }
        abort_unless(is_string($payment->snap_token) && $payment->snap_token !== '', 409, 'Sesi pembayaran belum pasti. Stok tetap ditahan sampai status dapat diperiksa.');
        $response = $this->client()->post($this->snapBase().'/snap/v1/transactions/'.rawurlencode($payment->snap_token).'/cancel');
        abort_unless(($response->successful() && is_string($response->json('canceled_at'))) || $response->json('error_messages') === ['token already canceled'], 409, 'Sesi Midtrans belum dapat dibatalkan. Pilih metode pembayaran atau periksa kembali nanti.');

        return ['order_id' => $payment->reference, 'gross_amount' => (string) $payment->amount, 'currency' => 'IDR', 'transaction_status' => 'cancel'];
    }

    public function findCheckout(Order $order): ?array
    {
        $data = $this->status($order->public_id);
        if ($data === null) {
            return null;
        }
        $status = $this->normalize($data, $order->public_id);
        abort_unless($status['amount'] === (int) $order->total && $status['currency'] === $order->currency, 409);

        return ['provider_reference' => $order->public_id, 'checkout_url' => null,
            'payload' => ['mode' => $this->mode(), 'merchant_reference' => $order->public_id, 'provider_status' => $status['status'], 'recovered' => true]];
    }

    public function fetchPaymentStatus(PaymentAttempt $paymentAttempt): array
    {
        abort_unless($paymentAttempt->provider === $this->provider(), 422);
        $reference = (string) ($paymentAttempt->provider_reference ?: $paymentAttempt->order->public_id);
        $data = $this->status($reference);
        if ($data === null) {
            return ['reference' => $reference, 'status' => 'unknown', 'amount' => (int) $paymentAttempt->amount,
                'currency' => $paymentAttempt->currency, 'fee' => 0, 'settlement_reference' => null,
                'payload' => ['provider_status' => 'not_found', 'mode' => $this->mode()]];
        }

        return $this->normalize($data, $reference);
    }

    public function status(string $reference): ?array
    {
        $response = $this->client()->get($this->apiBase().'/v2/'.rawurlencode($reference).'/status');
        if ($response->status() === 404 || (string) $response->json('status_code') === '404') {
            return null;
        }
        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('Midtrans status HTTP '.$response->status());
        }

        return $response->json();
    }

    public function normalize(array $data, string $reference): array
    {
        if (($data['order_id'] ?? null) !== $reference || ($data['currency'] ?? 'IDR') !== 'IDR') {
            throw new RuntimeException('Midtrans reference atau mata uang tidak cocok.');
        }
        $rawAmount = (string) ($data['gross_amount'] ?? '');
        if (! preg_match('/^\d{1,13}(?:\.0{1,2})?$/', $rawAmount)) {
            throw new RuntimeException('Nominal Midtrans tidak valid.');
        }
        $fraud = $data['fraud_status'] ?? null;
        $status = match ($data['transaction_status'] ?? '') {
            'settlement' => in_array($fraud, ['deny', 'challenge'], true) ? 'unknown' : 'succeeded',
            'capture' => $fraud === 'accept' ? 'succeeded' : ($fraud === 'deny' ? 'failed' : 'pending'),
            'pending' => 'pending',
            'deny', 'cancel', 'expire', 'failure' => 'failed',
            default => 'unknown',
        };

        return ['reference' => $reference, 'status' => $status, 'amount' => (int) $rawAmount, 'currency' => 'IDR', 'fee' => 0,
            'settlement_reference' => null, 'payload' => array_intersect_key($data, array_flip(['transaction_id', 'transaction_status', 'payment_type', 'fraud_status']))];
    }

    public function provider(): string
    {
        return 'midtrans_sandbox';
    }

    public function serverKey(): string
    {
        return (string) config('services.midtrans.server_key');
    }

    public function apiBase(): string
    {
        return 'https://api.sandbox.midtrans.com';
    }

    protected function snapBase(): string
    {
        return 'https://app.sandbox.midtrans.com';
    }

    protected function mode(): string
    {
        return 'sandbox';
    }

    private function client(): PendingRequest
    {
        $this->assertConfigured();

        return Http::withHeaders(['Authorization' => 'Basic '.base64_encode($this->serverKey().':')])
            ->acceptJson()->asJson()->connectTimeout(3)->timeout(10)->withoutRedirecting();
    }
}
