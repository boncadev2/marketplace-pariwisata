<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\CrossVillageAgreement;
use App\Models\InventoryBucket;
use App\Models\InventoryHold;
use App\Models\LodgingBooking;
use App\Models\MealBooking;
use App\Models\MealSlot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use App\Models\Region;
use App\Models\RoomType;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CrossVillageAllocationService;
use App\Services\InventoryReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    public function test_two_scanners_can_redeem_same_voucher_only_once(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
            $this->markTestSkipped('Requires isolated MySQL database wisata_concurrency_test.');
        }
        $suffix = Str::uuid()->toString();
        $region = Region::create(['code' => $suffix, 'name' => 'Scan', 'type' => 'regency']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Scan', 'slug' => $suffix, 'status' => 'approved']);
        $product = Product::create(['partner_id' => $partner->id, 'name' => 'Scan', 'slug' => $suffix, 'type' => 'ticket']);
        $order = Order::create(['public_id' => $suffix, 'partner_id' => $partner->id, 'idempotency_key' => $suffix, 'guest_access_hash' => 'test', 'customer_name' => 'Scan', 'customer_email' => 'scan@example.test', 'status' => 'paid', 'currency' => 'IDR', 'total' => 100, 'policy_snapshot' => []]);
        $item = $order->items()->create(['product_id' => $product->id, 'name' => 'Scan', 'quantity' => 1, 'unit_price' => 100, 'total' => 100, 'snapshot' => []]);
        $token = Str::random(48);
        $voucher = Voucher::create(['order_item_id' => $item->id, 'partner_id' => $partner->id, 'token_hash' => hash('sha256', $token), 'token' => $token, 'service_date' => now('Asia/Jakarta')->toDateString(), 'admissions' => 1]);
        $staff = User::factory()->create();
        PartnerMember::create(['partner_id' => $partner->id, 'user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
        $command = [PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $voucher->id, (string) (microtime(true) + 1), 'redeem', (string) $staff->id];
        $first = new Process($command, base_path());
        $second = new Process($command, base_path());

        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $this->assertSame(0, $first->getExitCode(), $first->getErrorOutput());
        $this->assertSame(0, $second->getExitCode(), $second->getErrorOutput());
        $results = [$first->getOutput(), $second->getOutput()];
        sort($results);
        $this->assertSame(['already_used', 'redeemed'], $results);
        $this->assertSame(1, $voucher->fresh()->used_admissions);
        $this->assertSame('redeemed', $voucher->fresh()->status);
    }

    public function test_expiry_and_paid_processing_do_not_double_allocate_inventory(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
            $this->markTestSkipped('Requires isolated MySQL database wisata_concurrency_test.');
        }
        $suffix = Str::uuid()->toString();
        $region = Region::create(['code' => $suffix, 'name' => 'Race', 'type' => 'regency']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Race', 'slug' => $suffix, 'status' => 'approved']);
        $product = Product::create(['partner_id' => $partner->id, 'name' => 'Race', 'slug' => $suffix, 'type' => 'ticket', 'status' => 'published']);
        $bucket = InventoryBucket::create(['product_id' => $product->id, 'service_date' => '2026-10-10', 'capacity' => 1]);
        $hold = app(InventoryReservationService::class)->reserve($bucket, 1, CarbonImmutable::now()->subMinute());
        $order = Order::create(['public_id' => $suffix, 'partner_id' => $partner->id, 'idempotency_key' => $suffix, 'guest_access_hash' => 'test', 'customer_name' => 'Race', 'customer_email' => 'race@example.test', 'status' => 'pending_payment', 'currency' => 'IDR', 'total' => 100, 'policy_snapshot' => []]);
        $order->items()->create(['product_id' => $product->id, 'name' => 'Race', 'quantity' => 1, 'unit_price' => 100, 'total' => 100, 'snapshot' => ['inventory_hold_id' => $hold->id]]);
        $attempt = PaymentAttempt::create(['order_id' => $order->id, 'provider' => 'sandbox', 'provider_reference' => $suffix, 'status' => 'pending', 'currency' => 'IDR', 'amount' => 100]);
        $event = PaymentWebhookEvent::create(['provider' => 'sandbox', 'provider_event_key' => $suffix, 'payment_attempt_id' => $attempt->id, 'payload' => ['status' => 'succeeded', 'amount' => 100, 'currency' => 'IDR']]);
        $start = (string) (microtime(true) + 1);
        $paid = new Process([PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $event->id, $start, 'paid'], base_path());
        $expiry = new Process([PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), '0', $start, 'expire'], base_path());

        $paid->start();
        $expiry->start();
        $paid->wait();
        $expiry->wait();

        $this->assertSame(0, $paid->getExitCode(), $paid->getErrorOutput());
        $this->assertSame(0, $expiry->getExitCode(), $expiry->getErrorOutput());
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('expired', $hold->fresh()->state);
        $this->assertSame(1, $bucket->fresh()->confirmed);
        $this->assertSame(0, $bucket->fresh()->held);
        $this->assertSame(0, $bucket->fresh()->available());
        $this->assertSame(1, $bucket->holds()->where('state', 'confirmed')->count());
    }

    public function test_two_processes_competing_for_last_unit_reserve_only_once(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
            $this->markTestSkipped('Requires isolated MySQL database wisata_concurrency_test.');
        }
        $suffix = Str::uuid()->toString();
        $region = Region::create(['code' => $suffix, 'name' => 'Test', 'type' => 'regency']);
        $partner = Partner::create(['region_id' => $region->id, 'name' => 'Test', 'slug' => $suffix, 'status' => 'approved']);
        $product = Product::create(['partner_id' => $partner->id, 'name' => 'Test', 'slug' => $suffix, 'type' => 'ticket', 'status' => 'published']);
        $bucket = InventoryBucket::create(['product_id' => $product->id, 'service_date' => '2026-10-10', 'capacity' => 1]);
        $start = (string) (microtime(true) + 1);
        $command = [PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $bucket->id, $start];
        $first = new Process($command, base_path());
        $second = new Process($command, base_path());

        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $this->assertSame(0, $first->getExitCode(), $first->getErrorOutput());
        $this->assertSame(0, $second->getExitCode(), $second->getErrorOutput());
        $results = [$first->getOutput(), $second->getOutput()];
        sort($results);
        $this->assertSame(['reserved', 'unavailable'], $results);
        $this->assertSame(1, $bucket->fresh()->held);
        $this->assertSame(0, $bucket->fresh()->available());
        $this->assertSame(1, $bucket->holds()->count());
    }

    public function test_late_payment_uses_current_hold_state_after_another_process_expires_it(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
            $this->markTestSkipped('Requires isolated MySQL database wisata_concurrency_test.');
        }
        $product = Product::factory()->create();
        $bucket = InventoryBucket::factory()->for($product)->create(['capacity' => 1]);
        $hold = app(InventoryReservationService::class)->reserve($bucket, 1, CarbonImmutable::now()->subMinute());

        $replacement = DB::transaction(function () use ($hold) {
            $snapshot = InventoryHold::query()->findOrFail($hold->id);
            $expiry = new Process([PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), '0', '0', 'expire'], base_path());
            $expiry->run();
            $this->assertSame(0, $expiry->getExitCode(), $expiry->getErrorOutput());

            return app(InventoryReservationService::class)->confirmOrReplace($snapshot);
        });

        $this->assertSame('confirmed', $replacement->state);
        $this->assertSame('expired', $hold->fresh()->state);
        $this->assertSame(0, $bucket->fresh()->held);
        $this->assertSame(1, $bucket->fresh()->confirmed);
        $this->assertSame(0, $bucket->fresh()->available());
    }

    public static function lodgingRaces(): array
    {
        return ['competing customers' => [false], 'duplicate retry' => [true]];
    }

    #[DataProvider('lodgingRaces')]
    public function test_multi_night_lodging_races_do_not_oversell(bool $sameCustomer): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
            $this->markTestSkipped('Requires isolated MySQL database wisata_concurrency_test.');
        }
        $room = RoomType::factory()->create();
        foreach (['2026-10-10', '2026-10-11'] as $date) {
            $room->inventories()->create(['date' => $date, 'stock' => 1]);
            $room->rates()->create(['date' => $date, 'price' => '100.00']);
        }
        $firstUser = User::factory()->create();
        $secondUser = $sameCustomer ? $firstUser : User::factory()->create();
        $key = Str::uuid()->toString();
        $start = (string) (microtime(true) + 1);
        $first = new Process([PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $room->id, $start, 'lodging', (string) $firstUser->id, $key], base_path());
        $second = new Process([PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $room->id, $start, 'lodging', (string) $secondUser->id, $key], base_path());

        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $this->assertSame(0, $first->getExitCode(), $first->getErrorOutput());
        $this->assertSame(0, $second->getExitCode(), $second->getErrorOutput());
        $results = [$first->getOutput(), $second->getOutput()];
        sort($results);
        $this->assertSame($sameCustomer ? ['reserved', 'reserved'] : ['reserved', 'unavailable'], $results);
        $this->assertSame(1, LodgingBooking::query()->where('room_type_id', $room->id)->count());
        $this->assertSame([0, 0], $room->inventories()->orderBy('date')->pluck('stock')->all());

        $booking = LodgingBooking::query()->where('room_type_id', $room->id)->firstOrFail();
        $cancelStart = (string) (microtime(true) + 1);
        $cancelCommand = [PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $booking->id, $cancelStart, 'lodging-cancel', (string) $booking->user_id];
        $cancelFirst = new Process($cancelCommand, base_path());
        $cancelSecond = new Process($cancelCommand, base_path());
        $cancelFirst->start();
        $cancelSecond->start();
        $cancelFirst->wait();
        $cancelSecond->wait();

        $this->assertSame(0, $cancelFirst->getExitCode(), $cancelFirst->getErrorOutput());
        $this->assertSame(0, $cancelSecond->getExitCode(), $cancelSecond->getErrorOutput());
        $this->assertSame([1, 1], $room->inventories()->orderBy('date')->pluck('stock')->all());
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    #[DataProvider('lodgingRaces')]
    public function test_meal_slot_races_do_not_oversell(bool $sameCustomer): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
            $this->markTestSkipped('Requires isolated MySQL database wisata_concurrency_test.');
        }
        $slot = MealSlot::factory()->create(['capacity' => 1, 'price' => '100.00']);
        $firstUser = User::factory()->create();
        $secondUser = $sameCustomer ? $firstUser : User::factory()->create();
        $key = Str::uuid()->toString();
        $start = (string) (microtime(true) + 1);
        $first = new Process([PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $slot->id, $start, 'meal', (string) $firstUser->id, $key], base_path());
        $second = new Process([PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $slot->id, $start, 'meal', (string) $secondUser->id, $key], base_path());

        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $this->assertSame(0, $first->getExitCode(), $first->getErrorOutput());
        $this->assertSame(0, $second->getExitCode(), $second->getErrorOutput());
        $results = [$first->getOutput(), $second->getOutput()];
        sort($results);
        $this->assertSame($sameCustomer ? ['reserved', 'reserved'] : ['reserved', 'unavailable'], $results);
        $this->assertSame(1, MealBooking::query()->where('meal_slot_id', $slot->id)->count());
        $this->assertSame(1, $slot->fresh()->reserved);

        $booking = MealBooking::query()->where('meal_slot_id', $slot->id)->firstOrFail();
        $cancelStart = (string) (microtime(true) + 1);
        $cancelCommand = [PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $booking->id, $cancelStart, 'meal-cancel', (string) $booking->user_id];
        $cancelFirst = new Process($cancelCommand, base_path());
        $cancelSecond = new Process($cancelCommand, base_path());
        $cancelFirst->start();
        $cancelSecond->start();
        $cancelFirst->wait();
        $cancelSecond->wait();

        $this->assertSame(0, $cancelFirst->getExitCode(), $cancelFirst->getErrorOutput());
        $this->assertSame(0, $cancelSecond->getExitCode(), $cancelSecond->getErrorOutput());
        $this->assertSame(0, $slot->fresh()->reserved);
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public static function couponRaces(): array
    {
        return ['global last coupon' => [false], 'duplicate retry' => [true]];
    }

    #[DataProvider('couponRaces')]
    public function test_coupon_races_redeem_exactly_once(bool $sameCustomer): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
            $this->markTestSkipped('Requires isolated MySQL database wisata_concurrency_test.');
        }
        $product = Product::factory()->create(['base_price' => 10000, 'status' => 'published', 'currency' => 'IDR']);
        $bucket = InventoryBucket::factory()->for($product)->create(['service_date' => '2026-10-10', 'capacity' => 10]);
        $coupon = Coupon::factory()->create(['global_quota' => 1]);
        $firstUser = User::factory()->create();
        $secondUser = $sameCustomer ? $firstUser : User::factory()->create();
        $key = Str::uuid()->toString();
        $start = (string) (microtime(true) + 1);
        $first = new Process([PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $product->id, $start, 'coupon', (string) $firstUser->id, $key, $coupon->code], base_path());
        $second = new Process([PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $product->id, $start, 'coupon', (string) $secondUser->id, $sameCustomer ? $key : (string) Str::uuid(), $coupon->code], base_path());

        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $this->assertSame(0, $first->getExitCode(), $first->getErrorOutput());
        $this->assertSame(0, $second->getExitCode(), $second->getErrorOutput());
        $results = [$first->getOutput(), $second->getOutput()];
        sort($results);
        $this->assertSame($sameCustomer ? ['redeemed', 'redeemed'] : ['redeemed', 'unavailable'], $results);
        $this->assertSame(1, $coupon->fresh()->used_quota);
        $this->assertSame(1, $coupon->redemptions()->count());
        $this->assertSame(1, Order::query()->where('partner_id', $product->partner_id)->count());
        $this->assertSame(1, $bucket->fresh()->held);
    }

    public function test_cross_village_snapshot_race_creates_one_immutable_allocation(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
            $this->markTestSkipped('Requires isolated MySQL database wisata_concurrency_test.');
        }
        $partners = collect([1, 2])->map(fn () => Partner::factory()->create(['region_id' => Region::factory()->create(['type' => 'village'])->id]));
        $product = Product::factory()->create(['partner_id' => $partners[0]->id, 'type' => 'package']);
        $package = TourPackage::factory()->create(['product_id' => $product->id, 'duration_days' => 1, 'meeting_point' => 'Demo',
            'pricing_mode' => 'per_person', 'minimum_participants' => 1, 'maximum_participants' => 20]);
        foreach ($partners as $index => $partner) {
            $package->crossVillagePackages()->create(['partner_id' => $partner->id, 'revenue_share_percentage' => '50.00', 'is_primary_partner' => $index === 0]);
        }
        $order = Order::factory()->create(['partner_id' => $partners[0]->id, 'total' => 10001, 'status' => 'paid']);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'name' => 'Snapshot race',
            'quantity' => 1, 'unit_price' => 10001, 'total' => 10001, 'commission_amount' => 1000, 'snapshot' => []]);
        PaymentAttempt::factory()->create(['order_id' => $order->id, 'status' => 'succeeded', 'amount' => 10001]);
        $user = User::factory()->create(['platform_role' => 'super_admin', 'email_verified_at' => now()]);
        $version = app(CrossVillageAllocationService::class)->configuration($package)['version'];
        $command = [PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $order->id,
            (string) (microtime(true) + 1), 'cross-village-snapshot', (string) $user->id, $version];
        $first = new Process($command, base_path());
        $second = new Process($command, base_path());
        $first->start();
        $second->start();
        $first->wait();
        $second->wait();
        $this->assertSame(0, $first->getExitCode(), $first->getErrorOutput());
        $this->assertSame(0, $second->getExitCode(), $second->getErrorOutput());
        $this->assertSame($first->getOutput(), $second->getOutput());
        $this->assertSame(2, $order->subOrders()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'cross_village.order_snapshot_created')->where('auditable_id', $order->id)->count());
        $this->assertSame(9001, $order->fresh()->cross_village_snapshot['partner_revenue']);
    }

    public function test_cross_village_checkout_race_creates_one_snapshot_and_hold(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || config('database.connections.mysql.database') !== 'wisata_concurrency_test') {
            $this->markTestSkipped('Requires isolated MySQL database wisata_concurrency_test.');
        }
        $partners = collect([1, 2])->map(fn () => Partner::factory()->create(['region_id' => Region::factory()->create(['type' => 'village'])->id]));
        $product = Product::factory()->create(['partner_id' => $partners[0]->id, 'type' => 'package', 'base_price' => 10001]);
        $package = TourPackage::factory()->create(['product_id' => $product->id, 'duration_days' => 1, 'meeting_point' => 'Demo',
            'pricing_mode' => 'per_person', 'minimum_participants' => 1, 'maximum_participants' => 10, 'status' => 'published']);
        foreach ($partners as $index => $partner) {
            $package->crossVillagePackages()->create(['partner_id' => $partner->id, 'revenue_share_percentage' => '50.00', 'is_primary_partner' => $index === 0]);
        }
        $version = app(CrossVillageAllocationService::class)->configuration($package)['version'];
        foreach ($partners as $partner) {
            CrossVillageAgreement::create(['tour_package_id' => $package->id, 'partner_id' => $partner->id, 'revision' => 0,
                'configuration_version' => $version, 'decision' => 'accepted', 'reason' => 'Sandbox race fixture', 'decided_at' => now()]);
        }
        $date = now()->addDay()->toDateString();
        $bucket = InventoryBucket::factory()->create(['product_id' => $product->id, 'service_date' => $date, 'session_key' => 'default', 'capacity' => 10]);
        $key = (string) Str::uuid();
        $command = [PHP_BINARY, base_path('tests/Fixtures/reserve_inventory.php'), (string) $product->id,
            (string) (microtime(true) + 1), 'cross-village-checkout', $key, $version, $date];
        $first = new Process($command, base_path());
        $second = new Process($command, base_path());
        $first->start();
        $second->start();
        $first->wait();
        $second->wait();
        $this->assertSame(0, $first->getExitCode(), $first->getErrorOutput());
        $this->assertSame(0, $second->getExitCode(), $second->getErrorOutput());
        $this->assertSame($first->getOutput(), $second->getOutput());
        $this->assertSame(1, Order::where('idempotency_key', $key)->count());
        $order = Order::where('idempotency_key', $key)->firstOrFail();
        $this->assertSame(2, $order->subOrders()->count());
        $this->assertSame(1, $bucket->fresh()->held);
        $this->assertSame(1, AuditLog::where('action', 'cross_village.checkout_snapshot_created')->where('auditable_id', $order->id)->count());
    }
}
