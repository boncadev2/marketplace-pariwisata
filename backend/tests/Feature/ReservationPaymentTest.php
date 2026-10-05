<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LodgingBooking;
use App\Models\MealSlot;
use App\Models\PartnerMember;
use App\Models\ReservationPayment;
use App\Models\RoomType;
use App\Models\UmkmOrder;
use App\Models\UmkmProduct;
use App\Models\User;
use App\Services\ReservationPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReservationPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.midtrans.server_key' => 'SB-Mid-server-testkey', 'services.frontend_url' => 'http://localhost:3000']);
        Http::preventStrayRequests();
    }

    private function order(User $user): UmkmOrder
    {
        $product = UmkmProduct::factory()->create(['price' => 35000, 'stock' => 10, 'status' => 'published', 'delivery_available' => true, 'shipping_fee' => 15000]);
        $id = $this->actingAs($user)->postJson('/api/v1/account/umkm-orders', ['product_slug' => $product->slug, 'quantity' => 2, 'expected_price' => 35000, 'customer_name' => 'Pemesan Demo', 'customer_phone' => '0000000000', 'fulfillment' => 'delivery', 'shipping_address' => 'Alamat sintetis untuk pengujian.', 'postal_code' => '00000', 'expected_shipping_fee' => 15000], ['Idempotency-Key' => fake()->uuid()])->assertCreated()->json('data.order_id');

        return UmkmOrder::where('public_id', $id)->firstOrFail();
    }

    private function fakeSnap(): void
    {
        Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(['token' => 'sandbox-token-123456789', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/sandbox-token-123456789'])]);
    }

    private function provider(ReservationPayment $payment, string $status = 'settlement', int $amount = 85000): array
    {
        return ['order_id' => $payment->reference, 'transaction_id' => 'transaction-demo-001', 'transaction_status' => $status, 'gross_amount' => $amount.'.00', 'currency' => 'IDR', 'status_code' => '200'];
    }

    private function webhook(ReservationPayment $payment, array $actual): array
    {
        return [...$actual, 'signature_key' => hash('sha512', $payment->reference.$actual['status_code'].$actual['gross_amount'].'SB-Mid-server-testkey')];
    }

    public function test_umkm_checkout_uses_snapshot_and_shipping_once_and_repeat_reuses_session(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);
        $order->product->update(['price' => 99000, 'shipping_fee' => 50000]);
        $this->fakeSnap();
        $url = '/api/v1/account/reservation-payments/umkm/'.$order->public_id.'/checkout';
        $result = $this->postJson($url, ['amount' => 1, 'user_id' => 999])->assertOk()->assertJsonPath('data.amount', 85000)->assertJsonPath('data.status', 'pending')->assertJsonMissingPath('data.snap_token')->assertJsonMissingPath('data.user_id');
        $this->postJson($url)->assertOk()->assertJsonPath('data.reference', $result->json('data.reference'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['transaction_details']['gross_amount'] === 85000 && $request['callbacks']['finish'] === 'http://localhost:3000/akun/umkm');
        $this->assertSame(8, $order->product->fresh()->stock);
        $this->postJson('/api/v1/account/umkm-orders/'.$order->public_id.'/cancel')->assertConflict();
        $this->actingAs(User::factory()->create())->postJson($url)->assertNotFound();
        $this->assertDatabaseCount('reservation_payments', 1);
    }

    public function test_webhook_checks_signature_amount_and_updates_paid_only_from_provider(): void
    {
        $order = $this->order(User::factory()->create());
        $this->fakeSnap();
        $this->postJson('/api/v1/account/reservation-payments/umkm/'.$order->public_id.'/checkout')->assertOk();
        $payment = ReservationPayment::firstOrFail();
        $actual = $this->provider($payment);
        Http::fake(function () use (&$actual) {
            return Http::response($actual);
        });
        $route = '/api/v1/webhooks/payments/midtrans';
        $this->postJson($route, [...$this->webhook($payment, $actual), 'signature_key' => str_repeat('0', 128)])->assertUnauthorized();
        $this->assertSame('pending', $payment->fresh()->status);
        $this->postJson($route, $this->webhook($payment, $actual))->assertOk();
        $this->postJson($route, $this->webhook($payment, $actual))->assertOk();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(1, AuditLog::where('action', 'reservation.payment_updated')->count());
        $this->getJson('/api/v1/account/umkm-orders')->assertJsonPath('data.0.payment_status', 'paid');
        $this->postJson('/api/v1/account/reservation-payments/umkm/'.$order->public_id.'/cancel')->assertConflict();
        $wrong = $this->provider($payment, 'settlement', 1);
        $actual = $wrong;
        $this->postJson($route, $this->webhook($payment, $wrong))->assertConflict();
        $this->assertSame(8, $order->product->fresh()->stock);
    }

    public function test_provider_expiry_releases_stock_once_and_late_payment_requires_admin(): void
    {
        $order = $this->order(User::factory()->create());
        $this->fakeSnap();
        $this->postJson('/api/v1/account/reservation-payments/umkm/'.$order->public_id.'/checkout');
        $payment = ReservationPayment::firstOrFail();
        $actual = $this->provider($payment, 'expire');
        Http::fake(function () use (&$actual) {
            return Http::response($actual);
        });
        $this->postJson('/api/v1/webhooks/payments/midtrans', $this->webhook($payment, $actual))->assertOk();
        $this->postJson('/api/v1/webhooks/payments/midtrans', $this->webhook($payment, $actual))->assertOk();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $order->product->fresh()->stock);
        $this->assertSame('failed', $payment->fresh()->status);
        $late = $this->provider($payment);
        $actual = $late;
        $this->postJson('/api/v1/webhooks/payments/midtrans', $this->webhook($payment, $late))->assertOk();
        $this->assertSame('payment_exception', $payment->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $order->product->fresh()->stock);
    }

    public function test_timeout_is_uncertain_and_repeat_never_creates_second_charge(): void
    {
        $order = $this->order(User::factory()->create());
        Http::fake(['app.sandbox.midtrans.com/*' => Http::failedConnection()]);
        $url = '/api/v1/account/reservation-payments/umkm/'.$order->public_id.'/checkout';
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'uncertain');
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'uncertain');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('reservation_payments', 1);
        $this->assertSame(8, $order->product->fresh()->stock);
    }

    public function test_lodging_and_culinary_use_server_totals_and_release_inventory_once(): void
    {
        $user = User::factory()->create();
        $room = RoomType::factory()->create();
        $room->rates()->create(['date' => now()->addWeek()->toDateString(), 'price' => '150000.00']);
        $room->inventories()->create(['date' => now()->addWeek()->toDateString(), 'stock' => 5]);
        $this->actingAs($user);
        $id = $this->postJson('/api/v1/account/lodging-bookings', ['room_type_id' => $room->id, 'check_in' => now()->addWeek()->toDateString(), 'check_out' => now()->addWeek()->addDay()->toDateString(), 'quantity' => 2, 'guests' => 2, 'expected_total_price' => '300000.00'], ['Idempotency-Key' => fake()->uuid()])->assertCreated()->json('data.id');
        $this->fakeSnap();
        $this->postJson('/api/v1/account/reservation-payments/lodging/'.$id.'/checkout')->assertOk()->assertJsonPath('data.amount', 300000);
        $this->postJson('/api/v1/account/lodging-bookings/'.$id.'/cancel')->assertConflict();
        $payment = ReservationPayment::where('kind', 'lodging')->firstOrFail();
        app(ReservationPaymentService::class)->apply($payment, $this->provider($payment, 'expire', 300000));
        app(ReservationPaymentService::class)->apply($payment, $this->provider($payment, 'expire', 300000));
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'stock' => 5]);
        $this->assertDatabaseHas('lodging_bookings', ['id' => $id, 'status' => 'cancelled']);
        $slot = MealSlot::factory()->create(['price' => '75000.00']);
        $meal = $this->postJson('/api/v1/account/meal-bookings', ['meal_slot_id' => $slot->id, 'quantity' => 2, 'expected_total_price' => '150000.00'], ['Idempotency-Key' => fake()->uuid()])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/account/reservation-payments/culinary/'.$meal.'/checkout')->assertOk()->assertJsonPath('data.amount', 150000);
        $this->postJson('/api/v1/account/meal-bookings/'.$meal.'/cancel')->assertConflict();
        $payment = ReservationPayment::where('kind', 'culinary')->firstOrFail();
        app(ReservationPaymentService::class)->apply($payment, $this->provider($payment, 'cancel', 150000));
        app(ReservationPaymentService::class)->apply($payment, $this->provider($payment, 'cancel', 150000));
        $this->assertSame(0, $slot->fresh()->reserved);
        $this->assertDatabaseHas('meal_bookings', ['id' => $meal, 'status' => 'cancelled']);
    }

    public function test_manager_cannot_fulfill_pending_payment_or_cancel_paid_order(): void
    {
        $owner = User::factory()->create();
        $order = $this->order($owner);
        PartnerMember::create(['user_id' => $owner->id, 'partner_id' => $order->product->partner_id, 'role' => 'owner', 'is_active' => true]);
        $this->fakeSnap();
        $this->postJson('/api/v1/account/reservation-payments/umkm/'.$order->public_id.'/checkout')->assertOk();
        $url = '/api/v1/dashboard/umkm-orders/'.$order->public_id;
        $revision = $this->getJson('/api/v1/dashboard/umkm-orders')->json('data.0.revision');
        $this->patchJson($url, ['status' => 'processing_sandbox', 'revision' => $revision])->assertConflict();
        $payment = ReservationPayment::firstOrFail();
        app(ReservationPaymentService::class)->apply($payment, $this->provider($payment));
        $this->patchJson($url, ['status' => 'processing_sandbox', 'revision' => $revision])->assertOk();
        $revision = $this->getJson('/api/v1/dashboard/umkm-orders')->json('data.0.revision');
        $this->patchJson($url, ['status' => 'cancelled', 'revision' => $revision])->assertConflict();
    }

    public function test_guest_and_unconfigured_gateway_cannot_start_payment(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);
        $url = '/api/v1/account/reservation-payments/umkm/'.$order->public_id.'/checkout';
        config(['services.midtrans.server_key' => '']);
        $this->postJson($url)->assertServiceUnavailable();
        $this->assertDatabaseCount('reservation_payments', 0);
        $this->app['auth']->forgetGuards();
        $this->postJson($url)->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_cancel_snap_session_before_method_selection_releases_stock_only_after_provider_confirms(): void
    {
        $order = $this->order(User::factory()->create());
        $this->fakeSnap();
        $base = '/api/v1/account/reservation-payments/umkm/'.$order->public_id;
        $this->postJson($base.'/checkout')->assertOk();
        $confirmed = false;
        Http::fake(function ($request) use (&$confirmed) {
            if (str_ends_with($request->url(), '/status')) {
                return Http::response(['status_code' => '404'], 404);
            }

            return $confirmed ? Http::response(['canceled_at' => '2026-10-03T01:00:00Z']) : Http::response(['error_messages' => ['Transaction is on progress']], 400);
        });
        $this->postJson($base.'/cancel')->assertConflict();
        $this->assertSame(8, $order->product->fresh()->stock);
        $confirmed = true;
        $this->postJson($base.'/cancel')->assertOk()->assertJsonPath('data.status', 'failed');
        $this->postJson($base.'/cancel')->assertOk();
        $this->assertSame(10, $order->product->fresh()->stock);
    }

    public function test_expired_unused_snap_session_is_voided_before_releasing_stock(): void
    {
        $order = $this->order(User::factory()->create());
        $this->fakeSnap();
        $this->postJson('/api/v1/account/reservation-payments/umkm/'.$order->public_id.'/checkout');
        $payment = ReservationPayment::firstOrFail();
        $this->travel(16)->minutes();
        Http::fake(function ($request) {
            return str_ends_with($request->url(), '/status') ? Http::response(['status_code' => '404'], 404) : Http::response(['canceled_at' => '2026-10-03T01:00:00Z']);
        });
        $result = app(ReservationPaymentService::class)->refresh($payment);
        $this->assertSame('failed', $result->status);
        $this->assertSame(10, $order->product->fresh()->stock);
    }

    public function test_pending_cancel_racing_success_keeps_stock_and_reports_paid(): void
    {
        $order = $this->order(User::factory()->create());
        $this->fakeSnap();
        $base = '/api/v1/account/reservation-payments/umkm/'.$order->public_id;
        $this->postJson($base.'/checkout');
        $payment = ReservationPayment::firstOrFail();
        $reads = 0;
        Http::fake(function ($request) use ($payment, &$reads) {
            if ($request->method() === 'POST') {
                return Http::response(['status_code' => '412'], 412);
            }
            $reads++;

            return Http::response($this->provider($payment, $reads >= 3 ? 'settlement' : 'pending'));
        });
        $this->postJson($base.'/cancel')->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertSame(8, $order->product->fresh()->stock);
        $this->assertSame('reserved_sandbox', $order->fresh()->status);
    }

    public function test_reversal_after_paid_requires_admin_and_blocks_fulfillment_without_releasing_stock(): void
    {
        $order = $this->order(User::factory()->create());
        $this->fakeSnap();
        $this->postJson('/api/v1/account/reservation-payments/umkm/'.$order->public_id.'/checkout');
        $payment = ReservationPayment::firstOrFail();
        app(ReservationPaymentService::class)->apply($payment, $this->provider($payment));
        app(ReservationPaymentService::class)->apply($payment, $this->provider($payment, 'deny'));
        $this->assertSame('payment_exception', $payment->fresh()->status);
        $this->assertSame(8, $order->product->fresh()->stock);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reservation.payment_exception', 'auditable_id' => $payment->id]);
    }

    public function test_cancelled_booking_and_fractional_rupiah_cannot_start_payment(): void
    {
        $order = $this->order(User::factory()->create());
        $this->postJson('/api/v1/account/umkm-orders/'.$order->public_id.'/cancel')->assertOk();
        $this->postJson('/api/v1/account/reservation-payments/umkm/'.$order->public_id.'/checkout')->assertConflict();
        $room = RoomType::factory()->create();
        $booking = LodgingBooking::create(['user_id' => $order->user_id, 'room_type_id' => $room->id, 'check_in' => now()->addWeek(), 'check_out' => now()->addWeek()->addDay(), 'quantity' => 1, 'guests' => 1, 'total_price' => '150000.25', 'nightly_prices' => [], 'status' => 'reserved_sandbox']);
        $this->postJson('/api/v1/account/reservation-payments/lodging/'.$booking->id.'/checkout')->assertUnprocessable();
        $this->assertDatabaseCount('reservation_payments', 0);
        Http::assertNothingSent();
    }

    public function test_change_method_cancels_old_session_and_creates_fresh_snap_session_without_cancelling_booking(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);
        $count = 0;
        Http::fake(function ($request) use (&$count) {
            if (str_contains($request->url(), '/cancel')) {
                return Http::response(['status_code' => '200', 'transaction_status' => 'cancel', 'canceled_at' => now()->toISOString()]);
            }
            $count++;
            $token = $count === 1 ? 'sandbox-token-123456789' : 'new-sandbox-token-987654321';

            return Http::response(['token' => $token, 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/'.$token]);
        });
        $base = '/api/v1/account/reservation-payments/umkm/'.$order->public_id;
        $first = $this->postJson($base.'/checkout')->assertOk();
        $firstRef = $first->json('data.reference');

        $second = $this->postJson($base.'/change-method')->assertOk();
        $secondRef = $second->json('data.reference');

        $this->assertNotSame($firstRef, $secondRef);
        $this->assertSame('pending', $second->json('data.status'));
        $this->assertSame('https://app.sandbox.midtrans.com/snap/v4/redirection/new-sandbox-token-987654321', $second->json('data.checkout_url'));
        $this->assertSame('reserved_sandbox', $order->fresh()->status);
        $this->assertSame(8, $order->product->fresh()->stock);
    }
}
