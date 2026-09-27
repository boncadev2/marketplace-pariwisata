<?php

namespace Tests\Feature;

use App\Models\InventoryBucket;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use App\Models\Region;
use App\Services\InventoryReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    public function test_expiry_and_paid_processing_do_not_double_allocate_inventory(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
            $this->markTestSkipped('Requires isolated MySQL database wisata_concurrency_test.');
        }
        $suffix = Str::uuid()->toString();
        $region = Region::create(['code' => $suffix, 'name' => 'Race', 'type' => 'regency']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Race', 'slug' => $suffix, 'status' => 'approved']);
        $product = Product::create(['partner_id' => $partner->id, 'name' => 'Race', 'slug' => $suffix, 'type' => 'ticket', 'status' => 'published']);
        $bucket = InventoryBucket::create(['product_id' => $product->id, 'service_date' => '2026-10-10', 'capacity' => 1]);
        $hold = app(InventoryReservationService::class)->reserve($bucket, 1, CarbonImmutable::now()->subMinute());
        $order = Order::create(['public_id' => $suffix, 'partner_id' => $partner->id, 'idempotency_key' => $suffix, 'guest_access_hash' => 'test', 'customer_name' => 'Race', 'customer_email' => 'race@example.test', 'status' => 'pending_payment', 'currency' => 'IDR', 'total' => 100, 'policy_snapshot' => []]);
        $order->items()->create(['product_id' => $product->id, 'name' => 'Race', 'quantity' => 1, 'unit_price' => 100, 'total' => 100, 'snapshot' => ['inventory_hold_id' => $hold->id]]);
        $attempt = PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'sandbox', 'provider_reference' => $suffix, 'status' => 'pending', 'currency' => 'IDR', 'amount' => 100]);
        $event = PaymentWebhookEvent::create(['provider' => 'sandbox', 'provider_event_key' => $suffix, 'payment_attempt_id' => $attempt->id, 'payload' => ['status' => 'succeeded', 'amount' => 100, 'currency' => 'IDR']]);
        $start = (string) (microtime(true) + 1);
        $paid = new Process([PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $event->id, $start, 'paid'], base_path());
        $expiry = new Process([PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), '0', $start, 'expire'], base_path());

        $paid->start();
        $expiry->start();
        $paid->wait();
        $expiry->wait();

        $this->assertSame(0, $paid->getExitCode(), $paid->getErrorOutput());
        $this->assertSame(0, $expiry->getExitCode(), $expiry->getErrorOutput());
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('expired', $hold->fresh()->state);
        $this->assertSame(1, $bucket->fresh()->confirmed);
        $this->assertSame(0, $bucket->fresh()->held);
        $this->assertSame(0, $bucket->fresh()->available());
        $this->assertSame(1, $bucket->holds()->where('state', 'confirmed')->count());
    }

    public function test_two_processes_competing_for_last_unit_reserve_only_once(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
            $this->markTestSkipped('Requires isolated MySQL database wisata_concurrency_test.');
        }
        $suffix = Str::uuid()->toString();
        $region = Region::create(['code' => $suffix, 'name' => 'Test', 'type' => 'regency']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Test', 'slug' => $suffix, 'status' => 'approved']);
        $product = Product::create(['partner_id' => $partner->id, 'name' => 'Test', 'slug' => $suffix, 'type' => 'ticket', 'status' => 'published']);
        $bucket = InventoryBucket::create(['product_id' => $product->id, 'service_date' => '2026-10-10', 'capacity' => 1]);
        $start = (string) (microtime(true) + 1);
        $command = [PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $bucket->id, $start];
        $first = new Process($command, base_path());
        $second = new Process($command, base_path());

        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $this->assertSame(0, $first->getExitCode(), $first->getErrorOutput());
        $this->assertSame(0, $second->getExitCode(), $second->getErrorOutput());
        $results = [$first->getOutput(), $second->getOutput()];
        sort($results);
        $this->assertSame(['reserved', 'unavailable'], $results);
        $this->assertSame(1, $bucket->fresh()->held);
        $this->assertSame(0, $bucket->fresh()->available());
        $this->assertSame(1, $bucket->holds()->count());
    }
}
