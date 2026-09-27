<?php

namespace Tests\Feature;

use App\Exceptions\InventoryUnavailableException;
use App\Models\InventoryBucket;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Region;
use App\Services\InventoryReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_reservation_can_take_the_last_available_unit(): void
    {
        $bucket = $this->bucket(1);
        $service = app(InventoryReservationService::class);

        $service->reserve($bucket, 1, CarbonImmutable::now()->addMinutes(15));

        $this->expectException(InventoryUnavailableException::class);
        $service->reserve($bucket, 1, CarbonImmutable::now()->addMinutes(15));
    }

    public function test_releasing_a_hold_twice_only_returns_capacity_once(): void
    {
        $bucket = $this->bucket(1);
        $service = app(InventoryReservationService::class);
        $hold = $service->reserve($bucket, 1, CarbonImmutable::now()->addMinutes(15));

        $service->release($hold);
        $service->release($hold->fresh());

        $this->assertSame(0, $bucket->fresh()->held);
        $this->assertSame(1, $bucket->fresh()->available());
    }

    public function test_expired_hold_is_released_before_a_new_reservation_is_checked(): void
    {
        $bucket = $this->bucket(1);
        $service = app(InventoryReservationService::class);
        $expired = $service->reserve($bucket, 1, CarbonImmutable::now()->subMinute());

        $replacement = $service->reserve($bucket, 1, CarbonImmutable::now()->addMinutes(15));

        $this->assertSame('expired', $expired->fresh()->state);
        $this->assertSame('active', $replacement->state);
        $this->assertSame(1, $bucket->fresh()->held);
    }

    private function bucket(int $capacity): InventoryBucket
    {
        $region = Region::create(['code' => 'STOCK-01', 'name' => 'Wilayah Stok', 'type' => 'regency']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Mitra Stok', 'slug' => 'mitra-stok', 'status' => 'approved']);
        $product = Product::create(['partner_id' => $partner->id, 'name' => 'Tiket Stok', 'slug' => 'tiket-stok', 'type' => 'ticket', 'base_price' => 50_000, 'status' => 'published']);

        return InventoryBucket::create([
            'product_id' => $product->id,
            'service_date' => '2026-10-10',
            'session_key' => 'pagi',
            'capacity' => $capacity,
        ]);
    }
}
