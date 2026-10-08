<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountOrdersTest extends TestCase
{
    use RefreshDatabase;

    private const GUEST_TOKEN = 'gggggggggggggggggggggggggggggggggggggggggggggggg';

    public function test_order_list_requires_authentication_and_does_not_auto_match_email(): void
    {
        $user = User::factory()->create(['email' => 'customer@example.test']);
        Order::factory()->create(['customer_email' => $user->email]);

        $this->getJson('/api/v1/account/orders')->assertUnauthorized();
        $this->actingAs($user)->getJson('/api/v1/account/orders')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_claim_requires_verified_matching_account_and_correct_guest_token(): void
    {
        $order = Order::factory()->create(['customer_email' => 'customer@example.test', 'guest_access_hash' => Hash::make(self::GUEST_TOKEN)]);
        $uri = '/api/v1/account/orders/claim';
        $payload = ['order_id' => $order->public_id];

        $unverified = User::factory()->unverified()->create(['email' => 'customer@example.test']);
        $this->actingAs($unverified)
            ->withHeader('X-Guest-Access-Token', self::GUEST_TOKEN)->postJson($uri, $payload)->assertForbidden();

        $this->actingAs(User::factory()->create(['email' => 'other@example.test']))
            ->withHeader('X-Guest-Access-Token', self::GUEST_TOKEN)->postJson($uri, $payload)->assertNotFound();

        $user = $unverified;
        $user->forceFill(['email' => 'CUSTOMER@example.test', 'email_verified_at' => now()])->save();
        $this->actingAs($user)->withHeader('X-Guest-Access-Token', str_repeat('x', 48))->postJson($uri, $payload)->assertNotFound();
        $this->actingAs($user)->withHeader('X-Guest-Access-Token', self::GUEST_TOKEN)->postJson($uri, $payload)->assertOk()->assertJsonPath('data.order_id', $order->public_id);
        $this->assertSame($user->id, $order->fresh()->user_id);
        $this->actingAs($user)->withHeader('X-Guest-Access-Token', self::GUEST_TOKEN)->postJson($uri, $payload)->assertOk();
    }

    public function test_claim_cannot_transfer_order_to_another_account(): void
    {
        $owner = User::factory()->create(['email' => 'customer@example.test']);
        $other = User::factory()->create(['email' => 'other@example.test']);
        $order = Order::factory()->create(['user_id' => $owner->id, 'customer_email' => $owner->email, 'guest_access_hash' => Hash::make(self::GUEST_TOKEN)]);

        $this->actingAs($other)->withHeader('X-Guest-Access-Token', self::GUEST_TOKEN)
            ->postJson('/api/v1/account/orders/claim', ['order_id' => $order->public_id])->assertNotFound();
        $this->assertSame($owner->id, $order->fresh()->user_id);
    }

    public function test_list_filter_and_detail_are_scoped_without_exposing_private_fields(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $paid = Order::factory()->create(['user_id' => $owner->id, 'status' => 'paid', 'policy_snapshot' => ['visit_date' => '2026-10-01']]);
        $paid->partner->update(['contact_email' => 'manager@example.test', 'contact_phone' => '+6281200000000']);
        PaymentAttempt::create(['order_id' => $paid->id, 'provider' => 'sandbox', 'provider_reference' => 'private-provider-reference', 'status' => 'succeeded', 'currency' => 'IDR', 'amount' => 100]);
        $pending = Order::factory()->create(['user_id' => $owner->id, 'status' => 'pending_payment']);
        $foreign = Order::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($owner)->getJson('/api/v1/account/orders?status=paid')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.order_id', $paid->public_id)->assertJsonPath('data.0.visit_date', '2026-10-01');
        $response->assertDontSee('customer_email')->assertDontSee('guest_access_hash')->assertDontSee('idempotency_key');
        $this->actingAs($owner)->getJson('/api/v1/account/orders/'.$pending->public_id)->assertOk();
        $detail = $this->actingAs($owner)->getJson('/api/v1/account/orders/'.$paid->public_id)->assertOk()->assertJsonPath('data.payment_status', 'succeeded')->assertJsonPath('data.manager.email', 'manager@example.test')->assertJsonPath('data.receipt_available', false);
        $detail->assertDontSee('private-provider-reference');
        $this->actingAs($owner)->getJson('/api/v1/account/orders/'.$foreign->public_id)->assertNotFound();
        $this->actingAs($owner)->getJson('/api/v1/account/orders?status=imaginary')->assertUnprocessable();

    }

    public function test_account_voucher_requires_ownership_and_paid_status(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id, 'status' => 'paid']);
        $product = Product::create(['partner_id' => $order->partner_id, 'name' => 'Tiket test', 'slug' => 'account-voucher-test', 'type' => 'ticket']);
        $item = $order->items()->create(['product_id' => $product->id, 'name' => 'Tiket test', 'quantity' => 1, 'unit_price' => 100, 'total' => 100, 'snapshot' => []]);
        $token = str_repeat('v', 48);
        Voucher::create(['order_item_id' => $item->id, 'partner_id' => $order->partner_id, 'token_hash' => hash('sha256', $token), 'token' => $token, 'service_date' => '2026-10-01', 'admissions' => 1]);
        $uri = '/api/v1/account/orders/'.$order->public_id.'/vouchers';

        $this->getJson($uri)->assertUnauthorized();
        $this->actingAs($stranger)->getJson($uri)->assertNotFound();
        $response = $this->actingAs($owner)->getJson($uri)->assertOk()->assertJsonPath('data.vouchers.0.token', $token);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $order->update(['status' => 'refunded']);
        $this->actingAs($owner)->getJson($uri)->assertOk()->assertJsonCount(0, 'data.vouchers');
    }

    public function test_user_can_update_tour_participants_manifest(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id, 'status' => 'paid']);

        $uri = '/api/v1/account/orders/'.$order->public_id.'/participants';
        $payload = [
            'participants' => [
                [
                    'name' => 'Budi Santoso',
                    'id_number' => '3201123456780001',
                    'phone' => '08123456789',
                    'notes' => 'Vegetarian',
                ],
                [
                    'name' => 'Siti Rahma',
                    'id_number' => '3201123456780002',
                    'phone' => '08129876543',
                    'notes' => '',
                ],
            ],
        ];

        $this->postJson($uri, $payload)->assertUnauthorized();
        $this->actingAs($stranger)->postJson($uri, $payload)->assertNotFound();

        $response = $this->actingAs($owner)->postJson($uri, $payload);
        $response->assertOk()
            ->assertJsonPath('data.order_id', $order->public_id)
            ->assertJsonCount(2, 'data.participants')
            ->assertJsonPath('data.participants.0.name', 'Budi Santoso');

        $this->assertSame(
            'Budi Santoso',
            $order->fresh()->policy_snapshot['participants'][0]['name']
        );
    }
}
