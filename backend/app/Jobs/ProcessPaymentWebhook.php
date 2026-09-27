<?php

namespace App\Jobs;

use App\Exceptions\InventoryUnavailableException;
use App\Models\InventoryHold;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Services\InventoryReservationService;
use App\Services\TransactionOutbox;
use App\Services\VoucherService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $eventId) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $event = PaymentWebhookEvent::query()->lockForUpdate()->findOrFail($this->eventId);
            if ($event->processed_at !== null) {
                return;
            }
            $data = $event->payload;
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($event->payment_attempt_id);
            $order = $attempt->order()->lockForUpdate()->firstOrFail();
            if ((int) $attempt->amount !== (int) $data['amount'] || $attempt->currency !== $data['currency'] || (int) $order->total !== (int) $data['amount'] || $order->currency !== $data['currency']) {
                throw new \RuntimeException('Payment amount changed after webhook acceptance.');
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
            if ($order->fresh()->status === 'paid') {
                app(VoucherService::class)->issue($order);
                app(TransactionOutbox::class)->record($order, 'confirmation', 'paid', 'Status pembayaran: berhasil.');
                app(TransactionOutbox::class)->record($order, 'voucher', 'issued', 'Gunakan nomor pesanan dan kode akses yang disimpan saat checkout.');
            }
        }, 3);
    }
}
