<?php

namespace Tests\Feature;

use App\Models\InventoryBucket;
use App\Models\InventoryHold;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPaymentResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_resume_existing_snap_without_creating_a_second_attempt_and_expired_hold_hides_url(): void
    {
        $owner = User::factory()->create();
        $product = Product::factory()->create(['status' => 'published', 'base_price' => 10000]);
        $date = CarbonImmutable::today()->addDay();
        InventoryBucket::factory()->create(['product_id' => $product->id, 'service_date' => $date->toDateString(), 'capacity' => 10]);
        [$order] = app(CheckoutService::class)->create($product, $date, 1, $owner->name, $owner->email, 'resume-account-demo-01', null, $owner);
        $url = 'https://app.sandbox.midtrans.com/snap/v4/redirection/test-token';
        $attempt = PaymentAttempt::factory()->create(['order_id' => $order->id, 'provider' => 'midtrans_sandbox', 'amount' => $order->total, 'currency' => $order->currency, 'status' => 'pending', 'checkout_url' => $url]);
        $path = '/api/v1/account/orders/'.$order->public_id;
        $this->getJson($path)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($path)->assertNotFound();
        $this->actingAs($owner)->getJson($path)->assertOk()->assertJsonPath('data.checkout_url', $url);
        $this->getJson($path)->assertOk();
        $this->assertDatabaseCount('payment_attempts', 1);
        InventoryHold::query()->update(['expires_at' => now()->subMinute()]);
        $this->getJson($path)->assertJsonPath('data.checkout_url', null);
    }

    public function test_failed_paid_uncertain_and_untrusted_links_cannot_be_resumed(): void
    {
        $owner = User::factory()->create();
        $product = Product::factory()->create(['status' => 'published']);
        $date = CarbonImmutable::today()->addDay();
        InventoryBucket::factory()->create(['product_id' => $product->id, 'service_date' => $date->toDateString(), 'capacity' => 10]);
        [$order] = app(CheckoutService::class)->create($product, $date, 1, $owner->name, $owner->email, 'resume-account-demo-02', null, $owner);
        $attempt = PaymentAttempt::factory()->create(['order_id' => $order->id, 'provider' => 'midtrans_sandbox', 'amount' => $order->total, 'currency' => $order->currency, 'status' => 'pending', 'checkout_url' => 'https://app.sandbox.midtrans.com.evil.test/snap/v4/redirection/test']);
        $path = '/api/v1/account/orders/'.$order->public_id;
        $this->actingAs($owner);
        $this->getJson($path)->assertJsonPath('data.checkout_url', null);
        $attempt->update(['checkout_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/test-token']);
        foreach (['failed', 'uncertain', 'succeeded'] as $state) {
            $attempt->update(['status' => $state]);
            $this->getJson($path)->assertJsonPath('data.checkout_url', null);
        }
        $attempt->update(['status' => 'pending']);
        $order->update(['status' => 'paid']);
        $this->getJson($path)->assertJsonPath('data.checkout_url', null);
    }
}
