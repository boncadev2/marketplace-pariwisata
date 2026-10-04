<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\ReservationPayment;
use App\Payments\MidtransSandboxGateway;
use App\Services\ReservationPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, MidtransSandboxGateway $gateway): JsonResponse
    {
        $gateway->assertConfigured();
        $data = $request->validate([
            'order_id' => ['required', 'string', 'max:50'], 'status_code' => ['required', 'string', 'regex:/^\d{3}$/'],
            'gross_amount' => ['required', 'string', 'regex:/^\d{1,13}(?:\.0{1,2})?$/'],
            'signature_key' => ['required', 'string', 'size:128'], 'transaction_id' => ['required', 'string', 'max:100'],
            'transaction_status' => ['required', 'string', 'max:32'], 'currency' => ['sometimes', 'in:IDR'],
        ]);
        $signature = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].$gateway->serverKey());
        abort_unless(hash_equals($signature, $data['signature_key']), 401);
        $reservation = ReservationPayment::query()->where('reference', $data['order_id'])->first();
        if ($reservation) {
            abort_unless($reservation->provider === $gateway->provider(), 409, 'Lingkungan pembayaran berbeda.');
            $actual = $gateway->status($data['order_id']);
            abort_unless($actual !== null, 503, 'Status Midtrans belum tersedia.');
            abort_unless(($actual['transaction_id'] ?? null) === $data['transaction_id'], 409, 'Transaksi Midtrans tidak cocok.');
            $status = $gateway->normalize($actual, $data['order_id']);
            abort_unless($status['amount'] === (int) $data['gross_amount'] && $status['amount'] === $reservation->amount, 409, 'Nominal pembayaran Midtrans tidak cocok.');
            app(ReservationPaymentService::class)->apply($reservation, $actual);

            return response()->json(['data' => ['accepted' => true]])->header('Cache-Control', 'no-store');
        }
        $attempt = PaymentAttempt::query()->where('provider', $gateway->provider())->where('provider_reference', $data['order_id'])->firstOrFail();
        $actual = $gateway->status($data['order_id']);
        abort_unless($actual !== null, 503, 'Status Midtrans belum tersedia.');
        abort_unless(($actual['transaction_id'] ?? null) === $data['transaction_id'], 409, 'Transaksi Midtrans tidak cocok.');
        $status = $gateway->normalize($actual, $data['order_id']);
        abort_unless($status['amount'] === (int) $data['gross_amount'] && $status['amount'] === (int) $attempt->amount
            && $status['currency'] === $attempt->currency, 409, 'Nominal atau mata uang Midtrans tidak cocok.');
        if ($status['status'] === 'unknown') {
            return response()->json(['data' => ['accepted' => true, 'ignored' => true]])->header('Cache-Control', 'no-store');
        }
        $payload = ['event_key' => $gateway->provider().':'.hash('sha256', $data['order_id'].'|'.$data['transaction_id'].'|'.$status['status']),
            'provider_reference' => $data['order_id'], 'status' => $status['status'], 'amount' => $status['amount'], 'currency' => $status['currency']];
        $eventId = DB::transaction(function () use ($attempt, $payload, $gateway): int {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $order = $locked->order()->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->amount === $payload['amount'] && (int) $order->total === $payload['amount']
                && $order->currency === $payload['currency'], 409);
            $event = PaymentWebhookEvent::firstOrCreate(['provider_event_key' => $payload['event_key']],
                ['provider' => $gateway->provider(), 'payment_attempt_id' => $locked->id, 'payload' => $payload]);
            $existing = $event->payload;
            $incoming = $payload;
            ksort($existing);
            ksort($incoming);
            abort_unless($event->provider === $gateway->provider() && $event->payment_attempt_id === $locked->id && $existing === $incoming, 409);

            return $event->id;
        }, 3);
        ProcessPaymentWebhook::dispatch($eventId);

        return response()->json(['data' => ['accepted' => true]])->header('Cache-Control', 'no-store');
    }
}
