<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaymentWebhook;
use App\Models\CommissionRule;
use App\Models\Coupon;
use App\Models\InventoryBucket;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CouponDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CouponCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
        config()->set('pilot.checkout_enabled_default', true);
    }

    private function product(): Product
    {
        $product = Product::factory()->create(['base_price' => 75000, 'currency' => 'IDR', 'status' => 'published']);
        InventoryBucket::factory()->for($product)->create(['service_date' => '2026-10-10', 'capacity' => 10]);

        return $product;
    }

    private function payload(Product $product, User $user, Coupon $coupon): array
    {
        return ['product_slug' => $product->slug, 'visit_date' => '2026-10-10', 'quantity' => 2, 'customer_name' => $user->name, 'customer_email' => $user->email, 'coupon_code' => $coupon->code, 'expected_total' => 135000];
    }

    public function test_quote_uses_server_subtotal_and_does_not_consume_coupon_or_inventory(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create();
        $this->actingAs($user);

        $this->getJson('/api/v1/promos/quote?'.http_build_query([...$this->payload($product, $user, $coupon), 'subtotal' => 1]))
            ->assertOk()->assertJsonPath('data.subtotal', 150000)->assertJsonPath('data.discount', 15000)->assertJsonPath('data.total', 135000);

        $this->assertSame(0, $coupon->fresh()->used_quota);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_holds', 0);
        $this->assertDatabaseCount('coupon_redemptions', 0);
    }

    public function test_coupon_checkout_and_retry_use_net_amount_once(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create();
        $this->actingAs($user);
        $payload = $this->payload($product, $user, $coupon);
        $headers = ['Idempotency-Key' => 'coupon-checkout-key-001'];

        $response = $this->postJson('/api/v1/checkout', $payload, $headers)->assertCreated()->assertJsonPath('data.total', 135000)->assertJsonPath('data.promotion.discount', 15000);
        $coupon->update(['is_active' => false]);
        $this->postJson('/api/v1/checkout', $payload, $headers)->assertOk()->assertJsonPath('data.order_id', $response->json('data.order_id'));

        $order = Order::query()->firstOrFail();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame(135000, $order->total);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_holds', 1);
        $this->assertDatabaseCount('coupon_redemptions', 1);
        $this->assertDatabaseHas('payment_attempts', ['order_id' => $order->id, 'amount' => 135000]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'total' => 135000]);
        $this->assertSame(1, $coupon->fresh()->used_quota);
    }

    public function test_paid_coupon_order_records_net_payment_and_net_commission_once(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create();
        CommissionRule::query()->create([
            'partner_id' => $product->partner_id, 'product_type' => 'default', 'percentage_rate' => '10.00',
            'fixed_amount' => 0, 'effective_from' => '2026-10-01',
        ]);
        $this->actingAs($user);
        $this->postJson('/api/v1/checkout', $this->payload($product, $user, $coupon), ['Idempotency-Key' => 'coupon-payment-key-001'])->assertCreated();
        $order = Order::query()->firstOrFail();
        $event = PaymentWebhookEvent::factory()->create([
            'provider' => 'sandbox',
            'provider_event_key' => 'coupon-paid-event-001',
            'payment_attempt_id' => $order->paymentAttempts()->firstOrFail()->id,
            'payload' => ['status' => 'succeeded', 'amount' => 135000, 'currency' => 'IDR'],
        ]);

        (new ProcessPaymentWebhook($event->id))->handle();
        (new ProcessPaymentWebhook($event->id))->handle();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'commission_amount' => 13500]);
        $entry = JournalEntry::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(135000, (int) $entry->transactions()->where('type', 'debit')->sum('amount'));
        $this->assertSame(135000, (int) $entry->transactions()->where('type', 'credit')->sum('amount'));
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('coupon_redemptions', 1);
        $this->assertSame(1, $coupon->fresh()->used_quota);
    }

    public function test_coupon_checkout_rejects_email_different_from_authenticated_account(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/v1/checkout', [...$this->payload($product, $user, $coupon), 'customer_email' => 'different@example.test'], ['Idempotency-Key' => 'coupon-email-key-001'])->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('coupon_redemptions', 0);
    }

    public function test_invalid_stock_rolls_back_coupon_consumption(): void
    {
        $product = $this->product();
        InventoryBucket::query()->where('product_id', $product->id)->update(['capacity' => 1]);
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/v1/checkout', $this->payload($product, $user, $coupon), ['Idempotency-Key' => 'coupon-no-stock-key-001'])->assertConflict();

        $this->assertSame(0, $coupon->fresh()->used_quota);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('coupon_redemptions', 0);
    }

    public static function invalidCoupons(): array
    {
        return [
            'inactive' => [['is_active' => false]],
            'not started' => [['starts_at' => '2026-10-02 00:00:00']],
            'expired' => [['expires_at' => '2026-10-01 00:00:00']],
            'quota exhausted' => [['global_quota' => 1, 'used_quota' => 1]],
            'minimum spend' => [['minimum_spend' => '200000.00']],
            'invalid type' => [['discount_type' => 'invalid']],
            'percentage too high' => [['discount_value' => '100.01']],
            'zero discount' => [['discount_value' => '0.00']],
            'zero payable' => [['discount_value' => '100.00']],
        ];
    }

    #[DataProvider('invalidCoupons')]
    public function test_invalid_coupon_rejected_without_creating_transaction(array $attributes): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create($attributes);
        $this->actingAs($user);

        $this->postJson('/api/v1/checkout', $this->payload($product, $user, $coupon), ['Idempotency-Key' => 'coupon-invalid-key-001'])->assertUnprocessable()->assertJsonValidationErrors('coupon_code');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('coupon_redemptions', 0);
        $this->assertDatabaseCount('inventory_holds', 0);
    }

    public static function discountRules(): array
    {
        return [
            'fixed integer rounding' => [['discount_type' => 'fixed', 'discount_value' => '10000.99'], 10000, 140000],
            'percentage rounding' => [['discount_value' => '1.23'], 1845, 148155],
            'maximum discount' => [['maximum_discount' => '5000.50'], 5000, 145000],
        ];
    }

    #[DataProvider('discountRules')]
    public function test_discount_rounds_down_to_integer_rupiah(array $attributes, int $discount, int $total): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create($attributes);
        $this->actingAs($user);

        $this->getJson('/api/v1/promos/quote?'.http_build_query($this->payload($product, $user, $coupon)))
            ->assertOk()->assertJsonPath('data.discount', $discount)->assertJsonPath('data.total', $total);
    }

    public function test_account_quota_prevents_second_order(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create(['user_quota' => 1]);
        $this->actingAs($user);
        $this->postJson('/api/v1/checkout', $this->payload($product, $user, $coupon), ['Idempotency-Key' => 'coupon-account-key-001'])->assertCreated();

        $this->postJson('/api/v1/checkout', $this->payload($product, $user, $coupon), ['Idempotency-Key' => 'coupon-account-key-002'])->assertUnprocessable()->assertJsonValidationErrors('coupon_code');

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, $coupon->fresh()->used_quota);
    }

    public function test_changed_coupon_on_retry_is_an_idempotency_conflict(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create();
        $other = Coupon::factory()->create();
        $this->actingAs($user);
        $headers = ['Idempotency-Key' => 'coupon-conflict-key-001'];
        $this->postJson('/api/v1/checkout', $this->payload($product, $user, $coupon), $headers)->assertCreated();

        $this->postJson('/api/v1/checkout', $this->payload($product, $user, $other), $headers)->assertConflict()->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->assertSame(0, $other->fresh()->used_quota);
    }

    public function test_changed_price_rejected_without_consuming_coupon(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/v1/checkout', [...$this->payload($product, $user, $coupon), 'expected_total' => 1], ['Idempotency-Key' => 'coupon-price-key-001'])->assertConflict();
        $this->assertDatabaseCount('coupon_redemptions', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_guest_and_unverified_customer_cannot_use_coupon(): void
    {
        $product = $this->product();
        $user = User::factory()->unverified()->create();
        $coupon = Coupon::factory()->create();
        $payload = $this->payload($product, $user, $coupon);
        $this->postJson('/api/v1/checkout', $payload, ['Idempotency-Key' => 'coupon-guest-key-001'])->assertUnauthorized();
        $this->actingAs($user);

        $this->postJson('/api/v1/checkout', $payload, ['Idempotency-Key' => 'coupon-unverified-key-001'])->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_demo_reseed_preserves_used_coupon_quota(): void
    {
        $this->seed(CouponDemoSeeder::class);
        $coupon = Coupon::query()->where('code', 'DEMO10')->firstOrFail();
        $coupon->update(['used_quota' => 3, 'is_active' => false]);

        $this->seed(CouponDemoSeeder::class);

        $this->assertDatabaseCount('coupons', 1);
        $this->assertSame(3, $coupon->fresh()->used_quota);
        $this->assertFalse($coupon->fresh()->is_active);
    }

    public function test_production_coupon_is_disabled_without_changing_guest_checkout_contract(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create();
        $this->actingAs($user);
        $this->app->instance('env', 'production');

        $this->postJson('/api/v1/checkout', $this->payload($product, $user, $coupon), ['Idempotency-Key' => 'coupon-production-key-001'])->assertStatus(503);
        $this->assertDatabaseCount('orders', 0);
    }
}
