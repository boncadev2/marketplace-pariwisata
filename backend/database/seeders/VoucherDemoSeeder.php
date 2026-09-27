<?php

namespace Database\Seeders;

use App\Models\InventoryBucket;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use App\Services\InventoryReservationService;
use App\Services\TransactionOutbox;
use App\Services\VoucherService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VoucherDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Voucher demo hanya untuk environment local.');
        }
        $region = Region::firstOrCreate(['code' => 'DEMO-CHECKIN'], ['name' => 'Wilayah demonstrasi check-in', 'type' => 'regency']);
        $partner = Partner::firstOrCreate(['slug' => 'demo-checkin'], ['region_id' => $region->id, 'name' => 'Mitra demonstrasi check-in', 'status' => 'approved']);
        $staff = User::firstOrCreate(['email' => 'petugas.demo@example.test'], ['name' => 'Petugas Demonstrasi', 'password' => 'DemoPetugas2026!']);
        PartnerMember::firstOrCreate(['partner_id' => $partner->id, 'user_id' => $staff->id], ['role' => 'staff', 'is_active' => true]);
        $product = Product::firstOrCreate(['slug' => 'demo-checkin-ticket'], ['partner_id' => $partner->id, 'name' => 'Tiket demonstrasi check-in', 'type' => 'ticket', 'status' => 'published', 'base_price' => 100]);
        $date = now('Asia/Jakarta')->toDateString();
        $key = 'demo-checkin-'.$date;
        if ($existing = Order::where('idempotency_key', $key)->first()) {
            app(TransactionOutbox::class)->record($existing, 'confirmation', 'demo-paid', 'DEMONSTRASI — Email lokal Mailpit saja. Pembayaran sandbox berhasil.');

            return;
        }
        DB::transaction(function () use ($partner, $product, $date, $key): void {
            $bucket = InventoryBucket::firstOrCreate(['product_id' => $product->id, 'service_date' => $date, 'session_key' => 'default'], ['capacity' => 10]);
            $inventory = app(InventoryReservationService::class);
            $hold = $inventory->reserve($bucket, 1, CarbonImmutable::now()->addMinutes(15));
            $inventory->confirm($hold);
            $order = Order::create(['public_id' => 'demo-checkin-'.$date, 'partner_id' => $partner->id, 'idempotency_key' => $key, 'guest_access_hash' => Hash::make(str_repeat('g', 48)), 'customer_name' => 'Pelanggan demonstrasi', 'customer_email' => 'pelanggan.demo@example.test', 'status' => 'paid', 'currency' => 'IDR', 'total' => 100, 'policy_snapshot' => ['demo' => true]]);
            $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'quantity' => 1, 'unit_price' => 100, 'total' => 100, 'snapshot' => ['inventory_hold_id' => $hold->id, 'visit_date' => $date, 'demo' => true]]);
            app(VoucherService::class)->issue($order);
            app(TransactionOutbox::class)->record($order, 'confirmation', 'demo-paid', 'DEMONSTRASI — Email lokal Mailpit saja. Pembayaran sandbox berhasil.');
        });
    }
}
