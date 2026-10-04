<?php

namespace App\Jobs;

use App\Models\PaymentAttempt;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use App\Services\ReconciliationService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

class ReconcilePaymentAttempt implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    public int $timeout = 30;

    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $paymentAttemptId) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new RateLimited('payment-reconciliation'))->releaseAfter(60)];
    }

    public function uniqueId(): string
    {
        return (string) $this->paymentAttemptId;
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentGateway $paymentGateway, ReconciliationService $reconciliationService): void
    {
        $paymentAttempt = PaymentAttempt::query()->find($this->paymentAttemptId);
        if ($paymentAttempt === null) {
            return;
        }

        if ($paymentAttempt->provider !== app(PaymentGatewayManager::class)->driver()) {
            $paymentGateway = app(PaymentGatewayManager::class)->forProvider($paymentAttempt->provider);
        }
        $providerStatus = $paymentGateway->fetchPaymentStatus($paymentAttempt);
        $reconciliationService->reconcilePaymentAttempt($paymentAttempt, $providerStatus);
    }

    public function failed(?Throwable $exception): void
    {
        PaymentAttempt::query()->whereKey($this->paymentAttemptId)->update([
            'reconciliation_error' => mb_substr($exception?->getMessage() ?? 'Reconciliation job failed.', 0, 2000),
            'next_reconciliation_at' => now()->addHour(),
        ]);
    }
}
