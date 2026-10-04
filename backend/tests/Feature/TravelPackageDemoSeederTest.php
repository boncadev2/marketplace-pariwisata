<?php

namespace Tests\Feature;

use App\Models\Destination;
use App\Models\InventoryBucket;
use App\Models\Product;
use Database\Seeders\DestinationDemoSeeder;
use Database\Seeders\TravelPackageDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelPackageDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_twelve_packages_and_preserves_edits_inventory_and_deleted_records_on_repeat(): void
    {
        $this->freezeTime();
        $this->seed(DestinationDemoSeeder::class);
        $this->seed(TravelPackageDemoSeeder::class);
        $this->assertDatabaseCount('products', 12);
        $this->assertDatabaseCount('inventory_buckets', 84);
        $destination = Destination::where('slug', 'destinasi-demo-air-terjun-embun')->firstOrFail();
        $this->getJson('/api/v1/destinations/'.$destination->slug.'/packages')->assertOk()->assertJsonCount(3, 'data');
        $product = Product::where('destination_id', $destination->id)->firstOrFail();
        $product->update(['name' => 'Diubah pengelola', 'status' => 'draft']);
        InventoryBucket::where('product_id', $product->id)->update(['capacity' => 4, 'confirmed' => 2]);
        $deleted = Product::where('id', '!=', $product->id)->firstOrFail();
        $deleted->delete();
        $this->seed(TravelPackageDemoSeeder::class);
        $this->assertDatabaseCount('products', 12);
        $this->assertSame('Diubah pengelola', $product->fresh()->name);
        $this->assertSame('draft', $product->fresh()->status);
        $this->assertSame(4, (int) InventoryBucket::where('product_id', $product->id)->first()->capacity);
        $this->assertSoftDeleted($deleted);
    }
}
