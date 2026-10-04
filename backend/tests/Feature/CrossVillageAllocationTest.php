<?php

namespace Tests\Feature;

use App\Models\CommissionRule;
use App\Models\CrossVillagePackage;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Region;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\CrossVillageAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrossVillageAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_uses_server_price_and_commission_without_creating_transactions(): void
    {
        $package = $this->package();
        CommissionRule::create(['partner_id' => $package->product->partner_id, 'percentage_rate' => 10,
            'fixed_amount' => 0, 'effective_from' => now()->subDay()]);
        $this->admin();
        $this->getJson($this->url($package))->assertOk()->assertJsonPath('data.total', 10001)
            ->assertJsonPath('data.commission_amount', 1000)->assertJsonPath('data.partner_revenue', 9001)
            ->assertJsonPath('data.allocations.0.partner_revenue', 4501)
            ->assertJsonPath('data.allocations.1.partner_revenue', 4500)
            ->assertJsonPath('data.simulation_only', true)->assertHeader('Cache-Control', 'no-store, private');
        foreach (['orders', 'sub_orders', 'payout_items', 'inventory_holds'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_largest_remainder_is_awarded_before_partner_id_tiebreak(): void
    {
        $package = $this->package();
        $shares = $package->crossVillagePackages()->orderBy('partner_id')->get();
        $shares[0]->update(['revenue_share_percentage' => '33.33']);
        $shares[1]->update(['revenue_share_percentage' => '66.67']);
        $result = app(CrossVillageAllocationService::class)->allocate($package, 2);
        $this->assertSame([1, 1], array_column($result, 'partner_revenue'));
        $this->assertSame([0, 0], array_column(app(CrossVillageAllocationService::class)->allocate($package, 0), 'partner_revenue'));
    }

    #[DataProvider('invalidMappings')]
    public function test_invalid_mapping_is_rejected(string $case): void
    {
        $package = $this->package();
        $shares = $package->crossVillagePackages()->orderBy('partner_id')->get();
        match ($case) {
            'total' => $shares[0]->update(['revenue_share_percentage' => '49.99']),
            'zero' => $shares[0]->update(['revenue_share_percentage' => '0']),
            'primary' => $shares[1]->update(['is_primary_partner' => true]),
            'owner' => $package->product->update(['partner_id' => $shares[1]->partner_id]),
            'unapproved' => $shares[1]->partner->update(['status' => 'pending']),
            'deleted' => $shares[1]->partner->delete(),
            'same_village' => $shares[1]->partner->update(['region_id' => $shares[0]->partner->region_id]),
            'regency' => Region::findOrFail($shares[1]->partner->region_id)->update(['type' => 'regency']),
            'duplicate' => CrossVillagePackage::create($shares[0]->only(['tour_package_id', 'partner_id', 'revenue_share_percentage', 'is_primary_partner'])),
            'single' => $shares[1]->delete(),
        };
        $this->admin();
        $this->getJson($this->url($package))->assertUnprocessable()->assertJsonValidationErrors('allocation');
    }

    public static function invalidMappings(): array
    {
        return array_map(fn ($case) => [$case], ['total', 'zero', 'primary', 'owner', 'unapproved', 'deleted', 'same_village', 'regency', 'duplicate', 'single']);
    }

    public function test_access_requires_verified_platform_admin(): void
    {
        $package = $this->package();
        $this->getJson($this->url($package))->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($this->url($package))->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['platform_role' => 'super_admin', 'email_verified_at' => null]));
        $this->getJson($this->url($package))->assertForbidden();
    }

    public function test_production_simulation_is_disabled(): void
    {
        $package = $this->package();
        $this->admin();
        app()->detectEnvironment(fn () => 'production');
        $this->getJson($this->url($package))->assertStatus(503);
    }

    public function test_draft_package_and_invalid_quantity_are_rejected(): void
    {
        $package = $this->package();
        $this->admin();
        $this->getJson($this->url($package).'&quantity=0')->assertUnprocessable();
        $package->update(['status' => 'draft']);
        $this->getJson($this->url($package))->assertNotFound();
    }

    public function test_negative_revenue_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(CrossVillageAllocationService::class)->allocate($this->package(), -1);
    }

    public function test_package_participant_bounds_and_pricing_mode_are_enforced(): void
    {
        $package = $this->package();
        $this->admin();
        $package->update(['minimum_participants' => 2]);
        $this->getJson($this->url($package))->assertUnprocessable();
        $package->update(['minimum_participants' => 1, 'maximum_participants' => 1]);
        $this->getJson($this->url($package).'&quantity=2')->assertUnprocessable();
        $package->update(['pricing_mode' => 'per_group']);
        $this->getJson($this->url($package))->assertUnprocessable();
    }

    private function admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['platform_role' => 'super_admin', 'email_verified_at' => now()]));
    }

    private function url(TourPackage $package): string
    {
        return '/api/v1/products/'.$package->product->slug.'/cross-village-quote?visit_date='.now()->addDay()->toDateString().'&quantity=1';
    }

    private function package(): TourPackage
    {
        $partners = collect([1, 2])->map(fn () => Partner::factory()->create(['region_id' => Region::factory()->create(['type' => 'village'])->id]));
        $product = Product::factory()->create(['partner_id' => $partners[0]->id, 'type' => 'package', 'base_price' => 10001]);
        $package = TourPackage::factory()->create(['product_id' => $product->id, 'departure_type' => 'fixed', 'duration_days' => 1,
            'meeting_point' => 'Demo', 'pricing_mode' => 'per_person', 'minimum_participants' => 1, 'maximum_participants' => 100, 'status' => 'published']);
        foreach ($partners as $index => $partner) {
            CrossVillagePackage::create(['tour_package_id' => $package->id, 'partner_id' => $partner->id,
                'revenue_share_percentage' => '50.00', 'is_primary_partner' => $index === 0]);
        }

        return $package;
    }
}
