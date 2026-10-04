<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CommissionRule;
use App\Models\Coupon;
use App\Models\CrossVillageAgreement;
use App\Models\InventoryBucket;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Region;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\CrossVillageAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrossVillageCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $partners = collect([1, 2])->map(fn () => Partner::factory()->create(['region_id' => Region::factory()->create(['type' => 'village'])->id]));
        $product = Product::factory()->create(['partner_id' => $partners[0]->id, 'type' => 'package', 'base_price' => 10001]);
        $package = TourPackage::factory()->create(['product_id' => $product->id, 'duration_days' => 1, 'meeting_point' => 'Demo',
            'pricing_mode' => 'per_person', 'minimum_participants' => 1, 'maximum_participants' => 10, 'status' => 'published']);
        foreach ($partners as $index => $partner) {
            $package->crossVillagePackages()->create(['partner_id' => $partner->id, 'revenue_share_percentage' => '50.00', 'is_primary_partner' => $index === 0]);
        }
        $version = app(CrossVillageAllocationService::class)->configuration($package)['version'];
        foreach ($partners as $partner) {
            CrossVillageAgreement::create(['tour_package_id' => $package->id, 'partner_id' => $partner->id, 'revision' => 0,
                'configuration_version' => $version, 'decision' => 'accepted', 'reason' => 'Persetujuan fixture sandbox', 'decided_at' => now()]);
        }
        CommissionRule::create(['partner_id' => $product->partner_id, 'percentage_rate' => 10, 'fixed_amount' => 0, 'effective_from' => now()->subDay()]);
        $date = now()->addDay()->toDateString();
        $bucket = InventoryBucket::factory()->create(['product_id' => $product->id, 'service_date' => $date, 'session_key' => 'default', 'capacity' => 10]);
        $payload = ['product_slug' => $product->slug, 'visit_date' => $date, 'quantity' => 1, 'customer_name' => 'Pelanggan sandbox',
            'customer_email' => 'sandbox@example.test', 'cross_village_version' => $version, 'expected_total' => 10001];

        return [$product, $package, $bucket, $payload];
    }

    public function test_checkout_freezes_accepted_shares_commission_and_suborders_and_retry_preserves_them(): void
    {
        [$product, $package, $bucket, $payload] = $this->fixture();
        $headers = ['Idempotency-Key' => 'cross-village-checkout-test-001'];
        $this->getJson('/api/v1/products/'.$product->slug.'/quote?visit_date='.$payload['visit_date'].'&quantity=1')
            ->assertOk()->assertJsonPath('data.cross_village.version', $payload['cross_village_version'])->assertJsonPath('data.cross_village.all_accepted', true);
        $this->postJson('/api/v1/checkout', $payload, $headers)->assertCreated()->assertJsonPath('data.total', 10001)
            ->assertJsonPath('data.cross_village.basis', 'configuration_at_checkout');
        $order = Order::firstOrFail();
        $snapshot = $order->cross_village_snapshot;
        $this->assertTrue($snapshot['all_accepted']);
        $this->assertSame(9001, $snapshot['partner_revenue']);
        $this->assertSame(4501, $snapshot['allocations'][0]['partner_revenue']);
        $this->assertDatabaseCount('sub_orders', 2);
        $package->increment('cross_village_revision');
        $package->crossVillagePackages()->update(['revenue_share_percentage' => '20.00']);
        $this->postJson('/api/v1/checkout', $payload, $headers)->assertOk();
        $this->assertSame($snapshot, $order->fresh()->cross_village_snapshot);
        $this->assertSame(1, $bucket->fresh()->held);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertSame(1, AuditLog::where('action', 'cross_village.checkout_snapshot_created')->count());
        $payload['cross_village_version'] = str_repeat('f', 64);
        $this->postJson('/api/v1/checkout', $payload, $headers)->assertConflict();
    }

    public function test_coupon_snapshot_uses_net_total_and_failed_snapshot_does_not_consume_quota(): void
    {
        [$product, $package, $bucket, $payload] = $this->fixture();
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);
        $coupon = Coupon::factory()->create(['discount_type' => 'fixed', 'discount_value' => 1000, 'minimum_spend' => 0]);
        $payload['customer_email'] = $user->email;
        $payload['coupon_code'] = $coupon->code;
        $payload['expected_total'] = 9001;
        CommissionRule::where('partner_id', $product->partner_id)->update(['percentage_rate' => 200]);
        $this->postJson('/api/v1/checkout', $payload, ['Idempotency-Key' => 'cross-village-coupon-fail-0001'])->assertUnprocessable();
        $this->assertSame(0, $coupon->fresh()->used_quota);
        $this->assertDatabaseCount('coupon_redemptions', 0);
        CommissionRule::where('partner_id', $product->partner_id)->update(['percentage_rate' => 10]);
        $this->postJson('/api/v1/checkout', $payload, ['Idempotency-Key' => 'cross-village-coupon-pass-0001'])->assertCreated();
        $snapshot = Order::firstOrFail()->cross_village_snapshot;
        $this->assertSame(9001, $snapshot['total']);
        $this->assertSame(900, $snapshot['commission_amount']);
        $this->assertSame(8101, $snapshot['partner_revenue']);
        $this->assertSame(1, $coupon->fresh()->used_quota);
    }

    public function test_missing_or_rejected_consent_blocks_order_and_stock_hold(): void
    {
        [$product, $package, $bucket, $payload] = $this->fixture();
        CrossVillageAgreement::firstOrFail()->update(['decision' => 'rejected']);
        $this->postJson('/api/v1/checkout', $payload, ['Idempotency-Key' => 'cross-village-rejected-0001'])->assertConflict();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_holds', 0);
        $this->assertSame(0, $bucket->fresh()->held);
    }

    public function test_stale_revision_and_changed_price_are_rejected_before_inventory(): void
    {
        [$product, $package, $bucket, $payload] = $this->fixture();
        $product->update(['base_price' => 20000]);
        $this->postJson('/api/v1/checkout', $payload, ['Idempotency-Key' => 'cross-village-price-0001'])->assertConflict();
        $payload['expected_total'] = 20000;
        $package->increment('cross_village_revision');
        $this->postJson('/api/v1/checkout', $payload, ['Idempotency-Key' => 'cross-village-revision-0001'])->assertConflict();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('sub_orders', 0);
    }

    public function test_invalid_commission_rolls_back_order_snapshot_audit_and_stock(): void
    {
        [$product, $package, $bucket, $payload] = $this->fixture();
        CommissionRule::where('partner_id', $product->partner_id)->update(['percentage_rate' => 200]);
        $this->postJson('/api/v1/checkout', $payload, ['Idempotency-Key' => 'cross-village-commission-0001'])->assertUnprocessable();
        foreach (['orders', 'inventory_holds', 'sub_orders', 'audit_logs', 'payment_attempts'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(0, $bucket->fresh()->held);
    }

    public function test_unquoted_collaboration_checkout_and_production_are_blocked(): void
    {
        [$product, $package, $bucket, $payload] = $this->fixture();
        $withoutVersion = $payload;
        unset($withoutVersion['cross_village_version']);
        $this->postJson('/api/v1/checkout', $withoutVersion, ['Idempotency-Key' => 'cross-village-noquote-0001'])->assertUnprocessable();
        app()->detectEnvironment(fn () => 'production');
        $this->postJson('/api/v1/checkout', $payload, ['Idempotency-Key' => 'cross-village-production-0001'])->assertStatus(503);
        $this->assertDatabaseCount('orders', 0);
    }
}
