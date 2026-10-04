<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Destination;
use App\Models\Partner;
use App\Models\Region;
use Database\Seeders\DestinationDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestinationDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_adds_ten_published_destinations_without_duplicates_or_overwriting_edits(): void
    {
        $existing = Destination::factory()->published()->create(['name' => 'Destinasi Lama']);
        $this->getJson('/api/v1/destinations')->assertOk()->assertJsonPath('meta.total', 1);
        $this->seed(DestinationDemoSeeder::class);
        $this->assertDatabaseCount('destinations', 11);
        $this->getJson('/api/v1/destinations?per_page=4')->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('meta.total', 11);
        $this->getJson('/api/v1/destinations?per_page=50')->assertOk()->assertJsonCount(11, 'data');
        $this->getJson('/api/v1/destinations/destinasi-demo-air-terjun-embun')->assertOk()->assertJsonPath('data.name', 'Air Terjun Embun — Demo')->assertJsonPath('data.latitude', null);
        $this->assertSame('Destinasi Lama', $existing->fresh()->name);
        $item = Destination::where('slug', 'destinasi-demo-air-terjun-embun')->firstOrFail();
        $item->update(['name' => 'Nama Diedit Pengelola', 'publication_status' => 'draft']);
        $removed = Destination::where('slug', 'destinasi-demo-bukit-mentari')->firstOrFail();
        $removed->delete();
        $regions = Region::count();
        $categories = Category::count();
        $partners = Partner::count();
        $this->seed(DestinationDemoSeeder::class);
        $this->assertSame(11, Destination::withTrashed()->count());
        $this->assertSame($regions, Region::count());
        $this->assertSame($categories, Category::count());
        $this->assertSame($partners, Partner::count());
        $this->assertSame('Nama Diedit Pengelola', $item->fresh()->name);
        $this->assertSame('draft', $item->fresh()->publication_status);
        $this->assertTrue($removed->fresh()->trashed());
    }

    public function test_seeder_reuses_existing_approved_pilot_partner_without_modifying_it(): void
    {
        $partner = Partner::factory()->create(['slug' => 'mitra-pilot-demo', 'name' => 'Mitra Yang Sudah Ada']);
        $this->seed(DestinationDemoSeeder::class);
        $this->assertDatabaseCount('partners', 1);
        $this->assertSame(10, Destination::where('partner_id', $partner->id)->count());
        $this->assertSame('Mitra Yang Sudah Ada', $partner->fresh()->name);
        $this->assertDatabaseCount('users', 0);
    }
}
