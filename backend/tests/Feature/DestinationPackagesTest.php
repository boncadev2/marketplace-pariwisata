<?php

namespace Tests\Feature;

use App\Models\Destination;
use App\Models\Product;
use App\Models\TourPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestinationPackagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_matching_published_packages_are_returned_once_including_secondary_itinerary_destinations(): void
    {
        $destination = Destination::factory()->published()->create();
        $other = Destination::factory()->published()->create();
        foreach (['Paket A', 'Paket B', 'Paket C'] as $name) {
            $package = $this->package($name, $destination, 'published', 'published', $other->id);
            $package->itineraryItems()->create(['destination_id' => $destination->id, 'day_number' => 1, 'sequence' => 2, 'title' => 'Kunjungan kedua', 'starts_at' => '12:00', 'duration_minutes' => 60]);
        }
        $this->package('Tidak terkait', $other);
        $this->package('Produk draft', $destination, 'draft');
        $this->package('Itinerary draft', $destination, 'published', 'draft');
        $deleted = $this->package('Dihapus', $destination);
        $deleted->product->delete();
        Product::factory()->create(['type' => 'ticket', 'destination_id' => $destination->id]);

        $url = '/api/v1/destinations/'.$destination->slug.'/packages';
        $this->getJson($url)->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.name', 'Paket A')->assertJsonPath('data.1.name', 'Paket B')
            ->assertJsonPath('data.2.name', 'Paket C')->assertJsonPath('destination.name', $destination->name);
        $this->getJson($url.'?per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Paket C');
    }

    public function test_empty_catalog_updates_after_itinerary_changes_and_unpublished_destination_returns_404(): void
    {
        $destination = Destination::factory()->published()->create();
        $other = Destination::factory()->published()->create();
        $package = $this->package('Paket pindah tujuan', $other);
        $url = '/api/v1/destinations/'.$destination->slug.'/packages';
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        $package->itineraryItems()->first()->update(['destination_id' => $destination->id]);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($url.'?per_page=51')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson($url.'?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
        $destination->update(['publication_status' => 'draft']);
        $this->getJson($url)->assertNotFound();
        $destination->delete();
        $this->getJson($url)->assertNotFound();
        $this->getJson('/api/v1/destinations/tidak-ada/packages')->assertNotFound();
    }

    private function package(string $name, Destination $destination, string $productStatus = 'published', string $packageStatus = 'published', ?int $primaryDestinationId = null): TourPackage
    {
        $product = Product::factory()->create(['name' => $name, 'type' => 'package', 'status' => $productStatus, 'destination_id' => $primaryDestinationId ?? $destination->id]);
        $package = TourPackage::factory()->create(['product_id' => $product->id, 'status' => $packageStatus, 'duration_days' => 1, 'meeting_point' => 'Gerbang', 'pricing_mode' => 'per_person', 'minimum_participants' => 1, 'maximum_participants' => 10]);
        $package->itineraryItems()->create(['destination_id' => $destination->id, 'day_number' => 1, 'sequence' => 1, 'title' => 'Kunjungan', 'starts_at' => '09:00', 'duration_minutes' => 60]);

        return $package;
    }
}
