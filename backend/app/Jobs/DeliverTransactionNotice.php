<?php

namespace App\Jobs;

use App\Mail\TransactionNotice;
use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DeliverTransactionNotice implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $deliveryId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Delivery must run outside the order transaction.');
        }
        if (! app()->environment(['local', 'testing']) && ! config('services.transaction_notices.enabled')) {
            return;
        }
        $delivery = DB::transaction(function (): ?NotificationDelivery {
            $delivery = NotificationDelivery::query()->lockForUpdate()->find($this->deliveryId);
            if (! $delivery || ! in_array($delivery->status, ['pending', 'retry'], true) || $delivery->available_at->isFuture() || $delivery->attempts >= 3) {
                return null;
            }
            $delivery->update(['status' => 'sending', 'attempts' => $delivery->attempts + 1, 'claimed_at' => now()]);

            return $delivery;
        });
        if (! $delivery) {
            return;
        }
        try {
            $mailer = app()->environment('local') ? 'mailpit' : config('mail.default');
            Mail::mailer($mailer)->to($delivery->recipient)->send(new TransactionNotice($delivery->type, $delivery->snapshot, $delivery->deduplication_key));
        } catch (\Throwable $exception) {
            $log = $delivery->delivery_log;
            $log[] = ['attempt' => $delivery->attempts, 'result' => 'error', 'error_class' => $exception::class, 'at' => now()->toIso8601String()];
            $delivery->update(['status' => $delivery->attempts >= 3 ? 'failed' : 'retry', 'available_at' => now()->addMinutes($delivery->attempts), 'delivery_log' => $log]);

            return;
        }
        $log = $delivery->delivery_log;
        $log[] = ['attempt' => $delivery->attempts, 'result' => 'accepted', 'at' => now()->toIso8601String()];
        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'delivery_log' => $log]);
    }
}
