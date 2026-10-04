<?php

namespace Tests\Feature;

use App\Models\ReservationPayment;
use App\Models\UmkmOrder;
use App\Models\UmkmProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReservationPaymentConcurrencyTest extends TestCase
{
    public function test_simultaneous_checkout_creates_one_midtrans_session(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'wisata_concurrency_test') {
            $this->markTestSkipped('Requires isolated MySQL database wisata_concurrency_test.');
        }
        $product = UmkmProduct::factory()->create(['stock' => 1]);
        $user = User::factory()->create();
        $order = UmkmOrder::create(['user_id' => $user->id, 'umkm_product_id' => $product->id, 'public_id' => fake()->uuid(), 'idempotency_key' => fake()->uuid(), 'payload_hash' => str_repeat('a', 64), 'quantity' => 1, 'total' => 45000, 'status' => 'reserved_sandbox', 'customer_name' => 'Race Demo', 'customer_phone' => '0000000000', 'snapshot' => []]);
        $command = [PHP_BINARY, base_path('tests/Fixtures/pay_reservation.php'), (string) (microtime(true) + 1), (string) $order->id];
        $one = new Process($command, base_path());
        $two = new Process($command, base_path());
        $one->start();
        $two->start();
        $one->wait();
        $two->wait();
        $this->assertSame(0, $one->getExitCode(), $one->getErrorOutput());
        $this->assertSame(0, $two->getExitCode(), $two->getErrorOutput());
        $counts = [$one->getOutput(), $two->getOutput()];
        sort($counts);
        $this->assertSame(['0', '1'], $counts);
        $this->assertSame(1, ReservationPayment::where('kind', 'umkm')->where('booking_id', $order->id)->count());
        $this->assertSame('pending', $order->reservationPayment->status);
    }
}
