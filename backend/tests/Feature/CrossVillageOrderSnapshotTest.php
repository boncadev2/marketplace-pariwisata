<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Partner;
use App\Models\PaymentAttempt;
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

class CrossVillageOrderSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $partners = collect([1, 2])->map(fn () => Partner::factory()->create(['region_id' => Region::factory()->create(['type' => 'village'])->id]));
        $product = Product::factory()->create(['partner_id' => $partners[0]->id, 'type' => 'package', 'base_price' => 999999]);
        $package = TourPackage::factory()->create(['product_id' => $product->id, 'duration_days' => 1, 'meeting_point' => 'Demo',
            'pricing_mode' => 'per_person', 'minimum_participants' => 1, 'maximum_participants' => 20]);
        foreach ($partners as $i => $partner) {
            $package->crossVillagePackages()->create(['partner_id' => $partner->id, 'revenue_share_percentage' => '50.00', 'is_primary_partner' => $i === 0]);
        }
        $order = Order::factory()->create(['partner_id' => $partners[0]->id, 'total' => 10001, 'status' => 'paid']);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'name' => 'Paket snapshot', 'quantity' => 1,
            'unit_price' => 10001, 'total' => 10001, 'commission_amount' => 1000, 'snapshot' => []]);
        PaymentAttempt::factory()->create(['order_id' => $order->id, 'status' => 'succeeded', 'amount' => 10001]);
        $version = app(CrossVillageAllocationService::class)->configuration($package)['version'];
        $url = '/api/v1/orders/'.$order->public_id.'/cross-village-snapshot';

        return [$order, $package, $url, ['version' => $version, 'reason' => 'Simulasi pembagian pesanan']];
    }

    private function admin(): array
    {
        $user = User::factory()->create(['platform_role' => 'super_admin', 'email_verified_at' => now()]);
        Sanctum::actingAs($user);
        $token = str_repeat('b', 64);
        Cache::put('sensitive-confirmation:'.hash('sha256', $token), $user->id, 600);

        return ['X-Sensitive-Confirmation' => $token];
    }

    public function test_snapshot_uses_frozen_order_money_and_balances_suborders_without_finance_writes(): void
    {
        [$order, $package, $url, $payload] = $this->fixture();
        $headers = $this->admin();
        $result = $this->postJson($url, $payload, $headers)->assertOk()->assertJsonPath('data.total', 10001)
            ->assertJsonPath('data.commission_amount', 1000)->assertJsonPath('data.partner_revenue', 9001)
            ->assertJsonPath('data.allocations.0.partner_revenue', 4501)->assertJsonPath('data.simulation_only', true)->json('data');
        $this->assertSame(10001, array_sum(array_column($result['allocations'], 'subtotal')));
        $this->assertSame(1000, array_sum(array_column($result['allocations'], 'commission_amount')));
        $this->assertDatabaseCount('sub_orders', 2);
        $this->assertDatabaseHas('sub_orders', ['order_id' => $order->id, 'status' => 'simulation_only']);
        foreach (['journal_transactions', 'payout_items', 'inventory_holds'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertArrayNotHasKey('cross_village_snapshot', $order->fresh()->toArray());
    }

    public function test_retry_and_later_configuration_changes_preserve_original_snapshot(): void
    {
        [$order, $package, $url, $payload] = $this->fixture();
        $headers = $this->admin();
        $original = $this->postJson($url, $payload, $headers)->assertOk()->json('data');
        $package->crossVillagePackages()->update(['revenue_share_percentage' => '25.00']);
        $order->update(['status' => 'refunded']);
        $this->assertSame($original, $this->postJson($url, $payload, $headers)->assertOk()->json('data'));
        $this->getJson($url)->assertOk()->assertJsonPath('order_status', 'refunded')->assertJsonPath('data.shares.0.revenue_share_percentage', '50.00');
        $this->assertDatabaseCount('sub_orders', 2);
        $this->assertDatabaseCount('audit_logs', 1);
        $payload['version'] = str_repeat('f', 64);
        $this->postJson($url, $payload, $headers)->assertConflict();
    }

    #[DataProvider('invalidOrders')]
    public function test_ineligible_order_does_not_create_snapshot(string $case): void
    {
        [$order, $package, $url, $payload] = $this->fixture();
        $headers = $this->admin();
        match ($case) {
            'unpaid' => $order->update(['status' => 'pending_payment']),
            'real_provider' => $order->paymentAttempts()->update(['provider' => 'real_gateway']),
            'payment_amount' => $order->paymentAttempts()->update(['amount' => 999]),
            'payment_pending' => $order->paymentAttempts()->update(['status' => 'pending']),
            'item_total' => $order->items()->update(['total' => 999]),
            'commission' => $order->items()->update(['commission_amount' => 11000]),
            'fraction' => $order->items()->update(['commission_amount' => '1000.50']),
            'mapping' => $package->crossVillagePackages()->update(['revenue_share_percentage' => '40.00']),
        };
        if ($case === 'mapping') {
            $payload['version'] = app(CrossVillageAllocationService::class)->configuration($package)['version'];
        }
        $this->postJson($url, $payload, $headers)->assertUnprocessable();
        $this->assertDatabaseCount('sub_orders', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNull($order->fresh()->cross_village_snapshot);
    }

    public static function invalidOrders(): array
    {
        return array_map(fn ($case) => [$case], ['unpaid', 'real_provider', 'payment_amount', 'payment_pending', 'item_total', 'commission', 'fraction', 'mapping']);
    }

    public function test_stale_configuration_returns_conflict_without_writes(): void
    {
        [$order, $package, $url, $payload] = $this->fixture();
        $headers = $this->admin();
        $payload['version'] = str_repeat('f', 64);
        $this->postJson($url, $payload, $headers)->assertConflict();
        $this->assertDatabaseCount('sub_orders', 0);
    }

    public function test_admin_confirmation_and_local_environment_are_required(): void
    {
        [$order, $package, $url, $payload] = $this->fixture();
        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson($url, $payload)->assertForbidden();
        $headers = $this->admin();
        $this->postJson($url, $payload)->assertStatus(423);
        app()->detectEnvironment(fn () => 'production');
        $this->getJson($url)->assertStatus(503);
        $this->postJson($url, $payload, $headers)->assertStatus(503);
    }
}
