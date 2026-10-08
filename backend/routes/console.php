<?php

use App\Jobs\DataRetentionJob;
use App\Jobs\GenerateDailyReconciliationReport;
use App\Jobs\ProcessPaymentWebhook;
use App\Jobs\ReconcilePaymentAttempt;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\RefundRequest;
use App\Models\ReservationPayment;
use App\Models\ReservationRefund;
use App\Services\Refund\RefundService;
use App\Services\ReservationPaymentService;
use App\Services\ReservationRefundService;
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

Artisan::command('reconciliation:dispatch', function (): void {
    PaymentAttempt::query()
        ->where(function ($query): void {
            $query->whereIn('status', ['created', 'pending'])
                ->orWhere(function ($uncertain): void {
                    $uncertain->where('status', 'succeeded')->whereNotNull('next_reconciliation_at');
                });
        })
        ->where('created_at', '<=', now()->subMinutes(5))
        ->where(function ($query): void {
            $query->whereNull('next_reconciliation_at')->orWhere('next_reconciliation_at', '<=', now());
        })
        ->chunkById(100, function ($attempts): void {
            foreach ($attempts as $attempt) {
                ReconcilePaymentAttempt::dispatch($attempt->id);
            }
        });
})->purpose('Dispatch due provider status reconciliation jobs');

Artisan::command('reconciliation:daily-report {date?}', function (?string $date = null): void {
    GenerateDailyReconciliationReport::dispatch($date);
})->purpose('Generate the daily reconciliation report and operational alerts');

Schedule::command('reconciliation:dispatch')->everyFiveMinutes()->withoutOverlapping(10)->onOneServer();
Schedule::command('reconciliation:daily-report')->dailyAt('01:30')->withoutOverlapping(60)->onOneServer();
Schedule::job(new DataRetentionJob)->dailyAt('02:00')->withoutOverlapping(60)->onOneServer();
Schedule::command('app:backup-database --compress')->dailyAt('02:30')->withoutOverlapping(60)->onOneServer();
Schedule::call(fn () => cache()->put('health:scheduler:last_seen', now()->timestamp, now()->addMinutes(10)))
    ->name('health:scheduler-heartbeat')
    ->everyMinute()
    ->onOneServer();

Artisan::command('payments:reconcile-reservations', function (): void {
    ReservationPayment::query()->whereIn('status', ['created', 'pending', 'uncertain'])
        ->where(fn ($query) => $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subMinute()))
        ->orderBy('last_checked_at')->orderBy('id')->limit(20)->get()->each(function ($payment): void {
            try {
                app(ReservationPaymentService::class)->refresh($payment);
            } catch (Throwable) {
                $payment->update(['last_checked_at' => now()]);
                $this->warn('Status pembayaran reservasi belum dapat diperiksa: '.$payment->id);
            }
        });
})->purpose('Reconcile UMKM, lodging and culinary payments with Midtrans sandbox');
Schedule::command('payments:reconcile-reservations')->everyMinute()->withoutOverlapping(10);

Artisan::command('refunds:reconcile', function (): void {
    ReservationRefund::query()->where('status', 'processing')->orderBy('id')->limit(20)->get()->each(function ($refund): void {
        try {
            app(ReservationRefundService::class)->process($refund);
        } catch (Throwable) {
            $this->warn('Refund reservasi belum dapat diperiksa: '.$refund->id);
        }
    });
    RefundRequest::query()->where('status', 'processing')->orderBy('id')->limit(20)->get()->each(function ($refund): void {
        try {
            app(RefundService::class)->process($refund);
        } catch (Throwable) {
            $this->warn('Status refund belum dapat diperiksa: '.$refund->id);
        }
    });
})->purpose('Reconcile submitted Midtrans refunds without creating another refund');
Schedule::command('refunds:reconcile')->everyFiveMinutes()->withoutOverlapping(10);
