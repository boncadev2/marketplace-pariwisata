<?php

namespace Tests\Feature;

use App\Jobs\DeliverTransactionNotice;
use App\Mail\TransactionNotice;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Services\TransactionOutbox;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionOutboxTest extends TestCase
{
    use DatabaseMigrations;

    public function test_rollback_removes_outbox_without_sending_or_queueing(): void
    {
        $order = Order::factory()->create(['status' => 'paid']);
        Mail::fake();
        Queue::fake([DeliverTransactionNotice::class]);
        DB::beginTransaction();

        app(TransactionOutbox::class)->record($order, 'confirmation', 'paid', 'Pembayaran berhasil.');
        DB::rollBack();

        $this->assertDatabaseCount('notification_deliveries', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_repeated_event_and_job_send_only_one_email(): void
    {
        $order = Order::factory()->create(['status' => 'paid']);
        Mail::fake();
        $outbox = app(TransactionOutbox::class);
        $delivery = $outbox->record($order, 'confirmation', 'paid', 'Pembayaran berhasil.');
        $outbox->record($order, 'confirmation', 'paid', 'Pembayaran berhasil.');

        (new DeliverTransactionNotice($delivery->id))->handle();
        (new DeliverTransactionNotice($delivery->id))->handle();

        $this->assertDatabaseCount('notification_deliveries', 1);
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'sent', 'attempts' => 1]);
        Mail::assertSent(TransactionNotice::class, fn ($mail) => $mail->hasTo($order->customer_email) && $mail->messageKey === $delivery->deduplication_key);
        Mail::assertSentCount(1);
        $this->assertCount(1, $delivery->fresh()->delivery_log);
    }

    public function test_provider_failure_keeps_paid_order_and_stops_after_three_attempts(): void
    {
        $this->freezeTime();
        $order = Order::factory()->create(['status' => 'paid']);
        $delivery = NotificationDelivery::factory()->create(['order_id' => $order->id]);
        Mail::shouldReceive('mailer->to->send')->times(3)->andThrow(new \RuntimeException('Simulated provider unavailable'));
        $job = new DeliverTransactionNotice($delivery->id);

        $job->handle();
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'retry', 'attempts' => 1]);
        $job->handle();
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->travel(1)->minutes();
        $job->handle();
        $this->travel(2)->minutes();
        $job->handle();
        $job->handle();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'failed', 'attempts' => 3]);
        $this->assertCount(3, $delivery->fresh()->delivery_log);
        $this->assertStringNotContainsString('Simulated provider unavailable', json_encode($delivery->fresh()->delivery_log));
    }

    public function test_poller_queues_due_records_and_quarantines_interrupted_send(): void
    {
        $this->freezeTime();
        $due = NotificationDelivery::factory()->create();
        NotificationDelivery::factory()->create(['available_at' => now()->addMinute()]);
        $interrupted = NotificationDelivery::factory()->create(['status' => 'sending', 'claimed_at' => now()->subMinutes(6), 'attempts' => 1]);
        Queue::fake([DeliverTransactionNotice::class]);

        $this->artisan('notifications:dispatch-outbox')->assertSuccessful();

        Queue::assertPushed(DeliverTransactionNotice::class, fn ($job) => $job->deliveryId === $due->id);
        Queue::assertPushed(DeliverTransactionNotice::class, 1);
        $this->assertDatabaseHas('notification_deliveries', ['id' => $interrupted->id, 'status' => 'uncertain']);
        $this->assertSame('uncertain', $interrupted->fresh()->delivery_log[0]['result']);
    }

    public function test_retry_succeeds_without_sending_again_after_acceptance(): void
    {
        $this->freezeTime();
        $delivery = NotificationDelivery::factory()->create();
        Mail::shouldReceive('mailer->to->send')->once()->andThrow(new \RuntimeException('Provider unavailable'));
        $job = new DeliverTransactionNotice($delivery->id);
        $job->handle();
        $this->travel(1)->minutes();
        Mail::shouldReceive('getDefaultDriver')->andReturn('array');
        Mail::fake();

        $job->handle();
        $job->handle();

        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'sent', 'attempts' => 2]);
        Mail::assertSentCount(1);
        $this->assertSame(['error', 'accepted'], array_column($delivery->fresh()->delivery_log, 'result'));
    }

    public function test_production_poller_does_not_queue_when_disabled(): void
    {
        NotificationDelivery::factory()->create();
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['services.transaction_notices.enabled' => false]);
        Queue::fake([DeliverTransactionNotice::class]);

        $this->artisan('notifications:dispatch-outbox')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->app->detectEnvironment(fn (): string => 'testing');
    }

    public function test_recipient_and_snapshot_are_encrypted_and_hidden(): void
    {
        $delivery = NotificationDelivery::factory()->create();

        $raw = DB::table('notification_deliveries')->where('id', $delivery->id)->first();

        $this->assertStringNotContainsString('customer@example.test', $raw->recipient);
        $this->assertStringNotContainsString('Pelanggan test', $raw->snapshot);
        $this->assertArrayNotHasKey('recipient', $delivery->toArray());
        $this->assertArrayNotHasKey('snapshot', $delivery->toArray());
    }

    public function test_inspection_does_not_dispatch_email_jobs(): void
    {
        NotificationDelivery::factory()->create();
        Queue::fake([DeliverTransactionNotice::class]);

        $this->artisan('notifications:dispatch-outbox', ['--inspect' => true])->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public static function obsoleteNotices(): array
    {
        return [
            'awaiting after payment' => ['awaiting_payment', 'paid'],
            'expired after payment' => ['expired', 'paid'],
            'confirmation after refund' => ['confirmation', 'refunded'],
            'voucher after cancellation' => ['voucher', 'cancelled'],
        ];
    }

    #[DataProvider('obsoleteNotices')]
    public function test_obsolete_notice_is_superseded_without_sending(string $type, string $status): void
    {
        $order = Order::factory()->create(['status' => $status]);
        $delivery = NotificationDelivery::factory()->create(['order_id' => $order->id, 'type' => $type]);
        Mail::fake();

        (new DeliverTransactionNotice($delivery->id))->handle();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'superseded', 'attempts' => 0]);
        $this->assertSame('superseded', $delivery->fresh()->delivery_log[0]['result']);
    }

    public function test_delivery_is_refused_inside_an_uncommitted_transaction(): void
    {
        $delivery = NotificationDelivery::factory()->create();
        Mail::fake();
        DB::beginTransaction();
        try {
            (new DeliverTransactionNotice($delivery->id))->handle();
            $this->fail('Uncommitted delivery must be refused.');
        } catch (\LogicException) {
            Mail::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_production_delivery_is_disabled_without_explicit_configuration(): void
    {
        $delivery = NotificationDelivery::factory()->create();
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['services.transaction_notices.enabled' => false]);
        Mail::fake();

        (new DeliverTransactionNotice($delivery->id))->handle();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'pending', 'attempts' => 0]);
        $this->app->detectEnvironment(fn (): string => 'testing');
    }
}
