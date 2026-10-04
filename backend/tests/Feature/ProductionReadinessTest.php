<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProductionReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_requires_admin_and_never_exposes_secrets(): void
    {
        config(['services.midtrans.production_server_key' => 'Mid-server-secret-production', 'services.shipping.api_key' => 'private-shipping-key']);
        $this->getJson('/api/v1/dashboard/production-readiness')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/dashboard/production-readiness')->assertForbidden();
        $response = $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']))->getJson('/api/v1/dashboard/production-readiness')->assertOk()->assertJsonPath('data.ready', false);
        $this->assertStringNotContainsString('Mid-server-secret-production', $response->getContent());
        $this->assertStringNotContainsString('private-shipping-key', $response->getContent());
        $this->artisan('app:production-readiness --strict')->assertExitCode(1);
    }

    public function test_local_environment_cannot_be_declared_production_ready(): void
    {
        $report = app(ProductionReadinessService::class)->report();
        $this->assertFalse($report['ready']);
        $this->assertFalse($report['checks'][0]['ready']);
    }
}
