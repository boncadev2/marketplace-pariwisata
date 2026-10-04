<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $app['config']->set('services.payment_gateway.driver', 'sandbox');
        $app['config']->set('services.midtrans.server_key', null);
        $app['config']->set('services.midtrans.production_enabled', false);
        $app['config']->set('services.midtrans.production_server_key', null);
        $app['config']->set('services.midtrans.refunds_enabled', false);
        $app['config']->set('services.refund.driver', 'sandbox');
        $app['config']->set('services.commerce.production_enabled', false);
        $app['config']->set('services.shipping.driver', 'manual');
        $app['config']->set('services.shipping.enabled', false);
        $app['config']->set('services.shipping.api_key', null);
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");
        if (! (($connection === 'sqlite' && $database === ':memory:') || ($connection === 'mysql' && $database === 'wisata_concurrency_test'))) {
            throw new \RuntimeException('Tests require SQLite :memory: or the isolated wisata_concurrency_test database. Application databases are forbidden.');
        }

        return $app;
    }

    /** @return array<string, string> */
    protected function sensitiveHeaders(User $user): array
    {
        $response = $this->actingAs($user)->postJson('/api/v1/security/confirm-password', [
            'password' => 'password',
        ])->assertOk();

        return ['X-Sensitive-Confirmation' => $response->json('confirmation_token')];
    }
}
