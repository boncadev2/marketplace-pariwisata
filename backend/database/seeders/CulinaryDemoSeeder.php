<?php

namespace Database\Seeders;

use App\Models\CulinaryPlace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CulinaryDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Culinary demo data may only be seeded locally or in tests.');
        }
        DB::transaction(function (): void {
            $place = CulinaryPlace::query()->firstOrCreate(
                ['name' => 'Dapur Demonstrasi — bukan penawaran nyata'],
                ['description' => 'Data sintetis untuk simulasi reservasi paket makan.', 'location' => 'Desa Demonstrasi', 'location_is_demo' => true, 'is_active' => true],
            );
            CulinaryPlace::query()->lockForUpdate()->findOrFail($place->id);
            foreach (range(1, 14) as $offset) {
                $place->mealSlots()->firstOrCreate(
                    ['time_slot' => now()->addDays($offset)->setTime(12, 0), 'package_name' => 'Paket Makan Demo'],
                    ['price' => '75000.00', 'capacity' => 10, 'reserved' => 0, 'is_active' => true],
                );
            }
        });
    }
}
