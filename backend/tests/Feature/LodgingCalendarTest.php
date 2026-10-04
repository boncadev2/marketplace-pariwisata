<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LodgingBooking;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LodgingCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function range(): array
    {
        return ['start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(3)->toDateString()];
    }

    private function url(RoomType $room): string
    {
        return '/api/v1/dashboard/lodging-rooms/'.$room->id.'/calendar';
    }

    private function preview(RoomType $room): array
    {
        return $this->getJson($this->url($room).'?'.http_build_query($this->range()))->assertOk()->json('data');
    }

    public function test_range_includes_both_endpoints_and_initializes_only_missing_stock(): void
    {
        $this->freezeTime();
        $room = RoomType::factory()->create();
        $room->inventories()->createMany([['date' => now()->addDay()->toDateString(), 'stock' => 2], ['date' => now()->addDays(2)->toDateString(), 'stock' => 0]]);
        $room->rates()->create(['date' => now()->addDay()->toDateString(), 'price' => 100000]);
        $room->rates()->create(['date' => now()->addDays(4)->toDateString(), 'price' => 90000]);
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $preview = $this->preview($room);
        $this->assertCount(3, $preview['days']);
        $this->assertNull($preview['days'][2]['stock']);
        $response = $this->patchJson($this->url($room), [...$this->range(), 'revision' => $preview['revision'], 'price' => 175000, 'initial_stock' => 5])->assertOk()->assertJsonPath('data.initialized_dates', 1)->assertJsonPath('data.days.0.stock', 2)->assertJsonPath('data.days.1.stock', 0)->assertJsonPath('data.days.2.stock', 5)->assertJsonPath('data.days.2.price', '175000.00');
        $this->assertDatabaseCount('room_rates', 4);
        $this->assertDatabaseHas('room_rates', ['room_type_id' => $room->id, 'date' => now()->addDays(4)->toDateString(), 'price' => 90000]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lodging.rates_updated', 'auditable_id' => $room->id]);
        $this->patchJson($this->url($room), [...$this->range(), 'revision' => $response->json('data.revision'), 'price' => 175000, 'initial_stock' => 999])->assertOk()->assertJsonPath('data.initialized_dates', 0)->assertJsonPath('data.days.2.stock', 5);
    }

    public function test_rate_only_update_leaves_unconfigured_stock_missing(): void
    {
        $this->freezeTime();
        $room = RoomType::factory()->create();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $preview = $this->preview($room);
        $this->patchJson($this->url($room), [...$this->range(), 'revision' => $preview['revision'], 'price' => 150000, 'initial_stock' => null])->assertOk()->assertJsonPath('data.days.0.stock', null)->assertJsonPath('data.initialized_dates', 0);
        $this->assertDatabaseCount('room_inventories', 0);
        $this->assertDatabaseCount('room_rates', 3);
    }

    public function test_reservation_invalidates_calendar_and_new_rate_keeps_booking_snapshot(): void
    {
        $this->freezeTime();
        $room = RoomType::factory()->create();
        $date = now()->addDay()->toDateString();
        $room->inventories()->create(['date' => $date, 'stock' => 3]);
        $room->rates()->create(['date' => $date, 'price' => 100000]);
        $admin = User::factory()->create(['platform_role' => 'super_admin']);
        $this->actingAs($admin);
        $preview = $this->preview($room);
        $this->actingAs(User::factory()->create());
        $booking = $this->postJson('/api/v1/account/lodging-bookings', ['room_type_id' => $room->id, 'check_in' => $date, 'check_out' => now()->addDays(2)->toDateString(), 'quantity' => 1, 'guests' => 1, 'expected_total_price' => '100000.00'], ['Idempotency-Key' => 'calendar-booking-key-0001'])->assertCreated();
        $this->actingAs($admin);
        $this->patchJson($this->url($room), [...$this->range(), 'revision' => $preview['revision'], 'price' => 200000, 'initial_stock' => 10])->assertConflict();
        $this->assertDatabaseHas('room_rates', ['room_type_id' => $room->id, 'date' => $date, 'price' => 100000]);
        $preview = $this->preview($room);
        $this->patchJson($this->url($room), [...$this->range(), 'revision' => $preview['revision'], 'price' => 200000, 'initial_stock' => 10])->assertOk()->assertJsonPath('data.days.0.stock', 2);
        $this->assertDatabaseHas('lodging_bookings', ['id' => $booking->json('data.id'), 'total_price' => 100000]);
        $this->assertSame('100000.00', LodgingBooking::findOrFail($booking->json('data.id'))->nightly_prices[0]['price_per_room']);
        $this->getJson('/api/v1/lodging/quote?'.http_build_query(['room_type_id' => $room->id, 'check_in' => $date, 'check_out' => now()->addDays(2)->toDateString(), 'quantity' => 1, 'guests' => 1]))->assertOk()->assertJsonPath('data.total_price', '200000.00');
    }

    public function test_changed_rate_or_missing_row_created_after_preview_rejects_entire_range(): void
    {
        $this->freezeTime();
        $room = RoomType::factory()->create();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $preview = $this->preview($room);
        $room->rates()->create(['date' => now()->addDays(2)->toDateString(), 'price' => 123456]);
        $this->patchJson($this->url($room), [...$this->range(), 'revision' => $preview['revision'], 'price' => 150000, 'initial_stock' => 5])->assertConflict();
        $this->assertDatabaseCount('room_rates', 1);
        $this->assertDatabaseCount('room_inventories', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_range_limits_and_invalid_inputs_are_rejected_without_writes(): void
    {
        $this->freezeTime();
        $room = RoomType::factory()->create();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->getJson($this->url($room).'?'.http_build_query(['start_date' => now()->toDateString(), 'end_date' => now()->addDays(89)->toDateString()]))->assertOk()->assertJsonCount(90, 'data.days');
        $this->getJson($this->url($room).'?'.http_build_query(['start_date' => now()->toDateString(), 'end_date' => now()->addDays(90)->toDateString()]))->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->getJson($this->url($room).'?'.http_build_query(['start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addYear()->addDay()->toDateString()]))->assertUnprocessable()->assertJsonValidationErrors(['start_date', 'end_date']);
        $this->getJson($this->url($room).'?'.http_build_query(['start_date' => now()->addDays(2)->toDateString(), 'end_date' => now()->addDay()->toDateString()]))->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $preview = $this->preview($room);
        $this->patchJson($this->url($room), [...$this->range(), 'revision' => $preview['revision'], 'price' => 0, 'initial_stock' => -1])->assertUnprocessable()->assertJsonValidationErrors(['price', 'initial_stock']);
        $this->assertDatabaseCount('room_rates', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_calendar_requires_verified_admin_and_mutations_are_local_only(): void
    {
        $this->freezeTime();
        $room = RoomType::factory()->create();
        $this->getJson($this->url($room).'?'.http_build_query($this->range()))->assertUnauthorized();
        $this->patchJson($this->url($room), [])->assertUnauthorized();
        foreach ([['platform_role' => 'customer'], ['platform_role' => 'super_admin', 'email_verified_at' => null]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            $this->getJson($this->url($room).'?'.http_build_query($this->range()))->assertForbidden();
            $this->patchJson($this->url($room), [])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->app->instance('env', 'staging');
        $this->getJson($this->url($room).'?'.http_build_query($this->range()))->assertOk()->assertJsonPath('meta.editing_available', false);
        $this->patchJson($this->url($room), [])->assertStatus(503);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_transaction_failure_rolls_back_all_dates(): void
    {
        $this->freezeTime();
        $room = RoomType::factory()->create();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $preview = $this->preview($room);
        AuditLog::creating(function (): void {
            throw new RuntimeException('Simulated audit storage failure');
        });
        $this->patchJson($this->url($room), [...$this->range(), 'revision' => $preview['revision'], 'price' => 150000, 'initial_stock' => 5])->assertStatus(500);
        $this->assertDatabaseCount('room_rates', 0);
        $this->assertDatabaseCount('room_inventories', 0);
    }
}
