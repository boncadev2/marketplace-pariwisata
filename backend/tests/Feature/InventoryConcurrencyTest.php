<?php

namespace Tests\Feature;

use App\Models\InventoryBucket;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
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
