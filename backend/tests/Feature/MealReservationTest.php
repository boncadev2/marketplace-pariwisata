<?php

namespace Tests\Feature;

use App\Models\MealBooking;
use App\Models\MealSlot;
use App\Models\User;
use Database\Seeders\CulinaryDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MealReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    }

    private function payload(MealSlot $slot): array
    {
        return ['meal_slot_id' => $slot->id, 'quantity' => 2, 'expected_total_price' => '150000.50'];
    }

    public function test_quote_uses_exact_server_price_without_consuming_quota(): void
    {
        $slot = MealSlot::factory()->create();

        $this->getJson('/api/v1/culinary/quote?'.http_build_query($this->payload($slot)))
            ->assertOk()->assertJsonPath('data.total_price', '150000.50')->assertJsonPath('data.quantity', 2);

        $this->assertDatabaseCount('meal_bookings', 0);
        $this->assertSame(0, $slot->fresh()->reserved);
    }

    public function test_reservation_and_retry_consume_quota_once_with_price_snapshot(): void
    {
        $slot = MealSlot::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);
        $headers = ['Idempotency-Key' => 'culinary-retry-key-001'];
        $created = $this->postJson('/api/v1/account/meal-bookings', [...$this->payload($slot), 'user_id' => 999, 'status' => 'paid', 'total_price' => '1'], $headers)
            ->assertCreated()->assertJsonPath('data.status', 'reserved_sandbox')->assertJsonPath('data.total_price', '150000.50');
        $slot->update(['price' => '99000.00', 'package_name' => 'Changed package']);

        $this->postJson('/api/v1/account/meal-bookings', $this->payload($slot), $headers)->assertOk()->assertJsonPath('data.id', $created->json('data.id'))->assertJsonPath('data.package_name', 'Paket Makan Demo');

        $this->assertDatabaseCount('meal_bookings', 1);
        $this->assertDatabaseHas('meal_bookings', ['id' => $created->json('data.id'), 'user_id' => $user->id, 'unit_price' => '75000.25']);
        $this->assertSame(2, $slot->fresh()->reserved);
    }

    public function test_insufficient_capacity_rejects_without_writes(): void
    {
        $slot = MealSlot::factory()->create(['capacity' => 2, 'reserved' => 1]);
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/v1/account/meal-bookings', $this->payload($slot), ['Idempotency-Key' => 'culinary-capacity-001'])->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertDatabaseCount('meal_bookings', 0);
        $this->assertSame(1, $slot->fresh()->reserved);
    }

    public function test_price_change_requires_confirmation_and_preserves_quota(): void
    {
        $slot = MealSlot::factory()->create(['price' => '80000.00']);
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/v1/account/meal-bookings', $this->payload($slot), ['Idempotency-Key' => 'culinary-price-key-001'])->assertConflict();

        $this->assertDatabaseCount('meal_bookings', 0);
        $this->assertSame(0, $slot->fresh()->reserved);
    }

    public function test_conflicting_key_does_not_create_second_booking(): void
    {
        $slot = MealSlot::factory()->create();
        $this->actingAs(User::factory()->create());
        $headers = ['Idempotency-Key' => 'culinary-conflict-001'];
        $this->postJson('/api/v1/account/meal-bookings', $this->payload($slot), $headers)->assertCreated();

        $this->postJson('/api/v1/account/meal-bookings', [...$this->payload($slot), 'quantity' => 1], $headers)->assertConflict();

        $this->assertDatabaseCount('meal_bookings', 1);
        $this->assertSame(2, $slot->fresh()->reserved);
    }

    public function test_cancel_restores_quota_once_even_when_slot_is_inactive(): void
    {
        $slot = MealSlot::factory()->create();
        $this->actingAs(User::factory()->create());
        $headers = ['Idempotency-Key' => 'culinary-cancel-key-001'];
        $id = $this->postJson('/api/v1/account/meal-bookings', $this->payload($slot), $headers)->assertCreated()->json('data.id');
        $slot->update(['is_active' => false]);

        $this->postJson("/api/v1/account/meal-bookings/$id/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/account/meal-bookings/$id/cancel")->assertOk();
        $this->postJson('/api/v1/account/meal-bookings', $this->payload($slot), $headers)->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(0, $slot->fresh()->reserved);
    }

    public function test_customer_cannot_see_or_cancel_other_customers_booking(): void
    {
        $slot = MealSlot::factory()->create();
        $this->actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/account/meal-bookings', $this->payload($slot), ['Idempotency-Key' => 'culinary-private-key-001'])->assertCreated()->json('data.id');
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/account/meal-bookings')->assertOk()->assertJsonCount(0, 'data.data');
        $this->postJson("/api/v1/account/meal-bookings/$id/cancel")->assertNotFound();
        $this->assertSame(2, $slot->fresh()->reserved);
    }

    public static function invalidSlots(): array
    {
        return ['inactive slot' => ['is_active', false, 404], 'past slot' => ['time_slot', '2026-09-30 12:00:00', 422], 'zero price' => ['price', '0.00', 422], 'negative price' => ['price', '-1.00', 422]];
    }

    #[DataProvider('invalidSlots')]
    public function test_invalid_slot_rejected_without_changes(string $field, mixed $value, int $status): void
    {
        $slot = MealSlot::factory()->create([$field => $value]);
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/v1/account/meal-bookings', $this->payload($slot), ['Idempotency-Key' => 'culinary-invalid-key-001'])->assertStatus($status);
        $this->assertDatabaseCount('meal_bookings', 0);
        $this->assertSame(0, $slot->fresh()->reserved);
    }

    public function test_inactive_place_is_hidden_from_catalog_and_reservation(): void
    {
        $slot = MealSlot::factory()->create();
        $slot->culinaryPlace->update(['is_active' => false]);
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/culinary/slots')->assertOk()->assertJsonCount(0, 'data.data');
        $this->postJson('/api/v1/account/meal-bookings', $this->payload($slot), ['Idempotency-Key' => 'culinary-place-key-001'])->assertNotFound();
        $this->assertDatabaseCount('meal_bookings', 0);
    }

    public function test_guest_cannot_reserve_and_missing_key_rejected(): void
    {
        $slot = MealSlot::factory()->create();
        $this->postJson('/api/v1/account/meal-bookings', $this->payload($slot))->assertUnauthorized();
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/v1/account/meal-bookings', $this->payload($slot))->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->assertDatabaseCount('meal_bookings', 0);
    }

    public static function invalidQuantities(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'fractional' => [1.5], 'too many' => [101]];
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantity_rejected_without_consuming_quota(mixed $quantity): void
    {
        $slot = MealSlot::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/v1/account/meal-bookings', [...$this->payload($slot), 'quantity' => $quantity], ['Idempotency-Key' => 'culinary-quantity-key-001'])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertDatabaseCount('meal_bookings', 0);
        $this->assertSame(0, $slot->fresh()->reserved);
    }

    public function test_production_rejects_simulation_writes(): void
    {
        $this->actingAs(User::factory()->create());
        $this->app->instance('env', 'production');

        $this->postJson('/api/v1/account/meal-bookings', [])->assertStatus(503);
        $this->assertDatabaseCount('meal_bookings', 0);
    }

    public function test_non_sandbox_booking_cannot_release_quota(): void
    {
        $user = User::factory()->create();
        $slot = MealSlot::factory()->create(['reserved' => 1]);
        $booking = MealBooking::factory()->for($user)->for($slot)->create();
        $this->actingAs($user);

        $this->postJson("/api/v1/account/meal-bookings/$booking->id/cancel")->assertConflict();
        $this->assertSame(1, $slot->fresh()->reserved);
        $this->assertSame('pending_payment', $booking->fresh()->status);
    }

    public function test_demo_reseed_does_not_reset_reserved_quota(): void
    {
        $this->seed(CulinaryDemoSeeder::class);
        $slot = MealSlot::query()->firstOrFail();
        $slot->update(['reserved' => 3]);

        $this->seed(CulinaryDemoSeeder::class);

        $this->assertDatabaseCount('culinary_places', 1);
        $this->assertDatabaseCount('meal_slots', 14);
        $this->assertSame(3, $slot->fresh()->reserved);
    }
}
