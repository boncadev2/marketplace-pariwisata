<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Product;
use App\Models\Region;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrossVillageConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $partners = collect([1, 2])->map(fn () => Partner::factory()->create(['region_id' => Region::factory()->create(['type' => 'village'])->id]));
        $product = Product::factory()->create(['type' => 'package', 'partner_id' => $partners[0]->id]);
        $package = TourPackage::factory()->create(['product_id' => $product->id, 'duration_days' => 1, 'meeting_point' => 'Demo',
            'pricing_mode' => 'per_person', 'minimum_participants' => 1, 'maximum_participants' => 20]);
        $url = '/api/v1/products/'.$product->slug.'/cross-village-configuration';
        $shares = $partners->map(fn ($partner, $index) => ['partner_id' => $partner->id, 'revenue_share_percentage' => '50.00', 'is_primary_partner' => $index === 0])->all();

        return [$url, $package, $shares];
    }

    private function admin(): array
    {
        $user = User::factory()->create(['platform_role' => 'super_admin', 'email_verified_at' => now()]);
        Sanctum::actingAs($user);
        $token = str_repeat('a', 64);
        Cache::put('sensitive-confirmation:'.hash('sha256', $token), $user->id, 600);

        return ['X-Sensitive-Confirmation' => $token];
    }

    public function test_valid_update_is_audited_and_stale_version_cannot_overwrite_it(): void
    {
        [$url, $package, $shares] = $this->fixture();
        $headers = $this->admin();
        $initial = $this->getJson($url)->assertOk()->json('data');
        $this->assertCount(2, $initial['candidates']);
        $payload = ['version' => $initial['version'], 'reason' => 'Simulasi kesepakatan mitra', 'shares' => $shares];
        $this->putJson($url, $payload, $headers)->assertOk()->assertJsonPath('data.shares.0.revenue_share_percentage', '50.00');
        $this->assertDatabaseCount('cross_village_packages', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cross_village.configuration_updated', 'auditable_id' => $package->id]);
        $this->putJson($url, $payload, $headers)->assertConflict();
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invalid_total_rolls_back_existing_mapping_and_audit(): void
    {
        [$url, $package, $shares] = $this->fixture();
        $package->crossVillagePackages()->createMany($shares);
        $headers = $this->admin();
        $version = $this->getJson($url)->json('data.version');
        $shares[0]['revenue_share_percentage'] = '40.00';
        $this->putJson($url, ['version' => $version, 'reason' => 'Uji porsi tidak valid', 'shares' => $shares], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('allocation');
        $this->assertDatabaseHas('cross_village_packages', ['partner_id' => $shares[0]['partner_id'], 'revenue_share_percentage' => 50]);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame($version, $this->getJson($url)->json('data.version'));
    }

    public function test_missing_partner_duplicate_and_unapproved_partner_are_rejected(): void
    {
        [$url, $package, $shares] = $this->fixture();
        $headers = $this->admin();
        $version = $this->getJson($url)->json('data.version');
        $payload = ['version' => $version, 'reason' => 'Uji kelayakan mitra', 'shares' => $shares];
        $payload['shares'][1]['partner_id'] = $shares[0]['partner_id'];
        $this->putJson($url, $payload, $headers)->assertUnprocessable();
        $payload['shares'][1]['partner_id'] = 999999;
        $this->putJson($url, $payload, $headers)->assertUnprocessable();
        Partner::findOrFail($shares[1]['partner_id'])->update(['status' => 'pending']);
        $payload['shares'] = $shares;
        $this->putJson($url, $payload, $headers)->assertUnprocessable();
        $this->assertDatabaseCount('cross_village_packages', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_and_sensitive_confirmation_are_required(): void
    {
        [$url] = $this->fixture();
        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();
        $this->putJson($url, [])->assertForbidden();
        $this->admin();
        $this->putJson($url, [])->assertStatus(423);
    }

    public function test_configuration_is_disabled_in_production(): void
    {
        [$url] = $this->fixture();
        $headers = $this->admin();
        app()->detectEnvironment(fn () => 'production');
        $this->getJson($url)->assertStatus(503);
        $this->putJson($url, [], $headers)->assertStatus(503);
    }
}
