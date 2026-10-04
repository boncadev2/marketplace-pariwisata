<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\InventoryBucket;
use App\Models\Product;
use App\Services\TourPackagePublicationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TravelPackageDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Paket demo hanya tersedia pada local/testing.');
        }
        $created = DB::transaction(function (): int {
            $count = 0;
            $destinations = Destination::query()->where('slug', 'like', 'destinasi-demo-%')->where('publication_status', 'published')->orderBy('id')->get();
            foreach ($destinations as $destination) {
                $variants = $destination->slug === 'destinasi-demo-air-terjun-embun' ? ['santai', 'pagi', 'keluarga'] : ['santai'];
                foreach ($variants as $index => $variant) {
                    $product = Product::withTrashed()->firstOrCreate(['slug' => 'paket-'.$destination->slug.'-'.$variant], ['partner_id' => $destination->partner_id, 'destination_id' => $destination->id, 'name' => 'Paket '.str_replace(' — Demo', '', $destination->name).' '.ucfirst($variant).' — Demo', 'type' => 'package', 'currency' => 'IDR', 'base_price' => 100000 + $index * 25000, 'status' => 'published']);
                    if (! $product->wasRecentlyCreated) {
                        continue;
                    }
                    $package = $product->tourPackage()->create(['description' => 'Paket demonstrasi sintetis untuk mencoba itinerary dan pembayaran sandbox. Bukan penawaran wisata nyata.', 'duration_days' => 1, 'meeting_point' => 'Gerbang kawasan contoh (lokasi demonstrasi)', 'transportation' => 'Transportasi contoh disediakan penyelenggara', 'guide_information' => 'Pendamping demonstrasi', 'minimum_participants' => 1, 'maximum_participants' => 10, 'pricing_mode' => 'per_person', 'departure_type' => 'open', 'status' => 'draft', 'inclusions' => ['Kunjungan destinasi dan pendamping (demonstrasi)'], 'exclusions' => ['Makan, belanja UMKM dan transportasi menuju titik kumpul']]);
                    $package->itineraryItems()->create(['destination_id' => $destination->id, 'title' => $destination->name, 'day_number' => 1, 'sequence' => 1, 'starts_at' => '09:00', 'duration_minutes' => 180, 'quantity' => 1, 'included' => true, 'additional_cost' => 0, 'description' => 'Contoh kunjungan tiga jam; jadwal dan tempat bukan wisata nyata.']);
                    app(TourPackagePublicationService::class)->publish($package);
                    for ($day = 1; $day <= 7; $day++) {
                        InventoryBucket::firstOrCreate(['product_id' => $product->id, 'service_date' => now('Asia/Jakarta')->addDays($day)->toDateString(), 'session_key' => 'default'], ['capacity' => 10, 'held' => 0, 'confirmed' => 0, 'is_closed' => false]);
                    }
                    $count++;
                }
            }

            return $count;
        }, 3);
        $this->command?->info($created.' paket demo baru ditambahkan. Paket dan kuota lama dipertahankan.');
    }
}
