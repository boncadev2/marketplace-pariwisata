<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\Product;
use App\Models\Region;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\CrossVillageAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrossVillageAgreementTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $partners = collect([1, 2])->map(fn () => Partner::factory()->create(['region_id' => Region::factory()->create(['type' => 'village'])->id]));
        $product = Product::factory()->create(['partner_id' => $partners[0]->id, 'type' => 'package']);
        $package = TourPackage::factory()->create(['product_id' => $product->id, 'duration_days' => 1, 'meeting_point' => 'Demo',
            'pricing_mode' => 'per_person', 'minimum_participants' => 1, 'maximum_participants' => 10]);
        foreach ($partners as $i => $partner) {
            $package->crossVillagePackages()->create(['partner_id' => $partner->id, 'revenue_share_percentage' => '50.00', 'is_primary_partner' => $i === 0]);
        }

        return [$product, $package, $partners];
    }

    private function act(User $user): array
    {
        Sanctum::actingAs($user);
        $token = str_repeat('c', 64);
        Cache::put('sensitive-confirmation:'.hash('sha256', $token), $user->id, 600);

        return ['X-Sensitive-Confirmation' => $token];
    }

    private function member(int $partnerId, string $role = 'owner', bool $active = true): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        PartnerMember::factory()->create(['user_id' => $user->id, 'partner_id' => $partnerId, 'role' => $role, 'is_active' => $active]);

        return $user;
    }

    private function url(Product $product): string
    {
        return '/api/v1/products/'.$product->slug.'/cross-village-agreement';
    }

    private function payload(TourPackage $package, int $partnerId): array
    {
        return ['partner_id' => $partnerId, 'version' => app(CrossVillageAllocationService::class)->configuration($package)['version'],
            'decision' => 'accepted', 'reason' => 'Porsi simulasi telah diperiksa'];
    }

    public function test_each_partner_decides_for_itself_and_retry_does_not_duplicate_audit(): void
    {
        [$product, $package, $partners] = $this->fixture();
        foreach ($partners as $partner) {
            $headers = $this->act($this->member($partner->id));
            $payload = $this->payload($package, $partner->id);
            $this->postJson($this->url($product), $payload, $headers)->assertOk();
            $this->postJson($this->url($product), $payload, $headers)->assertOk();
        }
        $this->getJson($this->url($product))->assertOk()->assertJsonPath('data.all_accepted', true);
        $this->assertDatabaseCount('cross_village_agreements', 2);
        $this->assertDatabaseCount('audit_logs', 2);
        $payload['decision'] = 'rejected';
        $this->postJson($this->url($product), $payload, $headers)->assertOk()->assertJsonPath('data.all_accepted', false);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_decision_for_another_configuration_hash_is_not_counted_as_current_consent(): void
    {
        [$product, $package, $partners] = $this->fixture();
        $headers = $this->act($this->member($partners[0]->id));
        $this->postJson($this->url($product), $this->payload($package, $partners[0]->id), $headers)->assertOk();
        $package->crossVillagePackages()->where('partner_id', $partners[0]->id)->update(['revenue_share_percentage' => '60.00']);
        $package->crossVillagePackages()->where('partner_id', $partners[1]->id)->update(['revenue_share_percentage' => '40.00']);
        $this->getJson($this->url($product))->assertOk()->assertJsonPath('data.agreements.0.decision', 'pending');
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_staff_inactive_and_unrelated_members_cannot_decide(string $role, bool $active, bool $related): void
    {
        [$product, $package, $partners] = $this->fixture();
        $partnerId = $related ? $partners[0]->id : Partner::factory()->create()->id;
        $headers = $this->act($this->member($partnerId, $role, $active));
        $this->getJson($this->url($product))->assertNotFound();
        $this->postJson($this->url($product), $this->payload($package, $partners[0]->id), $headers)->assertNotFound();
        $this->assertDatabaseCount('cross_village_agreements', 0);
    }

    public static function unauthorizedRoles(): array
    {
        return [['staff', true, true], ['owner', false, true], ['manager', true, false]];
    }

    public function test_admin_cannot_approve_without_membership_and_owner_cannot_approve_other_partner(): void
    {
        [$product, $package, $partners] = $this->fixture();
        $headers = $this->act(User::factory()->create(['platform_role' => 'super_admin', 'email_verified_at' => now()]));
        $this->getJson($this->url($product))->assertOk();
        $this->postJson($this->url($product), $this->payload($package, $partners[0]->id), $headers)->assertNotFound();
        $headers = $this->act($this->member($partners[0]->id));
        $this->postJson($this->url($product), $this->payload($package, $partners[1]->id), $headers)->assertNotFound();
    }

    public function test_configuration_update_increments_revision_and_invalidates_prior_consent_even_when_shares_unchanged(): void
    {
        [$product, $package, $partners] = $this->fixture();
        $headers = $this->act($this->member($partners[0]->id, 'manager'));
        $payload = $this->payload($package, $partners[0]->id);
        $this->postJson($this->url($product), $payload, $headers)->assertOk();
        $configuration = app(CrossVillageAllocationService::class)->configuration($package);
        $headers = $this->act(User::factory()->create(['platform_role' => 'super_admin', 'email_verified_at' => now()]));
        $this->putJson('/api/v1/products/'.$product->slug.'/cross-village-configuration',
            ['version' => $configuration['version'], 'shares' => $configuration['shares'], 'reason' => 'Minta persetujuan ulang'], $headers)
            ->assertOk()->assertJsonPath('data.revision', 1)->assertJsonPath('data.agreements.0.decision', 'pending');
        $this->assertDatabaseCount('cross_village_agreements', 1);
        $headers = $this->act($this->member($partners[0]->id));
        $this->postJson($this->url($product), $payload, $headers)->assertConflict();
    }

    public function test_proposals_are_scoped_and_require_verified_email_and_sensitive_confirmation(): void
    {
        [$product, $package, $partners] = $this->fixture();
        $headers = $this->act($this->member($partners[0]->id));
        $this->fixture();
        $result = $this->getJson('/api/v1/partner/cross-village-proposals')->assertOk()->json('data');
        $this->assertCount(1, $result);
        $this->assertSame($product->slug, $result[0]['slug']);
        $this->postJson($this->url($product), $this->payload($package, $partners[0]->id))->assertStatus(423);
        $headers = $this->act(User::factory()->create(['email_verified_at' => null]));
        $this->getJson('/api/v1/partner/cross-village-proposals')->assertForbidden();
        app()->detectEnvironment(fn () => 'production');
        $this->getJson('/api/v1/partner/cross-village-proposals')->assertStatus(503);
    }
}
