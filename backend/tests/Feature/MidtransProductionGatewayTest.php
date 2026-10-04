<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Payments\MidtransProductionGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransProductionGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_requires_explicit_activation_and_separate_production_key(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['services.midtrans.production_server_key' => 'Mid-server-production-test', 'services.midtrans.production_enabled' => false]);
        Http::preventStrayRequests();
        $this->assertFalse(app(MidtransProductionGateway::class)->isConfigured());
        config(['services.midtrans.production_enabled' => true, 'services.midtrans.production_server_key' => 'SB-Mid-server-sandbox-test']);
        $this->assertFalse(app(MidtransProductionGateway::class)->isConfigured());
        Http::assertNothingSent();
    }

    public function test_production_checkout_uses_production_host_and_rejects_sandbox_redirect(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');
        config(['services.midtrans.production_server_key' => 'Mid-server-production-test', 'services.midtrans.production_enabled' => true]);
        Http::preventStrayRequests();
        Http::fake(['https://app.midtrans.com/snap/v1/transactions' => Http::sequence()->push(['redirect_url' => 'https://app.midtrans.com/snap/v4/redirection/test-token'])->push(['redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/test-token'])]);
        $order = Order::factory()->create(['status' => 'pending_payment', 'currency' => 'IDR', 'total' => 100000]);
        $gateway = app(MidtransProductionGateway::class);
        $result = $gateway->createCheckout($order);
        $this->assertSame('production', $result['payload']['mode']);
        $this->assertSame('midtrans_production', $gateway->provider());
        Http::assertSent(fn ($request) => $request->url() === 'https://app.midtrans.com/snap/v1/transactions' && $request['transaction_details']['gross_amount'] === 100000);
        $this->expectException(\RuntimeException::class);
        $gateway->createCheckout($order);
    }
}
