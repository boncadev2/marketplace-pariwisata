<?php

namespace Tests\Feature;

use App\Models\InventoryBucket;
use App\Models\Order;
use App\Models\Product;
use App\Services\InventoryReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpireOrderPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithHold(string $status = 'pending_payment'): array
    {
        $order = Order::factory()->create(['status' => $status]);
        $product = Product::factory()->create(['partner_id' => $order->partner_id, 'name' => 'Tiket test', 'slug' => fake()->uuid(), 'type' => 'ticket', 'status' => 'published']);
        $bucket = InventoryBucket::factory()->create(['product_id' => $product->id, 'capacity' => 1]);
        $hold = app(InventoryReservationService::class)->reserve($bucket, 1, CarbonImmutable::now()->addMinutes(15));
        $order->items()->create(['product_id' => $product->id, 'name' => 'Tiket test', 'quantity' => 1, 'unit_price' => 100, 'total' => 100, 'snapshot' => ['inventory_hold_id' => $hold->id]]);

        return [$order, $hold];
    }

    public function test_scheduler_expires_order_once_and_records_notice_without_sending(): void
    {
        $this->freezeTime();
        [$order, $hold] = $this->orderWithHold();
        $hold->update(['expires_at' => now()->subMinute()]);
        Mail::fake();

        $this->artisan('inventory:release-expired-holds')->assertSuccessful();
        $this->artisan('inventory:release-expired-holds')->assertSuccessful();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'expired']);
        $this->assertDatabaseHas('inventory_holds', ['id' => $hold->id, 'state' => 'expired']);
        $this->assertSame(0, $hold->bucket->fresh()->held);
        $this->assertDatabaseCount('notification_deliveries', 1);
        $this->assertDatabaseHas('notification_deliveries', ['order_id' => $order->id, 'type' => 'expired', 'status' => 'pending']);
        Mail::assertNothingSent();
    }

    public function test_active_payment_window_is_not_expired(): void
    {
        $this->freezeTime();
        [$order] = $this->orderWithHold();

        $this->artisan('inventory:release-expired-holds')->assertSuccessful();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending_payment']);
        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    public static function terminalStatuses(): array
    {
        return [['paid'], ['cancelled'], ['refunded'], ['payment_exception']];
    }

    #[DataProvider('terminalStatuses')]
    public function test_other_order_states_are_not_overwritten(string $status): void
    {
        $this->freezeTime();
        [$order, $hold] = $this->orderWithHold($status);
        $hold->update(['expires_at' => now()->subMinute()]);

        $this->artisan('inventory:release-expired-holds')->assertSuccessful();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => $status]);
        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    public function test_order_without_inventory_reference_is_not_expired(): void
    {
        $order = Order::factory()->create(['status' => 'pending_payment']);

        $this->artisan('inventory:release-expired-holds')->assertSuccessful();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending_payment']);
        $this->assertDatabaseCount('notification_deliveries', 0);
    }
}
