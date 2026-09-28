<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
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
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user)->withHeader('X-Guest-Access-Token', str_repeat('x', 48))->postJson($uri, $payload)->assertNotFound();
        $this->actingAs($user)->withHeader('X-Guest-Access-Token', self::GUEST_TOKEN)->postJson($uri, $payload)->assertOk()->assertJsonPath('data.order_id', $order->public_id);
        $this->assertSame($user->id, $order->fresh()->user_id);
        $this->actingAs($user)->withHeader('X-Guest-Access-Token', self::GUEST_TOKEN)->postJson($uri, $payload)->assertOk();
    }

    public function test_claim_cannot_transfer_order_to_another_account(): void
    {
        $owner = User::factory()->create(['email' => 'customer@example.test']);
        $other = User::factory()->create(['email' => 'CUSTOMER@example.test']);
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
        $pending = Order::factory()->create(['user_id' => $owner->id, 'status' => 'pending_payment']);
        $foreign = Order::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($owner)->getJson('/api/v1/account/orders?status=paid')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.order_id', $paid->public_id)->assertJsonPath('data.0.visit_date', '2026-10-01');
        $response->assertDontSee('customer_email')->assertDontSee('guest_access_hash')->assertDontSee('idempotency_key');
        $this->actingAs($owner)->getJson('/api/v1/account/orders/'.$pending->public_id)->assertOk();
        $this->actingAs($owner)->getJson('/api/v1/account/orders/'.$foreign->public_id)->assertNotFound();
        $this->actingAs($owner)->getJson('/api/v1/account/orders?status=imaginary')->assertUnprocessable();

    }
}
