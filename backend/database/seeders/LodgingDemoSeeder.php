<?php

namespace Database\Seeders;

use App\Models\RoomType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LodgingDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Lodging demo data may only be seeded locally or in tests.');
        }
        DB::transaction(function (): void {
            $room = RoomType::query()->firstOrCreate(
                ['name' => 'Kamar Demonstrasi — bukan penawaran nyata'],
                ['description' => 'Data sintetis untuk latihan reservasi penginapan.', 'capacity' => 2, 'is_active' => true],
            );
            RoomType::query()->lockForUpdate()->findOrFail($room->id);
            if (! $room->exterior_image_url && ! $room->interior_image_url) {
                $room->update(['exterior_image_url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=900&q=85',
                    'interior_image_url' => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=900&q=85', 'photos_are_illustrations' => true]);
            }
            foreach (range(1, 60) as $offset) {
                $date = now('Asia/Jakarta')->addDays($offset)->toDateString();
                $room->inventories()->firstOrCreate(['date' => $date], ['stock' => 5]);
                $room->rates()->firstOrCreate(['date' => $date], ['price' => '150000.00']);
            }
        });
    }
}
