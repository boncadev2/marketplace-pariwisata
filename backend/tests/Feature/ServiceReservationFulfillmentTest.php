<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ServiceReservationConfirmationController;
use App\Models\LodgingBooking;
use App\Models\MealBooking;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ServiceReservationFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function lodging(): LodgingBooking
    {
        $room = RoomType::factory()->create();
        RoomInventory::create(['room_type_id' => $room->id, 'date' => '2026-10-03', 'stock' => 3]);

        return LodgingBooking::create(['user_id' => User::factory()->create()->id, 'room_type_id' => $room->id, 'check_in' => '2026-10-03', 'check_out' => '2026-10-04', 'quantity' => 2, 'guests' => 4, 'total_price' => '300000.00', 'status' => 'reserved_sandbox', 'nightly_prices' => [['date' => '2026-10-03', 'price' => '150000.00']], 'idempotency_key' => 'fulfillment-test']);
    }

    private function action(LodgingBooking|MealBooking $booking, string $action, ?string $revision = null, array $extra = []): TestResponse
    {
        $type = $booking instanceof LodgingBooking ? 'lodging' : 'culinary';

        return $this->postJson('/api/v1/dashboard/service-reservations/'.$type.'/'.$booking->id.'/'.$action, ['revision' => $revision ?? ServiceReservationConfirmationController::revision($booking->fresh()), ...$extra]);
    }

    public function test_lodging_requires_confirmation_and_wib_arrival_date_before_check_in_and_completion(): void
    {
        $this->travelTo(Carbon::parse('2026-10-02 16:59:00', 'UTC'));
        $booking = $this->lodging();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->action($booking, 'check-in')->assertConflict();
        $this->action($booking, 'complete')->assertConflict();
        $this->action($booking, 'confirm')->assertOk();
        $this->action($booking, 'check-in')->assertConflict();
        $this->travelTo(Carbon::parse('2026-10-02 17:00:00', 'UTC'));
        $revision = ServiceReservationConfirmationController::revision($booking->fresh());
        $this->action($booking, 'check-in', str_repeat('a', 64))->assertConflict();
        $this->action($booking, 'check-in', $revision)->assertOk();
        $this->action($booking, 'check-in', $revision)->assertOk();
        $this->action($booking, 'cancel', extra: ['reason' => 'Pengujian pembatalan setelah check-in'])->assertConflict();
        $this->actingAs($booking->user)->postJson('/api/v1/account/lodging-bookings/'.$booking->id.'/cancel')->assertConflict();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $revision = ServiceReservationConfirmationController::revision($booking->fresh());
        $this->action($booking, 'complete', $revision)->assertOk();
        $this->action($booking, 'complete', $revision)->assertOk();
        $this->assertDatabaseCount('audit_logs', 3);
        $this->assertNotNull($booking->fresh()->checked_in_at);
        $this->assertNotNull($booking->fresh()->completed_at);
        $this->assertSame('300000.00', $booking->fresh()->total_price);
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $booking->room_type_id, 'stock' => 3]);
        $this->getJson('/api/v1/dashboard/service-reservations?type=lodging&operation=completed')->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.available_actions', []);
        $this->getJson('/api/v1/dashboard/service-reservations?type=lodging&operation=checked_in')->assertJsonCount(0, 'data.data');
    }

    public function test_culinary_cannot_complete_before_visit_or_check_in_and_customer_cannot_cancel_completed_service(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 05:00:00', 'UTC'));
        $booking = MealBooking::factory()->create(['status' => 'reserved_sandbox', 'time_slot' => now()->addHour()]);
        $booking->mealSlot->update(['reserved' => $booking->quantity]);
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->action($booking, 'complete')->assertConflict();
        $this->action($booking, 'confirm')->assertOk();
        $this->action($booking, 'check-in')->assertConflict();
        $this->action($booking, 'complete')->assertConflict();
        $this->travelTo(Carbon::parse('2026-10-03 06:00:00', 'UTC'));
        $this->action($booking, 'complete')->assertOk();
        $this->assertSame($booking->quantity, $booking->mealSlot->fresh()->reserved);
        $this->actingAs($booking->user)->postJson('/api/v1/account/meal-bookings/'.$booking->id.'/cancel')->assertConflict();
        $this->getJson('/api/v1/account/meal-bookings')->assertJsonPath('data.data.0.completed_at', $booking->fresh()->completed_at->toJSON());
    }

    public function test_manager_cancellation_requires_reason_and_returns_inventory_once_with_audit(): void
    {
        $booking = $this->lodging();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->action($booking, 'cancel')->assertUnprocessable();
        $revision = ServiceReservationConfirmationController::revision($booking);
        $this->action($booking, 'cancel', $revision, ['reason' => 'Tempat ditutup untuk perbaikan'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->action($booking, 'cancel', $revision, ['reason' => 'Tempat ditutup untuk perbaikan'])->assertOk();
        $this->assertDatabaseHas('room_inventories', ['room_type_id' => $booking->room_type_id, 'stock' => 5]);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->action($booking, 'confirm')->assertConflict();
        $this->action($booking, 'check-in')->assertConflict();
    }

    public function test_unauthorized_partner_cannot_apply_any_operational_action(): void
    {
        $owner = User::factory()->create();
        $partner = Partner::factory()->create();
        PartnerMember::create(['user_id' => $owner->id, 'partner_id' => $partner->id, 'role' => 'owner', 'is_active' => true]);
        $booking = MealBooking::factory()->create(['status' => 'reserved_sandbox']);
        $this->actingAs($owner);
        foreach (['check-in', 'complete', 'cancel'] as $action) {
            $this->action($booking, $action, extra: ['reason' => 'Pengujian akses mitra lain'])->assertNotFound();
        }
        $this->assertNull($booking->fresh()->completed_at);
        $this->assertSame('reserved_sandbox', $booking->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_owning_manager_can_cancel_culinary_reservation_and_return_quota_once(): void
    {
        $owner = User::factory()->create();
        $partner = Partner::factory()->create();
        PartnerMember::create(['user_id' => $owner->id, 'partner_id' => $partner->id, 'role' => 'manager', 'is_active' => true]);
        $booking = MealBooking::factory()->create(['status' => 'reserved_sandbox', 'confirmed_at' => now()]);
        $booking->mealSlot->culinaryPlace->update(['partner_id' => $partner->id]);
        $booking->mealSlot->update(['reserved' => $booking->quantity + 1]);
        $this->actingAs($owner);
        $revision = ServiceReservationConfirmationController::revision($booking);
        $this->action($booking, 'cancel', $revision, ['reason' => 'Restoran tutup untuk perbaikan'])->assertOk();
        $this->action($booking, 'cancel', $revision, ['reason' => 'Restoran tutup untuk perbaikan'])->assertOk();
        $this->assertSame(1, $booking->mealSlot->fresh()->reserved);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $owner->id, 'action' => 'service.reservation_cancelled']);
    }

    public function test_guest_and_customer_cannot_run_manager_actions_and_departure_date_blocks_check_in(): void
    {
        $this->travelTo(Carbon::parse('2026-10-04 01:00:00', 'UTC'));
        $booking = $this->lodging();
        $this->action($booking, 'check-in')->assertUnauthorized();
        $this->actingAs($booking->user);
        foreach (['check-in', 'complete', 'cancel'] as $action) {
            $this->action($booking, $action, extra: ['reason' => 'Pengujian akses pengunjung'])->assertForbidden();
        }
        $booking->confirmed_at = now();
        $booking->save();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->action($booking, 'check-in')->assertConflict();
        $this->assertNull($booking->fresh()->checked_in_at);
    }
}
