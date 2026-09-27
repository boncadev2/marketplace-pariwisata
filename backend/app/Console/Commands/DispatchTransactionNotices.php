<?php

namespace App\Console\Commands;

use App\Jobs\DeliverTransactionNotice;
use App\Models\NotificationDelivery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('notifications:dispatch-outbox {--inspect : Show recent delivery metadata without dispatching}')]
#[Description('Queue committed transaction notices and quarantine interrupted deliveries')]
class DispatchTransactionNotices extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('inspect')) {
            $this->table(['ID', 'Order', 'Type', 'Status', 'Attempts', 'Sent at'], NotificationDelivery::query()->latest('id')->limit(50)->get()->map(fn ($delivery): array => [$delivery->id, $delivery->order_id, $delivery->type, $delivery->status, $delivery->attempts, $delivery->sent_at?->toIso8601String()])->all());

            return self::SUCCESS;
        }
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Outbox polling must run outside a transaction.');
        }
        if (! app()->environment(['local', 'testing']) && ! config('services.transaction_notices.enabled')) {
            return self::SUCCESS;
        }
        NotificationDelivery::query()->where('status', 'sending')->where('claimed_at', '<=', now()->subMinutes(5))->chunkById(100, function ($deliveries): void {
            foreach ($deliveries as $candidate) {
                DB::transaction(function () use ($candidate): void {
                    $delivery = NotificationDelivery::query()->lockForUpdate()->findOrFail($candidate->id);
                    if ($delivery->status !== 'sending' || $delivery->claimed_at->gt(now()->subMinutes(5))) {
                        return;
                    }
                    $log = $delivery->delivery_log;
                    $log[] = ['attempt' => $delivery->attempts, 'result' => 'uncertain', 'at' => now()->toIso8601String()];
                    $delivery->update(['status' => 'uncertain', 'delivery_log' => $log]);
                });
            }
        });
        NotificationDelivery::query()->whereIn('status', ['pending', 'retry'])->where('available_at', '<=', now())->where('attempts', '<', 3)->chunkById(100, function ($deliveries): void {
            foreach ($deliveries as $delivery) {
                DeliverTransactionNotice::dispatch($delivery->id);
            }
        });

        return self::SUCCESS;
    }
}
