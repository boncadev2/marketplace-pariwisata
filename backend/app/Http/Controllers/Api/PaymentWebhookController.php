<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InventoryUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\InventoryHold;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Services\InventoryReservationService;
use Carbon\CarbonImmutable;
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
        DB::transaction(function () use ($data): void {
            $attempt = PaymentAttempt::query()->where('provider', 'sandbox')->where('provider_reference', $data['provider_reference'])->lockForUpdate()->firstOrFail();
            $order = $attempt->order()->lockForUpdate()->firstOrFail();
            abort_unless((int) $attempt->amount === $data['amount'] && $attempt->currency === $data['currency'] && (int) $order->total === $data['amount'] && $order->currency === $data['currency'], 409, 'Nominal atau mata uang pembayaran tidak cocok.');
            $event = PaymentWebhookEvent::firstOrCreate(['provider_event_key' => $data['event_key']], ['provider' => 'sandbox', 'payment_attempt_id' => $attempt->id, 'payload' => $data]);
            abort_unless($event->payload === $data, 409, 'Kunci event telah digunakan untuk payload berbeda.');
            if ($event->processed_at !== null) {
                return;
            }
            if ($attempt->status !== 'succeeded') {
                $attempt->update(['status' => $data['status']]);
            }
            if ($data['status'] === 'succeeded' && $order->status === 'pending_payment') {
                $item = $order->items()->first();
                $hold = InventoryHold::find($item?->snapshot['inventory_hold_id'] ?? null);
                $allocated = false;
                if ($hold !== null) {
                    $inventory = app(InventoryReservationService::class);
                    $hold = $inventory->confirm($hold);
                    if ($hold->state !== 'confirmed') {
                        try {
                            $replacement = $inventory->reserve($hold->bucket, $hold->quantity, CarbonImmutable::now()->addMinutes(15));
                            $replacement = $inventory->confirm($replacement);
                            $item->update(['snapshot' => array_replace($item->snapshot, ['inventory_hold_id' => $replacement->id])]);
                            $allocated = $replacement->state === 'confirmed';
                        } catch (InventoryUnavailableException) {
                            $allocated = false;
                        }
                    } else {
                        $allocated = true;
                    }
                }
                $order->update(['status' => $allocated ? 'paid' : 'payment_exception']);
            }
            $event->update(['processed_at' => now()]);
        }, 3);

        return response()->json(['data' => ['accepted' => true]]);
    }
}
