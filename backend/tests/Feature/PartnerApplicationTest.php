<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Region;
use App\Models\User;
use App\Support\ServiceManagementAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.partner_self_registration' => true]);
    }

    private function payload(): array
    {
        return ['region_id' => Region::factory()->create(['is_active' => true])->id, 'name' => 'Usaha Pengujian', 'contact_phone' => '081234567890'];
    }

    private function confirmation(User $admin): string
    {
        return $this->actingAs($admin)->postJson('/api/v1/security/confirm-password', ['password' => 'password'])->assertOk()->json('confirmation_token');
    }

    public function test_verified_applicant_creates_pending_owner_without_privilege_and_duplicate_returns_409(): void
    {
        $user = User::factory()->create();
        $payload = $this->payload();
        $response = $this->actingAs($user)->postJson('/api/v1/partner-applications', [...$payload, 'status' => 'approved', 'role' => 'super_admin', 'contact_email' => 'attacker@example.test', 'slug' => 'injected'])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.contact_email', $user->email);
        $id = $response->json('data.id');
        $this->assertDatabaseHas('partner_members', ['partner_id' => $id, 'user_id' => $user->id, 'role' => 'owner', 'is_active' => false]);
        $this->assertSame([], ServiceManagementAccess::partnerIds($user));
        $this->assertDatabaseHas('audit_logs', ['partner_id' => $id, 'action' => 'partner.application_submitted']);
        $this->getJson('/api/v1/partner-applications')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/partner-applications', $payload)->assertConflict();
        $this->assertDatabaseCount('partners', 1);
        $this->assertDatabaseCount('partner_members', 1);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/partner-applications')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_guest_unverified_and_closed_registration_cannot_create_partner(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/v1/partner-applications', $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->unverified()->create())->postJson('/api/v1/partner-applications', $payload)->assertForbidden();
        config(['app.partner_self_registration' => false]);
        $this->actingAs(User::factory()->create())->postJson('/api/v1/partner-applications', $payload)->assertForbidden();
        $this->assertDatabaseCount('partners', 0);
    }

    public function test_invalid_contact_and_inactive_region_return_422_without_records(): void
    {
        $region = Region::factory()->create(['is_active' => false]);
        $this->actingAs(User::factory()->create())->postJson('/api/v1/partner-applications', ['region_id' => $region->id, 'name' => 'X', 'contact_phone' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors(['region_id', 'name', 'contact_phone']);
        $this->assertDatabaseCount('partners', 0);
    }

    public function test_only_verified_admin_can_approve_and_activate_owner_once(): void
    {
        $owner = User::factory()->create();
        $id = $this->actingAs($owner)->postJson('/api/v1/partner-applications', $this->payload())->assertCreated()->json('data.id');
        $path = '/api/v1/dashboard/partner-applications/'.$id.'/decision';
        $this->getJson('/api/v1/dashboard/partner-applications')->assertForbidden();
        $this->postJson($path, ['decision' => 'approved'])->assertForbidden();
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $this->actingAs($admin)->postJson($path, ['decision' => 'approved'])->assertStatus(423);
        $token = $this->confirmation($admin);
        $this->withHeader('X-Sensitive-Confirmation', $token)->postJson($path, ['decision' => 'approved'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->postJson($path, ['decision' => 'approved'])->assertOk();
        $this->assertDatabaseHas('partner_members', ['partner_id' => $id, 'user_id' => $owner->id, 'is_active' => true]);
        $this->assertSame([$id], ServiceManagementAccess::partnerIds($owner));
        $this->assertSame(1, AuditLog::where('action', 'partner.application_reviewed')->count());
        $this->postJson($path, ['decision' => 'rejected', 'reason' => 'Keputusan berbeda'])->assertConflict();
        $this->actingAs(User::factory()->unverified()->create(['platform_role' => 'super_admin']))->getJson('/api/v1/dashboard/partner-applications')->assertForbidden();
    }

    public function test_rejection_requires_reason_and_leaves_owner_inactive(): void
    {
        $owner = User::factory()->create();
        $id = $this->actingAs($owner)->postJson('/api/v1/partner-applications', $this->payload())->json('data.id');
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $token = $this->confirmation($admin);
        $path = '/api/v1/dashboard/partner-applications/'.$id.'/decision';
        $this->withHeader('X-Sensitive-Confirmation', $token)->postJson($path, ['decision' => 'rejected'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($path, ['decision' => 'rejected', 'reason' => 'Lengkapi data usaha.'])->assertOk()->assertJsonPath('data.review_reason', 'Lengkapi data usaha.');
        $this->assertDatabaseHas('partners', ['id' => $id, 'status' => 'rejected']);
        $this->assertDatabaseHas('partner_members', ['partner_id' => $id, 'is_active' => false]);
        $this->actingAs($owner)->getJson('/api/v1/partner-applications')->assertOk()->assertJsonPath('data.0.review_reason', 'Lengkapi data usaha.');
    }
}
