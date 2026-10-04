<?php

namespace App\Services\Shipping;

use App\Models\UmkmProduct;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class BiteshipClient
{
    public function configured(): bool
    {
        return config('services.shipping.driver') === 'biteship' && config('services.shipping.enabled') && filled(config('services.shipping.api_key'));
    }

    public function rates(UmkmProduct $product, int $quantity, string $postalCode): array
    {
        abort_unless($product->origin_postal_code && $product->weight_grams > 0, 409, 'Penjual perlu melengkapi kode pos asal dan berat produk.');
        $response = $this->client()->post('/v1/rates/couriers', ['origin_postal_code' => (int) $product->origin_postal_code, 'destination_postal_code' => (int) $postalCode, 'couriers' => config('services.shipping.couriers'), 'items' => [['name' => $product->name, 'value' => $product->price, 'weight' => $product->weight_grams, 'quantity' => $quantity]]]);
        abort_unless($response->successful() && $response->json('success') === true && is_array($response->json('pricing')), 503, 'Ongkir ekspedisi belum dapat dimuat.');
        $allowed = explode(',', (string) config('services.shipping.couriers'));

        return collect($response->json('pricing'))->filter(fn ($rate) => in_array($rate['courier_code'] ?? null, $allowed, true) && is_numeric($rate['price'] ?? null) && (int) $rate['price'] >= 0 && (int) $rate['price'] <= 1000000 && (float) $rate['price'] === (float) (int) $rate['price'] && preg_match('/^[a-z0-9_-]{1,80}$/D', $rate['courier_service_code'] ?? '') === 1)
            ->take(30)->map(fn ($rate) => ['courier' => $rate['courier_code'], 'service' => $rate['courier_service_code'], 'fee' => (int) $rate['price'], 'duration' => mb_substr((string) ($rate['duration'] ?? ''), 0, 120)])->values()->all();
    }

    public function tracking(string $courier, string $waybill, string $trackingId): array
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $trackingId) && preg_match('/^[a-z0-9_-]{1,40}$/D', $courier) && preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]{2,99}$/D', $waybill), 422, 'Kode kurir atau resi tidak valid.');
        $response = $this->client()->get('/v1/trackings/'.rawurlencode($trackingId));
        abort_unless($response->successful() && $response->json('success') === true && $response->json('id') === $trackingId && ($response->json('waybill_id') === $waybill) && $response->json('courier.company') === $courier, 503, 'Pelacakan ekspedisi belum dapat diverifikasi.');

        return ['status' => mb_substr((string) $response->json('status'), 0, 80), 'history' => collect($response->json('history') ?? [])->take(50)->map(fn ($event) => ['status' => mb_substr((string) ($event['status'] ?? ''), 0, 80), 'note' => mb_substr((string) ($event['note'] ?? ''), 0, 500), 'updated_at' => mb_substr((string) ($event['updated_at'] ?? ''), 0, 50)])->all()];
    }

    private function client(): PendingRequest
    {
        abort_unless($this->configured(), 503, 'Layanan ekspedisi belum dikonfigurasi.');

        return Http::baseUrl('https://api.biteship.com')->withHeaders(['Authorization' => (string) config('services.shipping.api_key')])->acceptJson()->asJson()->connectTimeout(3)->timeout(10)->withoutRedirecting();
    }
}
