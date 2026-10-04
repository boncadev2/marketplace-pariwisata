<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Region;
use App\Models\UmkmProduct;
use Illuminate\Database\Seeder;

class UmkmProductSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Data demo UMKM hanya untuk local/testing.');
        }
        $region = Region::firstOrCreate(['code' => 'UMKM-DEMO'], ['name' => 'Desa UMKM Demo', 'type' => 'village']);
        $partner = Partner::firstOrCreate(['slug' => 'umkm-demo'], ['region_id' => $region->id, 'name' => 'UMKM Lokal Demo', 'status' => 'approved']);
        foreach ([['kopi-lokal-demo', 'Kopi Lokal — Demo', 'Kopi bubuk dalam kemasan 200 gram. Contoh produk untuk meninjau katalog UMKM.', 45000, 'kemasan 200 g'], ['tas-anyaman-demo', 'Tas Anyaman — Demo', 'Tas anyaman untuk kebutuhan sehari-hari. Contoh kerajinan, bukan penawaran penjual nyata.', 85000, 'pcs'], ['keripik-pisang-demo', 'Keripik Pisang — Demo', 'Keripik pisang dalam kemasan 150 gram. Data sintetis untuk demonstrasi katalog.', 25000, 'kemasan 150 g']] as [$slug,$name,$description,$price,$unit]) {
            UmkmProduct::firstOrCreate(['slug' => $slug], ['partner_id' => $partner->id, 'name' => $name, 'description' => $description, 'location' => 'Desa UMKM Demo, Indonesia (lokasi contoh)', 'price' => $price, 'stock' => 20, 'unit' => $unit, 'status' => 'published', 'is_demo' => true]);
        }
    }
}
