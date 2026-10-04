<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTourPackageException;
use App\Models\PackageItineraryItem;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Region;
use App\Models\TourPackage;
use App\Services\TourPackagePublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_with_overlapping_itinerary_cannot_be_published(): void
    {
        $package = $this->package();
        PackageItineraryItem::create(['tour_package_id' => $package->id, 'day_number' => 1, 'sequence' => 1, 'title' => 'Mulai', 'starts_at' => '09:00', 'duration_minutes' => 120]);
        PackageItineraryItem::create(['tour_package_id' => $package->id, 'day_number' => 1, 'sequence' => 2, 'title' => 'Bentrok', 'starts_at' => '10:00', 'duration_minutes' => 60]);

        $this->expectException(InvalidTourPackageException::class);
        app(TourPackagePublicationService::class)->publish($package);
    }

    public function test_valid_package_can_be_published(): void
    {
        $package = $this->package();
        PackageItineraryItem::create(['tour_package_id' => $package->id, 'day_number' => 1, 'sequence' => 1, 'title' => 'Mulai', 'starts_at' => '09:00', 'duration_minutes' => 60]);
        PackageItineraryItem::create(['tour_package_id' => $package->id, 'day_number' => 2, 'sequence' => 1, 'title' => 'Selesai', 'starts_at' => '10:00', 'duration_minutes' => 60]);

        $published = app(TourPackagePublicationService::class)->publish($package);

        $this->assertSame('published', $published->status);
    }

    private function package(): TourPackage
    {
        $region = Region::create(['code' => 'PACK-01', 'name' => 'Wilayah Paket', 'type' => 'regency']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Mitra Paket', 'slug' => 'mitra-paket', 'status' => 'approved']);
        $product = Product::create(['partner_id' => $partner->id, 'name' => 'Paket Uji', 'slug' => 'paket-uji', 'type' => 'package', 'base_price' => 300_000, 'status' => 'draft']);

        return TourPackage::create(['product_id' => $product->id, 'departure_type' => 'fixed', 'duration_days' => 2, 'meeting_point' => 'Alun-alun', 'pricing_mode' => 'per_person', 'minimum_participants' => 2, 'maximum_participants' => 10]);
    }
}
