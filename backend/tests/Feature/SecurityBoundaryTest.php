<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_endpoints_reject_anonymous_requests(): void
    {
        $this->getJson('/api/v1/payouts/eligible')->assertUnauthorized();
        $this->postJson('/api/v1/reconciliation', [])->assertUnauthorized();
        $this->getJson('/api/v1/partner-bank-accounts?partner_id=1')->assertUnauthorized();
    }

    public function test_only_super_admin_can_access_platform_finance(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/payouts/eligible')->assertForbidden();
    }

    public function test_sensitive_action_requires_recent_password_confirmation(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);

        $this->actingAs($admin)->postJson('/api/v1/reconciliation', [])->assertStatus(423);

        $confirmation = $this->postJson('/api/v1/security/confirm-password', ['password' => 'password'])
            ->assertOk();

        $this->withHeader('X-Sensitive-Confirmation', $confirmation->json('confirmation_token'))
            ->postJson('/api/v1/reconciliation', [])->assertUnprocessable();
    }
}
