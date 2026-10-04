<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DeploymentHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_dependency_health_reports_healthy_dependencies_without_cacheable_details(): void
    {
        config()->set('observability.minimum_free_disk_bytes', 1);
        config()->set('observability.require_scheduler_heartbeat', true);
        Cache::put('health:scheduler:last_seen', now()->timestamp, 60);

        $response = $this->getJson('/api/v1/health/dependencies');

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson([
                'status' => 'ok',
                'checks' => [
                    'database' => true,
                    'cache' => true,
                    'queue' => true,
                    'storage' => true,
                    'scheduler' => true,
                    'failed_jobs' => true,
                ],
            ]);
    }

    public function test_stale_scheduler_heartbeat_fails_readiness(): void
    {
        config()->set('observability.minimum_free_disk_bytes', 1);
        config()->set('observability.require_scheduler_heartbeat', true);
        config()->set('observability.scheduler_stale_seconds', 60);
        Cache::put('health:scheduler:last_seen', now()->subMinutes(2)->timestamp, 60);

        $this->getJson('/api/v1/health/dependencies')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.scheduler', false);
    }

    public function test_every_response_has_a_request_id_and_preserves_a_valid_incoming_uuid(): void
    {
        $requestId = 'c0a80121-7ad8-4bd7-99b4-2bdf0afb7470';

        $this->withHeader('X-Request-ID', $requestId)
            ->getJson('/api/v1/products')
            ->assertHeader('X-Request-ID', $requestId);

        $generated = $this->withHeader('X-Request-ID', 'untrusted-value')
            ->getJson('/api/v1/products')
            ->headers->get('X-Request-ID');

        $this->assertIsString($generated);
        $this->assertNotSame('untrusted-value', $generated);
    }
}
