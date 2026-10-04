<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Destination;
use App\Models\Partner;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DestinationDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Destinasi demo hanya dapat diisi pada lingkungan local atau testing.');
        }

        $created = DB::transaction(function (): int {
            $region = Region::query()->firstOrCreate(['code' => 'DESTINASI-DEMO-10'], ['name' => 'Desa Wisata Contoh', 'type' => 'village', 'is_active' => true]);
            $partner = Partner::query()->where('slug', 'mitra-pilot-demo')->where('status', 'approved')->first()
                ?? Partner::withTrashed()->firstOrCreate(['slug' => 'pengelola-destinasi-demo-10'], ['name' => 'Pengelola Destinasi Demo', 'region_id' => $region->id, 'status' => 'approved']);
            if ($partner->trashed() || $partner->status !== 'approved') {
                throw new RuntimeException('Pengelola demo tidak aktif; seeder tidak mengubah status pengelola yang sudah ada.');
            }
            $nature = Category::query()->firstOrCreate(['slug' => 'destinasi-demo-alam'], ['name' => 'Alam (Demo)', 'is_active' => true]);
            $culture = Category::query()->firstOrCreate(['slug' => 'destinasi-demo-budaya'], ['name' => 'Budaya (Demo)', 'is_active' => true]);
            $places = [
                ['air-terjun-embun', 'Air Terjun Embun — Demo', 'Jalur jalan kaki dan suasana air terjun untuk inspirasi wisata alam.', $nature->id],
                ['bukit-mentari', 'Bukit Mentari — Demo', 'Perbukitan dengan area pandang untuk menikmati suasana pagi.', $nature->id],
                ['pantai-senandung', 'Pantai Senandung — Demo', 'Kawasan pantai untuk bersantai dan mengenal cerita pesisir.', $nature->id],
                ['danau-cermin', 'Danau Cermin — Demo', 'Tepi danau dan ruang terbuka untuk perjalanan santai.', $nature->id],
                ['hutan-bambu', 'Hutan Bambu — Demo', 'Jalur teduh di antara rumpun bambu untuk wisata berjalan kaki.', $nature->id],
                ['kebun-teh-harmoni', 'Kebun Teh Harmoni — Demo', 'Pengalaman mengenal perkebunan dan kegiatan masyarakat setempat.', $nature->id],
                ['kampung-tenun', 'Kampung Tenun — Demo', 'Cerita kerajinan tenun dan kegiatan pengenalan budaya lokal.', $culture->id],
                ['museum-cerita-desa', 'Museum Cerita Desa — Demo', 'Ruang cerita tentang sejarah, tradisi, dan keseharian desa.', $culture->id],
                ['gua-pelangi', 'Gua Pelangi — Demo', 'Pengenalan kawasan gua dalam contoh itinerary wisata alam.', $nature->id],
                ['taman-budaya-nusantara', 'Taman Budaya Nusantara — Demo', 'Ruang kegiatan seni dan pengenalan tradisi dalam perjalanan budaya.', $culture->id],
            ];
            $created = 0;
            foreach ($places as [$slug, $name, $summary, $categoryId]) {
                $destination = Destination::withTrashed()->firstOrCreate(['slug' => 'destinasi-demo-'.$slug], [
                    'partner_id' => $partner->id, 'region_id' => $region->id, 'category_id' => $categoryId,
                    'name' => $name, 'publication_status' => 'published', 'summary' => $summary,
                    'description' => $summary.' Data destinasi ini merupakan contoh sintetis untuk mencoba katalog dan penyusunan paket wisata. Nama, lokasi, serta pengalaman bukan penawaran wisata nyata. Gambar pada aplikasi adalah ilustrasi.',
                    'address' => 'Kawasan wisata contoh, Desa Wisata Contoh (alamat sintetis; bukan lokasi nyata).',
                    'latitude' => null, 'longitude' => null, 'location_is_demo' => true,
                ]);
                $created += $destination->wasRecentlyCreated ? 1 : 0;
            }

            return $created;
        }, 3);

        $this->command?->info($created.' destinasi demo baru ditambahkan. Data yang sudah ada dipertahankan.');
    }
}
