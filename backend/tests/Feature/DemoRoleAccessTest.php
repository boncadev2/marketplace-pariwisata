<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PilotDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DemoRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return [['admin.pilot@example.test', 'super_admin', '/dashboard'], ['owner.pilot@example.test', 'partner_owner', '/dashboard'], ['visitor.pilot@example.test', null, '/akun']];
    }

    #[DataProvider('roles')]
    public function test_demo_login_and_role_access(string $email, ?string $scope, string $redirect): void
    {
        config(['pilot.demo_password' => 'WisataDemo2026!Local']);
        $this->seed(PilotDatabaseSeeder::class);
        $this->withHeaders(['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/login']);
        $this->postJson('/api/v1/login', ['email' => $email, 'password' => 'WisataDemo2026!Local'])->assertOk()->assertJsonPath('redirect_to', $redirect);
        $user = User::where('email', $email)->firstOrFail();
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertTrue($user->hasVerifiedEmail());
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', $email)->assertJsonMissingPath('data.password');
        if ($scope !== null) {
            $this->getJson('/api/v1/dashboard/summary')->assertOk()->assertJsonPath('scope.role', $scope);
        } else {
            $this->getJson('/api/v1/dashboard/summary')->assertForbidden();
        }
        if ($scope === 'partner_owner') {
            $this->getJson('/api/v1/reconciliation/reports')->assertForbidden();
        }
        $this->getJson('/api/v1/account/orders')->assertOk();
        $this->getJson('/api/v1/account/umkm-orders')->assertOk();
        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->assertGuest('web');
    }
}
