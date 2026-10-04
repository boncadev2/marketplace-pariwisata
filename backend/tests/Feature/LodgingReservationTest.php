<?php

namespace Tests\Feature;

use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\LodgingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LodgingReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    }

    private function room(int $secondNightStock = 3): RoomType
    {
        $room = RoomType::factory()->create();
        $room->inventories()->createMany([
            ['date' => '2026-10-10', 'stock' => 3],
            ['date' => '2026-10-11', 'stock' => $secondNightStock],
        ]);
        $room->rates()->createMany([
            ['date' => '2026-10-10', 'price' => '125000.25'],
            ['date' => '2026-10-11', 'price' => '150000.50'],
        ]);

        return $room;
    }

    private function stay(RoomType $room): array
    {
        return ['room_type_id' => $room->id, 'check_in' => '2026-10-10', 'check_out' => '2026-10-12', 'quantity' => 2, 'guests' => 3, 'expected_total_price' => '550001.50'];
    }

    public function test_quote_excludes_checkout_night_and_does_not_reserve_inventory(): void
    {
        $room = $this->room();

        $this->getJson('/api/v1/lodging/quote?'.http_build_query($this->stay($room)))
            ->assertOk()->assertJsonPath('data.nights', 2)->assertJsonPath('data.total_price', '550001.50')
            ->assertJsonCount(2, 'data.nightly_prices')->assertHeader('Cache-Control', 'no-store, private');

        $this->assertDatabaseCount('lodging_bookings', 0);
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'date' => '2026-10-10', 'stock' => 3]);
    }

    public function test_reservation_uses_server_prices_and_retry_never_decrements_twice(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create());
        $payload = [...$this->stay($room), 'total_price' => '1', 'status' => 'paid'];
        $headers = ['Idempotency-Key' => 'lodging-test-key-001'];

        $created = $this->postJson('/api/v1/account/lodging-bookings', $payload, $headers)
            ->assertCreated()->assertJsonPath('data.total_price', '550001.50')->assertJsonPath('data.status', 'reserved_sandbox');
        $this->postJson('/api/v1/account/lodging-bookings', $payload, $headers)
            ->assertOk()->assertJsonPath('data.id', $created->json('data.id'));

        $this->assertDatabaseCount('lodging_bookings', 1);
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'date' => '2026-10-10', 'stock' => 1]);
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'date' => '2026-10-11', 'stock' => 1]);
    }

    public function test_unavailable_later_night_leaves_all_stock_unchanged(): void
    {
        $room = $this->room(1);
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/v1/account/lodging-bookings', $this->stay($room), ['Idempotency-Key' => 'lodging-test-key-002'])
            ->assertUnprocessable()->assertJsonValidationErrors('check_in');

        $this->assertDatabaseCount('lodging_bookings', 0);
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'date' => '2026-10-10', 'stock' => 3]);
    }

    public function test_missing_rate_rejects_entire_stay(): void
    {
        $room = $this->room();
        $room->rates()->where('date', '2026-10-11')->delete();
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/v1/account/lodging-bookings', $this->stay($room), ['Idempotency-Key' => 'lodging-test-key-003'])
            ->assertUnprocessable()->assertJsonValidationErrors('check_in');

        $this->assertDatabaseCount('lodging_bookings', 0);
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'date' => '2026-10-10', 'stock' => 3]);
    }

    public function test_conflicting_retry_returns_409_without_creating_booking(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create());
        $headers = ['Idempotency-Key' => 'lodging-test-key-004'];
        $this->postJson('/api/v1/account/lodging-bookings', $this->stay($room), $headers)->assertCreated();

        $this->postJson('/api/v1/account/lodging-bookings', [...$this->stay($room), 'quantity' => 1], $headers)->assertConflict();

        $this->assertDatabaseCount('lodging_bookings', 1);
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'date' => '2026-10-10', 'stock' => 1]);
    }

    public function test_cancellation_restores_exact_stock_once_and_retry_stays_cancelled(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create());
        $headers = ['Idempotency-Key' => 'lodging-test-key-005'];
        $id = $this->postJson('/api/v1/account/lodging-bookings', $this->stay($room), $headers)->assertCreated()->json('data.id');
        $room->update(['is_active' => false]);

        $this->postJson("/api/v1/account/lodging-bookings/$id/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/account/lodging-bookings/$id/cancel")->assertOk();
        $this->postJson('/api/v1/account/lodging-bookings', $this->stay($room), $headers)->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'date' => '2026-10-10', 'stock' => 3]);
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'date' => '2026-10-11', 'stock' => 3]);
    }

    public function test_other_customer_cannot_see_or_cancel_booking(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/account/lodging-bookings', $this->stay($room), ['Idempotency-Key' => 'lodging-test-key-006'])->assertCreated()->json('data.id');
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/account/lodging-bookings')->assertOk()->assertJsonCount(0, 'data.data');
        $this->postJson("/api/v1/account/lodging-bookings/$id/cancel")->assertNotFound();

        $this->assertDatabaseHas('lodging_bookings', ['id' => $id, 'status' => 'reserved_sandbox']);
    }

    public function test_changed_price_requires_new_confirmation_without_stock_change(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create());
        $room->rates()->where('date', '2026-10-11')->update(['price' => '180000.50']);

        $this->postJson('/api/v1/account/lodging-bookings', $this->stay($room), ['Idempotency-Key' => 'lodging-price-change-01'])->assertConflict();

        $this->assertDatabaseCount('lodging_bookings', 0);
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'date' => '2026-10-10', 'stock' => 3]);
    }

    public function test_demo_seeding_preserves_consumed_inventory(): void
    {
        $this->seed(LodgingDemoSeeder::class);
        $room = RoomType::query()->firstOrFail();
        $room->inventories()->where('date', '2026-10-10')->update(['stock' => 1]);

        $this->seed(LodgingDemoSeeder::class);

        $this->assertDatabaseCount('room_types', 1);
        $this->assertDatabaseCount('room_inventories', 60);
        $this->assertDatabaseCount('room_rates', 60);
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'date' => '2026-10-10', 'stock' => 1]);
    }

    public function test_guest_cannot_reserve(): void
    {
        $this->postJson('/api/v1/account/lodging-bookings', [])->assertUnauthorized();
        $this->assertDatabaseCount('lodging_bookings', 0);
    }

    public function test_inactive_room_is_hidden_and_cannot_be_quoted(): void
    {
        $room = $this->room();
        $room->update(['is_active' => false]);

        $this->getJson('/api/v1/lodging/rooms')->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson('/api/v1/lodging/quote?'.http_build_query($this->stay($room)))->assertNotFound();
    }

    public static function invalidStays(): array
    {
        return [
            'past checkin' => ['check_in', '2026-09-30', 'check_in'],
            'same day checkout' => ['check_out', '2026-10-10', 'check_out'],
            'too many nights' => ['check_out', '2026-11-11', 'check_out'],
            'zero rooms' => ['quantity', 0, 'quantity'],
            'too many rooms' => ['quantity', 11, 'quantity'],
            'zero guests' => ['guests', 0, 'guests'],
            'over capacity' => ['guests', 5, 'guests'],
        ];
    }

    #[DataProvider('invalidStays')]
    public function test_invalid_stay_rejected_without_writes(string $field, mixed $value, string $errorField): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/v1/account/lodging-bookings', [...$this->stay($room), $field => $value], ['Idempotency-Key' => 'lodging-invalid-key-001'])
            ->assertUnprocessable()->assertJsonValidationErrors($errorField);

        $this->assertDatabaseCount('lodging_bookings', 0);
    }

    public function test_missing_idempotency_key_rejected_without_reserving(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/v1/account/lodging-bookings', $this->stay($room))->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->assertDatabaseCount('lodging_bookings', 0);
    }

    public function test_production_rejects_simulation_writes(): void
    {
        $this->actingAs(User::factory()->create());
        $this->app->instance('env', 'production');

        $this->postJson('/api/v1/account/lodging-bookings', [])->assertStatus(503);
        $this->assertDatabaseCount('lodging_bookings', 0);
    }

    public function test_missing_inventory_on_cancel_rolls_back_all_restoration(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/account/lodging-bookings', $this->stay($room), ['Idempotency-Key' => 'lodging-test-key-007'])->assertCreated()->json('data.id');
        RoomInventory::query()->where('room_type_id', $room->id)->where('date', '2026-10-11')->delete();

        $this->postJson("/api/v1/account/lodging-bookings/$id/cancel")->assertConflict();

        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $room->id, 'date' => '2026-10-10', 'stock' => 1]);
        $this->assertDatabaseHas('lodging_bookings', ['id' => $id, 'status' => 'reserved_sandbox']);
    }
}
