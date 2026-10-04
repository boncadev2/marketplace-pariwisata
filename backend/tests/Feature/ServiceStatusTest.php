<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ServiceStatusTest extends TestCase
{
    public function test_status_hides_credentials_and_does_not_contact_providers(): void
    {
        config(['services.payment_gateway.driver' => 'midtrans_sandbox', 'services.midtrans.server_key' => 'SB-Mid-server-private', 'services.refund.driver' => 'midtrans_sandbox', 'services.midtrans.refunds_enabled' => true, 'services.shipping.driver' => 'biteship', 'services.shipping.enabled' => true, 'services.shipping.api_key' => 'private-biteship-key', 'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.username' => 'private-user', 'mail.mailers.smtp.password' => 'private-password', 'mail.from.address' => 'sender@example.test']);
        Http::preventStrayRequests();
        Mail::fake();
        $this->assertSame(0, Artisan::call('app:services-status'));
        $output = Artisan::output();
        $this->assertStringContainsString('Terisi', $output);
        foreach (['SB-Mid-server-private', 'private-biteship-key', 'private-user', 'private-password', 'smtp.example.test', 'sender@example.test'] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_disabled_or_incomplete_services_are_reported_without_activation(): void
    {
        config(['services.payment_gateway.driver' => 'midtrans_production', 'services.midtrans.production_server_key' => 'Mid-server-present-but-disabled', 'services.midtrans.production_enabled' => false]);
        $this->artisan('app:services-status')->expectsOutputToContain('Belum lengkap')->assertSuccessful();
        $this->assertFalse(config('services.midtrans.production_enabled'));
    }
}
