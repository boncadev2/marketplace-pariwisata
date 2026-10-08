<?php

namespace App\Providers;

use App\Payments\MidtransProductionGateway;
use App\Payments\MidtransSandboxGateway;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use App\Services\Refund\MidtransRefundAdapter;
use App\Services\Refund\RefundAdapterInterface;
use App\Services\Refund\SandboxRefundAdapter;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MidtransSandboxGateway::class, fn () => config('services.payment_gateway.driver') === 'midtrans_production' ? app(MidtransProductionGateway::class) : new MidtransSandboxGateway);
        $this->app->bind(RefundAdapterInterface::class, fn () => match (config('services.refund.driver')) {
            'midtrans_sandbox', 'midtrans_production' => app(MidtransRefundAdapter::class),
            default => app(SandboxRefundAdapter::class),
        });
        $this->app->bind(PaymentGateway::class, fn () => app(PaymentGatewayManager::class)->forProvider(app(PaymentGatewayManager::class)->driver()));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = rtrim((string) config('services.frontend_url', env('FRONTEND_URL', 'http://localhost:8080')), '/');
            return "{$frontendUrl}/reset-password?token={$token}&email=".urlencode($notifiable->getEmailForPasswordReset());
        });

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(6)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('sensitive-confirmation', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(($request->user()?->id ?? 'guest').'|'.$request->ip()));
        RateLimiter::for('checkout', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('uploads', fn (Request $request): Limit => Limit::perMinute(10)
            ->by(($request->user()?->id ?? 'guest').'|'.$request->ip()));
        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('privacy', fn (Request $request): Limit => Limit::perHour(3)
            ->by(($request->user()?->id ?? 'guest').'|'.$request->ip()));

        RateLimiter::for('payment-reconciliation', fn (): Limit => Limit::perMinute(
            (int) config('services.payment_reconciliation.requests_per_minute', 30)
        )->by('payment-provider-status'));
    }
}
