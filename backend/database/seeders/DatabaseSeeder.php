<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Destination;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Region;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $regency = Region::create(['code' => 'DEMO-01', 'name' => 'Kabupaten Demo', 'type' => 'regency']);
        $village = Region::create(['parent_id' => $regency->id, 'code' => 'DEMO-01-01', 'name' => 'Desa Demo', 'type' => 'village']);
        $nature = Category::create(['name' => 'Alam', 'slug' => 'alam']);
        $culture = Category::create(['name' => 'Budaya', 'slug' => 'budaya']);
        $partnerA = Partner::create(['region_id' => $village->id, 'name' => 'Pengelola Alam Demo', 'slug' => 'pengelola-alam-demo', 'status' => 'approved']);
        $partnerB = Partner::create(['region_id' => $village->id, 'name' => 'Kelompok Budaya Demo', 'slug' => 'kelompok-budaya-demo', 'status' => 'approved']);
        Destination::create(['partner_id' => $partnerA->id, 'region_id' => $village->id, 'category_id' => $nature->id, 'name' => 'Air Terjun Demo', 'slug' => 'air-terjun-demo', 'publication_status' => 'published']);
        $destination = Destination::create(['partner_id' => $partnerB->id, 'region_id' => $village->id, 'category_id' => $culture->id, 'name' => 'Kampung Budaya Demo', 'slug' => 'kampung-budaya-demo', 'publication_status' => 'published']);
        Product::create(['partner_id' => $partnerB->id, 'destination_id' => $destination->id, 'name' => 'Tiket Kampung Budaya Demo', 'slug' => 'tiket-kampung-budaya-demo', 'type' => 'ticket', 'base_price' => 25000, 'status' => 'draft']);
        $this->call(ArticleDemoSeeder::class);
    }
}
