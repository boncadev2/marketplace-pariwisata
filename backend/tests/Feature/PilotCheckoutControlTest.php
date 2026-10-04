<?php

namespace Tests\Feature;

use App\Models\InventoryBucket;
use App\Models\PilotControl;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotCheckoutControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_status_uses_safe_environment_default_without_persisting_a_control(): void
    {
        config()->set('pilot.checkout_enabled_default', false);

        $this->getJson('/api/v1/pilot/checkout-status')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.reason', null);

        $this->assertDatabaseCount('pilot_controls', 0);
    }

    public function test_closed_checkout_returns_503_without_creating_transaction_records(): void
    {
        $product = Product::factory()->create(['slug' => 'pilot-closed-product']);
        InventoryBucket::factory()->for($product)->create(['service_date' => '2026-10-10']);
        PilotControl::factory()->create([
            'id' => 1,
            'checkout_enabled' => false,
            'reason' => 'Insiden pembayaran sedang diperiksa.',
        ]);

        $this->postJson('/api/v1/checkout', [
            'product_slug' => $product->slug,
            'visit_date' => '2026-10-10',
            'quantity' => 1,
            'customer_name' => 'Pengunjung Pilot',
            'customer_email' => 'pengunjung@example.test',
        ], ['Idempotency-Key' => 'pilot-closed-key-0001'])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'CHECKOUT_CLOSED')
            ->assertJsonPath('error.message', 'Insiden pembayaran sedang diperiksa.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_holds', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_customer_cannot_change_checkout_control(): void
    {
        $customer = User::factory()->create(['platform_role' => 'customer']);

        $this->actingAs($customer)
            ->patchJson('/api/v1/pilot/checkout-status', [
                'enabled' => false,
                'reason' => 'Tidak berwenang mengubah kontrol.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('pilot_controls', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_requires_recent_password_confirmation(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);

        $this->actingAs($admin)
            ->patchJson('/api/v1/pilot/checkout-status', [
                'enabled' => false,
                'reason' => 'Menutup checkout untuk latihan insiden.',
            ])
            ->assertStatus(423)
            ->assertJsonPath('code', 'SENSITIVE_CONFIRMATION_REQUIRED');

        $this->assertDatabaseCount('pilot_controls', 0);
    }

    public function test_admin_can_close_checkout_with_reason_and_audit_log(): void
    {
        config()->set('pilot.checkout_enabled_default', true);
        $admin = User::factory()->create(['platform_role' => 'super_admin']);

        $this->actingAs($admin)
            ->withHeaders($this->sensitiveHeaders($admin))
            ->patchJson('/api/v1/pilot/checkout-status', [
                'enabled' => false,
                'reason' => 'Selisih rekonsiliasi harus ditangani sebelum pesanan baru.',
            ])
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.reason', 'Selisih rekonsiliasi harus ditangani sebelum pesanan baru.');

        $this->assertDatabaseHas('pilot_controls', [
            'id' => 1,
            'checkout_enabled' => false,
            'changed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'pilot.checkout_closed',
            'auditable_type' => PilotControl::class,
            'auditable_id' => 1,
        ]);
    }

    public function test_admin_must_provide_meaningful_reason(): void
    {
        $admin = User::factory()->create(['platform_role' => 'super_admin']);

        $this->actingAs($admin)
            ->withHeaders($this->sensitiveHeaders($admin))
            ->patchJson('/api/v1/pilot/checkout-status', [
                'enabled' => true,
                'reason' => 'ok',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->assertDatabaseCount('pilot_controls', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
