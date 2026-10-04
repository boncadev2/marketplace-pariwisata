<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Destination;
use App\Models\InventoryBucket;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\PilotControl;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class PilotDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Pilot demo data may only be seeded in local or testing environments.');
        }

        $password = (string) config('pilot.demo_password');
        if (Str::length($password) < 16) {
            throw new RuntimeException('PILOT_DEMO_PASSWORD must contain at least 16 characters.');
        }

        DB::transaction(function () use ($password): void {
            $regency = Region::query()->updateOrCreate(
                ['code' => 'PILOT-DEMO'],
                ['name' => 'Kabupaten Demonstrasi', 'type' => 'regency', 'is_active' => true],
            );
            $village = Region::query()->updateOrCreate(
                ['code' => 'PILOT-DEMO-01'],
                ['parent_id' => $regency->id, 'name' => 'Desa Demonstrasi', 'type' => 'village', 'is_active' => true],
            );
            $category = Category::query()->updateOrCreate(
                ['slug' => 'alam-pilot-demo'],
                ['name' => 'Alam Pilot Demo', 'is_active' => true],
            );
            $partner = Partner::query()->updateOrCreate(
                ['slug' => 'mitra-pilot-demo'],
                [
                    'region_id' => $village->id,
                    'name' => 'Mitra Pilot Demonstrasi',
                    'status' => 'approved',
                    'contact_email' => 'mitra.pilot@example.test',
                    'contact_phone' => null,
                ],
            );
            $destination = Destination::query()->updateOrCreate(
                ['slug' => 'destinasi-pilot-demo'],
                [
                    'partner_id' => $partner->id,
                    'region_id' => $village->id,
                    'category_id' => $category->id,
                    'name' => 'Destinasi Pilot Demonstrasi',
                    'publication_status' => 'published',
                    'summary' => 'Data sintetis untuk simulasi operator. Bukan destinasi atau penawaran nyata.',
                    'description' => 'Gunakan hanya untuk latihan pembelian, check-in, pembatalan, refund, dan penanganan insiden.',
                ],
            );
            $product = Product::query()->updateOrCreate(
                ['slug' => 'tiket-pilot-demo'],
                [
                    'partner_id' => $partner->id,
                    'destination_id' => $destination->id,
                    'name' => 'Tiket Pilot Demonstrasi',
                    'type' => 'ticket',
                    'currency' => 'IDR',
                    'base_price' => 10_000,
                    'status' => 'published',
                ],
            );

            foreach (range(1, 14) as $day) {
                $serviceDate = now('Asia/Jakarta')->addDays($day)->startOfDay();
                InventoryBucket::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'service_date' => $serviceDate,
                        'session_key' => 'default',
                    ],
                    ['capacity' => 10, 'is_closed' => false],
                );
            }

            $admin = $this->demoUser('Admin Pilot Demo', 'admin.pilot@example.test', $password, 'super_admin');
            $owner = $this->demoUser('Pemilik Mitra Pilot Demo', 'owner.pilot@example.test', $password);
            $this->demoUser('Pengunjung Pilot Demo', 'visitor.pilot@example.test', $password);
            $staff = $this->demoUser('Petugas Pilot Demo', 'staff.pilot@example.test', $password);

            PartnerMember::query()->updateOrCreate(
                ['partner_id' => $partner->id, 'user_id' => $owner->id],
                ['role' => 'owner', 'is_active' => true, 'destination_id' => null],
            );
            PartnerMember::query()->updateOrCreate(
                ['partner_id' => $partner->id, 'user_id' => $staff->id],
                ['role' => 'staff', 'is_active' => true, 'destination_id' => $destination->id],
            );

            PilotControl::query()->updateOrCreate(['id' => 1], [
                'checkout_enabled' => false,
                'reason' => 'Checkout ditutup setelah seeding. Operator harus menyelesaikan checklist simulasi sebelum membukanya.',
                'changed_by' => $admin->id,
            ]);
        });
    }

    private function demoUser(string $name, string $email, string $password, string $role = 'customer'): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make($password),
            'platform_role' => $role,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
