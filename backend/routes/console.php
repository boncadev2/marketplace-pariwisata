<?php

use App\Jobs\ProcessPaymentWebhook;
use App\Models\PaymentWebhookEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inventory:release-expired-holds')->everyMinute()->withoutOverlapping();

Artisan::command('payments:recover-webhooks', function (): void {
    PaymentWebhookEvent::query()
        ->whereNull('processed_at')
        ->where('created_at', '<=', now()->subMinute())
        ->chunkById(100, function ($events): void {
            foreach ($events as $event) {
                ProcessPaymentWebhook::dispatch($event->id);
            }
        });
})->purpose('Requeue durable payment events that have not been processed');

Schedule::command('payments:recover-webhooks')->everyMinute()->withoutOverlapping();
Schedule::command('notifications:dispatch-outbox')->everyMinute()->withoutOverlapping();
