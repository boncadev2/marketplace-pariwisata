<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $secret = (string) config('services.sandbox_payment.webhook_secret');
        abort_unless($secret !== '' && hash_equals($secret, (string) $request->header('X-Sandbox-Signature')), 401);
        $data = $request->validate([
            'event_key' => ['required', 'string', 'max:128'],
            'provider_reference' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:succeeded,failed'],
            'amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
        ]);
        $eventId = DB::transaction(function () use ($data): int {
            $attempt = PaymentAttempt::query()->where('provider', 'sandbox')->where('provider_reference', $data['provider_reference'])->lockForUpdate()->firstOrFail();
            $order = $attempt->order()->lockForUpdate()->firstOrFail();
            abort_unless((int) $attempt->amount === $data['amount'] && $attempt->currency === $data['currency'] && (int) $order->total === $data['amount'] && $order->currency === $data['currency'], 409, 'Nominal atau mata uang pembayaran tidak cocok.');
            $event = PaymentWebhookEvent::firstOrCreate(['provider_event_key' => $data['event_key']], ['provider' => 'sandbox', 'payment_attempt_id' => $attempt->id, 'payload' => $data]);
            abort_unless($event->payload === $data, 409, 'Kunci event telah digunakan untuk payload berbeda.');

            return $event->id;
        }, 3);

        ProcessPaymentWebhook::dispatch($eventId);

        return response()->json(['data' => ['accepted' => true]]);
    }
}
