<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Destination;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherRedemptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_redeems_group_once_and_token_is_not_serialized(): void
    {
        [$voucher, $staff] = $this->voucher();

        $this->actingAs($staff)->postJson('/api/v1/staff/vouchers/redeem', ['token' => str_repeat('a', 48)])->assertOk()->assertJsonPath('data.used_admissions', 2);
        $this->actingAs($staff)->postJson('/api/v1/staff/vouchers/redeem', ['token' => str_repeat('a', 48)])->assertConflict();

        $this->assertSame(2, $voucher->fresh()->used_admissions);
        $this->assertSame($staff->id, $voucher->fresh()->redeemed_by);
        $this->assertArrayNotHasKey('token', $voucher->toArray());
    }

    public function test_other_partner_staff_cannot_redeem(): void
    {
        [$voucher] = $this->voucher();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->postJson('/api/v1/staff/vouchers/redeem', ['token' => str_repeat('a', 48)])->assertNotFound();

        $this->assertSame(0, $voucher->fresh()->used_admissions);
    }

    public function test_refunded_order_voucher_is_rejected(): void
    {
        [$voucher, $staff, $order] = $this->voucher();
        $order->update(['status' => 'refunded']);

        $this->actingAs($staff)->postJson('/api/v1/staff/vouchers/redeem', ['token' => str_repeat('a', 48)])->assertConflict();

        $this->assertSame(0, $voucher->fresh()->used_admissions);
    }

    public function test_member_without_staff_role_cannot_redeem(): void
    {
        [$voucher, $staff] = $this->voucher();
        PartnerMember::where('user_id', $staff->id)->update(['role' => 'viewer']);

        $this->actingAs($staff)->postJson('/api/v1/staff/vouchers/redeem', ['token' => str_repeat('a', 48)])->assertNotFound();

        $this->assertSame(0, $voucher->fresh()->used_admissions);
    }

    public function test_staff_without_assignment_to_product_destination_is_rejected(): void
    {
        [$voucher, $staff] = $this->voucher();
        $product = Product::firstOrFail();
        $category = Category::create(['name' => 'Test', 'slug' => 'location-test']);
        $destination = Destination::create(['partner_id' => $product->partner_id, 'region_id' => Region::firstOrFail()->id, 'category_id' => $category->id, 'name' => 'Lokasi', 'slug' => 'location-test']);
        $product->update(['destination_id' => $destination->id]);

        $this->actingAs($staff)->postJson('/api/v1/staff/vouchers/redeem', ['token' => str_repeat('a', 48)])->assertNotFound();

        $this->assertSame(0, $voucher->fresh()->used_admissions);
        $this->assertDatabaseCount('voucher_check_ins', 0);
    }

    public function test_admin_date_override_records_reason_and_actor(): void
    {
        [$voucher, $staff] = $this->voucher();
        $staff->forceFill(['platform_role' => 'super_admin'])->save();
        $voucher->update(['service_date' => now()->subDay()->toDateString()]);

        $this->actingAs($staff)->postJson('/api/v1/staff/vouchers/redeem', ['token' => str_repeat('a', 48), 'override_reason' => 'Jadwal dipindahkan oleh pengelola.'])->assertOk();

        $this->assertDatabaseHas('voucher_check_ins', ['voucher_id' => $voucher->id, 'user_id' => $staff->id, 'admissions' => 2, 'override_reason' => 'Jadwal dipindahkan oleh pengelola.']);
    }

    public function test_staff_cannot_request_admin_override(): void
    {
        [$voucher, $staff] = $this->voucher();

        $this->actingAs($staff)->postJson('/api/v1/staff/vouchers/redeem', ['token' => str_repeat('a', 48), 'override_reason' => 'Meminta pengecualian tanggal.'])->assertForbidden();

        $this->assertSame(0, $voucher->fresh()->used_admissions);
        $this->assertDatabaseCount('voucher_check_ins', 0);
    }

    public function test_staff_can_check_voucher_details(): void
    {
        [$voucher, $staff] = $this->voucher();

        $response = $this->actingAs($staff)->postJson('/api/v1/staff/vouchers/check', ['token' => str_repeat('a', 48)]);

        $response->assertOk()
            ->assertJsonPath('data.admissions', 2)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_today', true);
    }

    public function test_super_admin_can_redeem_without_partner_membership(): void
    {
        [$voucher] = $this->voucher();
        $admin = User::factory()->create(['platform_role' => 'super_admin']);

        $this->actingAs($admin)->postJson('/api/v1/staff/vouchers/redeem', ['token' => str_repeat('a', 48)])->assertOk();

        $this->assertSame(2, $voucher->fresh()->used_admissions);
    }

    private function voucher(): array
    {
        $this->freezeTime();
        $region = Region::create(['code' => 'VOUCH-01', 'name' => 'Test', 'type' => 'regency']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Test', 'slug' => 'voucher-partner', 'status' => 'approved']);
        $product = Product::create(['partner_id' => $partner->id, 'name' => 'Tiket', 'slug' => 'voucher-product', 'type' => 'ticket']);
        $order = Order::create(['public_id' => fake()->uuid(), 'partner_id' => $partner->id, 'idempotency_key' => 'voucher-order', 'guest_access_hash' => 'hash', 'customer_name' => 'Test', 'customer_email' => 'test@example.test', 'status' => 'paid', 'currency' => 'IDR', 'total' => 100, 'policy_snapshot' => []]);
        $item = $order->items()->create(['product_id' => $product->id, 'name' => 'Tiket', 'quantity' => 2, 'unit_price' => 50, 'total' => 100, 'snapshot' => []]);
        $voucher = Voucher::create(['order_item_id' => $item->id, 'partner_id' => $partner->id, 'token_hash' => hash('sha256', str_repeat('a', 48)), 'token' => str_repeat('a', 48), 'service_date' => now('Asia/Jakarta')->toDateString(), 'admissions' => 2]);
        $staff = User::factory()->create();
        PartnerMember::create(['partner_id' => $partner->id, 'user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);

        return [$voucher->fresh(), $staff, $order];
    }
}
