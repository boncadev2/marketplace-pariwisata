<?php

namespace Tests\Feature;

use App\Models\UmkmOrder;
use App\Models\UmkmProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class UmkmOrderConcurrencyTest extends TestCase
{
    public function test_last_item_cannot_be_reserved_by_two_customers(): void
    {
        $this->race(false);
    }

    public function test_simultaneous_retry_creates_only_one_order(): void
    {
        $this->race(true);
    }

    private function race(bool $sameUser): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'wisata_concurrency_test') {
            $this->markTestSkipped('Requires isolated MySQL database wisata_concurrency_test.');
        }
        $product = UmkmProduct::factory()->create(['status' => 'published', 'stock' => 1, 'price' => 45000]);
        $first = User::factory()->create();
        $second = $sameUser ? $first : User::factory()->create();
        $start = (string) (microtime(true) + 1);
        $command = [PHP_BINARY, base_path('tests/Fixtures/order_umkm.php'), $start, $product->slug, (string) $first->id, 'umkm-concurrency-key-01'];
        $one = new Process($command, base_path());
        $command[4] = (string) $second->id;
        $two = new Process($command, base_path());
        $one->start();
        $two->start();
        $one->wait();
        $two->wait();
        $this->assertSame(0, $one->getExitCode(), $one->getErrorOutput());
        $this->assertSame(0, $two->getExitCode(), $two->getErrorOutput());
        $results = [$one->getOutput(), $two->getOutput()];
        sort($results);
        $this->assertSame($sameUser ? ['200', '201'] : ['201', '409'], $results);
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame(1, UmkmOrder::where('umkm_product_id', $product->id)->count());
    }
}
