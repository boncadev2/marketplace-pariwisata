<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Payments\MidtransSandboxGateway;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaymentAttemptService
{
    public function __construct(private PaymentGateway $paymentGateway) {}

    public function create(Order $order): PaymentAttempt
    {
        if ($this->paymentGateway instanceof MidtransSandboxGateway) {
            $this->paymentGateway->assertConfigured();
        }

        return Cache::lock('payment-attempt:'.$order->id, 15)->block(5, function () use ($order): PaymentAttempt {
            [$attempt, $isNew] = DB::transaction(function () use ($order): array {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                $existing = PaymentAttempt::query()
                    ->where('order_id', $lockedOrder->id)
                    ->where(fn ($query) => $query->whereIn('status', ['created', 'uncertain', 'pending', 'succeeded'])
                        ->orWhereIn('provider', ['midtrans_sandbox', 'midtrans_production']))
                    ->latest('id')
                    ->first();

                if ($existing !== null) {
                    return [$existing, false];
                }

                abort_unless($lockedOrder->status === 'pending_payment', 409, 'Pesanan tidak menerima pembayaran baru.');

                return [PaymentAttempt::create([
                    'order_id' => $lockedOrder->id,
                    'provider' => app(PaymentGatewayManager::class)->driver(),
                    'provider_reference' => in_array(app(PaymentGatewayManager::class)->driver(), ['midtrans_sandbox', 'midtrans_production'], true) ? $lockedOrder->public_id : null,
                    'status' => 'created',
                    'currency' => $lockedOrder->currency,
                    'amount' => $lockedOrder->total,
                    'provider_payload' => ['merchant_reference' => $lockedOrder->public_id],
                ]), true];
            }, 3);

            $order->refresh();
            if (in_array($attempt->status, ['pending', 'succeeded', 'failed'], true)) {
                return $attempt;
            }

            if ($attempt->status === 'uncertain' || ($attempt->status === 'created' && ! $isNew)) {
                return $this->recoverOrKeepUncertain($attempt, $order);
            }

            try {
                return $this->complete($attempt, $this->paymentGateway->createCheckout($order));
            } catch (Throwable) {
                return $this->recoverOrKeepUncertain($attempt, $order);
            }
        });
    }

    private function recoverOrKeepUncertain(PaymentAttempt $attempt, Order $order): PaymentAttempt
    {
        try {
            $gateway = $attempt->provider === app(PaymentGatewayManager::class)->driver() ? $this->paymentGateway : app(PaymentGatewayManager::class)->forProvider($attempt->provider);
            $response = $gateway->findCheckout($order);
        } catch (Throwable) {
            $response = null;
        }

        if ($response !== null) {
            return $this->complete($attempt, $response);
        }

        return DB::transaction(function () use ($attempt): PaymentAttempt {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if (in_array($locked->status, ['pending', 'succeeded'], true)) {
                return $locked;
            }
            $locked->update([
                'status' => 'uncertain',
                'reconciliation_error' => 'Hasil pembuatan pembayaran belum dapat dipastikan; lookup provider diperlukan sebelum retry.',
                'next_reconciliation_at' => now()->addMinute(),
            ]);

            return $locked->fresh();
        }, 3);
    }

    /** @param array{provider_reference:string,checkout_url:?string,payload:array<string,mixed>} $response */
    private function complete(PaymentAttempt $attempt, array $response): PaymentAttempt
    {
        return DB::transaction(function () use ($attempt, $response): PaymentAttempt {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if (in_array($attempt->status, ['pending', 'succeeded'], true)) {
                return $attempt;
            }

            $attempt->update([
                'provider_reference' => $response['provider_reference'],
                'status' => 'pending',
                'checkout_url' => $response['checkout_url'],
                'provider_payload' => array_replace($attempt->provider_payload ?? [], $response['payload']),
                'reconciliation_error' => null,
                'next_reconciliation_at' => null,
            ]);

            return $attempt->fresh();
        }, 3);
    }
}
