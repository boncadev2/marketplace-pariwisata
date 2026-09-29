<?php

namespace App\Providers;

use App\Services\Refund\RefundAdapterInterface;
use App\Services\Refund\SandboxRefundAdapter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RefundAdapterInterface::class, SandboxRefundAdapter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
