<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaymentWebhook;
use App\Jobs\ReconcilePaymentAttempt;
use App\Models\InventoryBucket;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use App\Payments\MidtransSandboxGateway;
use App\Payments\PaymentGateway;
use App\Services\CheckoutService;
use App\Services\PaymentAttemptService;
use App\Services\ReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MidtransSandboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.payment_gateway.driver' => 'midtrans_sandbox', 'services.midtrans.server_key' => 'SB-Mid-server-test-only']);
        Http::preventStrayRequests();
    }

    private function order(): Order
    {
        $product = Product::factory()->create(['base_price' => 125000]);
        $date = CarbonImmutable::today()->addDay();
        InventoryBucket::factory()->create(['product_id' => $product->id, 'service_date' => $date->toDateString(), 'session_key' => 'default', 'capacity' => 10]);
        [$order] = app(CheckoutService::class)->create($product, $date, 1, 'Pengunjung uji', 'test@example.test', fake()->uuid());

        return $order;
    }

    private function providerStatus(Order $order, string $state = 'settlement', string $fraud = 'accept'): array
    {
        return ['order_id' => $order->public_id, 'transaction_id' => 'transaction-test-001', 'status_code' => '200',
            'gross_amount' => '125000.00', 'currency' => 'IDR', 'transaction_status' => $state, 'fraud_status' => $fraud, 'payment_type' => 'bank_transfer'];
    }

    private function signed(array $data): array
    {
        $data['signature_key'] = hash('sha512', $data['order_id'].$data['status_code'].$data['gross_amount'].config('services.midtrans.server_key'));

        return $data;
    }

    private function fakeSnap(array $status): void
    {
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['token' => 'test-token', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/test-token']),
            'api.sandbox.midtrans.com/*' => Http::response($status)]);
    }

    public function test_snap_uses_server_amount_basic_auth_and_deterministic_reference_without_repeat_charge(): void
    {
        config(['services.frontend_url' => 'https://wisata.example.test/']);
        $order = $this->order();
        $this->fakeSnap($this->providerStatus($order));
        $attempt = app(PaymentAttemptService::class)->create($order);
        $this->assertInstanceOf(MidtransSandboxGateway::class, app(PaymentGateway::class));
        $this->assertSame('midtrans_sandbox', $attempt->provider);
        $this->assertSame($order->public_id, $attempt->provider_reference);
        $this->assertSame('pending', $attempt->status);
        $this->assertSame($attempt->id, app(PaymentAttemptService::class)->create($order)->id);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['transaction_details']['gross_amount'] === 125000
            && $request['transaction_details']['order_id'] === $order->public_id && $request['item_details'][0]['price'] === 125000
            && $request['callbacks']['finish'] === 'https://wisata.example.test/akun'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('SB-Mid-server-test-only:')));
        $this->assertStringNotContainsString('SB-Mid-server', json_encode($attempt->provider_payload));
    }

    public function test_webhook_processed_before_snap_response_does_not_downgrade_payment(): void
    {
        $order = $this->order();
        Http::fake(['app.sandbox.midtrans.com/*' => function () use ($order) {
            $attempt = PaymentAttempt::where('order_id', $order->id)->firstOrFail();
            $event = PaymentWebhookEvent::create(['provider' => 'midtrans_sandbox', 'provider_event_key' => 'fast-midtrans-event',
                'payment_attempt_id' => $attempt->id, 'payload' => ['status' => 'succeeded', 'amount' => 125000, 'currency' => 'IDR']]);
            (new ProcessPaymentWebhook($event->id))->handle();

            return Http::response(['redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/test-token']);
        }]);
        $attempt = app(PaymentAttemptService::class)->create($order);
        $this->assertSame('succeeded', $attempt->status);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertDatabaseCount('payment_attempts', 1);
        Http::assertSentCount(1);
    }

    public function test_failed_midtrans_attempt_is_not_replaced_or_charged_again(): void
    {
        $order = $this->order();
        $this->fakeSnap($this->providerStatus($order));
        $attempt = app(PaymentAttemptService::class)->create($order);
        $attempt->update(['status' => 'failed']);
        $retry = app(PaymentAttemptService::class)->create($order);
        $this->assertSame($attempt->id, $retry->id);
        $this->assertSame('failed', $retry->status);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_timeout_and_status_404_remain_uncertain_without_reposting_snap(): void
    {
        $order = $this->order();
        $snapCalls = 0;
        Http::fake(['app.sandbox.midtrans.com/*' => function () use (&$snapCalls) {
            $snapCalls++;
            throw new ConnectionException('timeout');
        }, 'api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404'], 404)]);
        $first = app(PaymentAttemptService::class)->create($order);
        $second = app(PaymentAttemptService::class)->create($order);
        $this->assertSame('uncertain', $second->status);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($order->public_id, $second->provider_reference);
        Http::assertSentCount(2);
        $this->assertSame(1, $snapCalls);
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_signed_webhook_is_verified_against_status_api_and_worker_issues_ledger_and_voucher_once(): void
    {
        $order = $this->order();
        $status = $this->providerStatus($order);
        $this->fakeSnap($status);
        $attempt = app(PaymentAttemptService::class)->create($order);
        Queue::fake([ProcessPaymentWebhook::class]);
        $this->postJson('/api/v1/webhooks/payments/midtrans', $this->signed($status))->assertOk();
        $this->assertSame('pending_payment', $order->fresh()->status);
        $event = PaymentWebhookEvent::firstOrFail();
        Queue::assertPushed(ProcessPaymentWebhook::class, fn ($job) => $job->eventId === $event->id);
        (new ProcessPaymentWebhook($event->id))->handle();
        $this->postJson('/api/v1/webhooks/payments/midtrans', $this->signed($status))->assertOk();
        (new ProcessPaymentWebhook($event->id))->handle();
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('succeeded', $attempt->fresh()->status);
        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_transactions', 2);
        $this->assertDatabaseCount('vouchers', 1);
        $this->assertStringNotContainsString('signature_key', json_encode($event->payload));
    }

    public function test_invalid_signature_performs_no_status_request_and_writes_no_event(): void
    {
        $order = $this->order();
        $payload = $this->signed($this->providerStatus($order));
        $payload['signature_key'] = str_repeat('0', 128);
        $this->postJson('/api/v1/webhooks/payments/midtrans', $payload)->assertUnauthorized();
        Http::assertNothingSent();
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_forged_success_with_valid_amount_signature_uses_authoritative_pending_status(): void
    {
        $order = $this->order();
        $this->fakeSnap($this->providerStatus($order, 'pending'));
        $attempt = app(PaymentAttemptService::class)->create($order);
        $this->postJson('/api/v1/webhooks/payments/midtrans', $this->signed($this->providerStatus($order, 'settlement')))->assertOk();
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_amount_mismatch_and_unavailable_status_do_not_create_events(): void
    {
        $order = $this->order();
        $this->fakeSnap($this->providerStatus($order));
        app(PaymentAttemptService::class)->create($order);
        $payload = $this->providerStatus($order);
        $payload['gross_amount'] = '1.00';
        $this->postJson('/api/v1/webhooks/payments/midtrans', $this->signed($payload))->assertConflict();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404'], 404)]);
        $this->postJson('/api/v1/webhooks/payments/midtrans', $this->signed($this->providerStatus($order)))->assertStatus(503);
        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    #[DataProvider('statusMappings')]
    public function test_status_mapping_is_conservative(string $state, ?string $fraud, string $expected): void
    {
        $data = ['order_id' => 'order-test', 'gross_amount' => '100.00', 'transaction_status' => $state, 'fraud_status' => $fraud];
        $this->assertSame($expected, app(MidtransSandboxGateway::class)->normalize($data, 'order-test')['status']);
    }

    public static function statusMappings(): array
    {
        return [['settlement', 'accept', 'succeeded'], ['capture', 'accept', 'succeeded'], ['capture', 'challenge', 'pending'],
            ['capture', null, 'pending'], ['capture', 'deny', 'failed'], ['expire', null, 'failed'], ['pending', null, 'pending'],
            ['refund', null, 'unknown'], ['partial_refund', null, 'unknown'], ['authorize', null, 'unknown']];
    }

    public function test_configuration_accepts_dashboard_key_formats_without_inferring_environment_from_prefix(): void
    {
        foreach (['SB-Mid-server-test-only', 'Mid-server-test-only'] as $key) {
            config(['services.midtrans.server_key' => $key]);
            $this->assertTrue(app(MidtransSandboxGateway::class)->isConfigured());
        }
        foreach (['', 'Mid-client-test-only', 'Mid-server-', 'Mid-server-test key'] as $key) {
            config(['services.midtrans.server_key' => $key]);
            $this->assertFalse(app(MidtransSandboxGateway::class)->isConfigured());
        }
    }

    public function test_missing_key_blocks_checkout_before_order_and_readiness_never_returns_key(): void
    {
        config(['services.midtrans.server_key' => null]);
        $this->getJson('/api/v1/payments/gateway-status')->assertOk()->assertJsonPath('data.ready', false);
        $this->postJson('/api/v1/checkout', [])->assertStatus(503);
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_production_environment_does_not_contact_sandbox(): void
    {
        app()->detectEnvironment(fn () => 'production');
        $this->getJson('/api/v1/payments/gateway-status')->assertOk()->assertJsonPath('data.ready', false);
        $this->postJson('/api/v1/webhooks/payments/midtrans', [])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_old_internal_attempt_is_reconciled_by_original_provider_after_driver_switch(): void
    {
        $attempt = PaymentAttempt::factory()->create(['provider' => 'sandbox']);
        (new ReconcilePaymentAttempt($attempt->id))->handle(app(PaymentGateway::class), app(ReconciliationService::class));
        Http::assertNothingSent();
    }
}
