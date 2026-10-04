<?php

namespace Tests\Feature;

use App\Exceptions\InventoryUnavailableException;
use App\Models\PackageDeparture;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Region;
use App\Models\TourPackage;
use App\Services\PackageDepartureService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageDepartureTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_guaranteed_departures_before_cutoff_accept_instant_booking(): void
    {
        $departure = $this->departure('guaranteed', CarbonImmutable::now()->addHour());
        app(PackageDepartureService::class)->reserveParticipant($departure, 'Wisatawan');

        $this->assertSame(1, $departure->fresh()->confirmed);
    }

    public function test_open_departure_requires_request_booking(): void
    {
        $departure = $this->departure('open', CarbonImmutable::now()->addHour());

        $this->expectException(InventoryUnavailableException::class);
        app(PackageDepartureService::class)->reserveParticipant($departure, 'Wisatawan');
    }

    private function departure(string $status, CarbonImmutable $cutoff): PackageDeparture
    {
        $region = Region::create(['code' => 'DEP-01', 'name' => 'Wilayah', 'type' => 'regency']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Mitra', 'slug' => 'mitra-dep', 'status' => 'approved']);
        $product = Product::create(['partner_id' => $partner->id, 'name' => 'Paket', 'slug' => 'paket-dep', 'type' => 'package', 'base_price' => 1, 'status' => 'published']);
        $package = TourPackage::create(['product_id' => $product->id, 'departure_type' => 'fixed', 'duration_days' => 1, 'meeting_point' => 'Titik', 'pricing_mode' => 'per_person', 'minimum_participants' => 1, 'maximum_participants' => 2]);

        return PackageDeparture::create(['tour_package_id' => $package->id, 'local_date' => '2026-10-10', 'timezone' => 'Asia/Jakarta', 'cutoff_at' => $cutoff, 'capacity' => 2, 'status' => $status]);
    }
}
