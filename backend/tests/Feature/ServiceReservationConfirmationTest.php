<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ServiceReservationConfirmationController;
use App\Models\LodgingBooking;
use App\Models\MealBooking;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceReservationConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_confirmation_is_visible_to_customer_and_retry_does_not_duplicate_audit(): void
    {
        $owner = User::factory()->create();
        $partner = Partner::factory()->create();
        PartnerMember::create(['user_id' => $owner->id, 'partner_id' => $partner->id, 'role' => 'manager', 'is_active' => true]);
        $booking = MealBooking::factory()->create(['status' => 'reserved_sandbox']);
        $booking->mealSlot->culinaryPlace->update(['partner_id' => $partner->id]);
        $url = '/api/v1/dashboard/service-reservations/culinary/'.$booking->id.'/confirm';
        $payload = ['revision' => ServiceReservationConfirmationController::revision($booking)];
        $this->actingAs($owner);
        $this->postJson($url, ['revision' => str_repeat('a', 64)])->assertConflict();
        $this->assertNull($booking->fresh()->confirmed_at);
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.status', 'reserved_sandbox');
        $confirmed = $booking->fresh()->confirmed_at->toIso8601String();
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.confirmed_at', $confirmed);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->actingAs($booking->user)->getJson('/api/v1/account/meal-bookings')->assertOk()->assertJsonPath('data.data.0.confirmed_at', $booking->fresh()->confirmed_at->toJSON());
    }

    public function test_other_partner_cannot_see_or_confirm_reservation_and_cancelled_booking_is_rejected(): void
    {
        $owner = User::factory()->create();
        $partner = Partner::factory()->create();
        PartnerMember::create(['user_id' => $owner->id, 'partner_id' => $partner->id, 'role' => 'owner', 'is_active' => true]);
        $booking = MealBooking::factory()->create(['status' => 'reserved_sandbox']);
        $url = '/api/v1/dashboard/service-reservations/culinary/'.$booking->id.'/confirm';
        $this->actingAs($owner)->getJson('/api/v1/dashboard/service-reservations?type=culinary')->assertJsonCount(0, 'data.data');
        $this->postJson($url, ['revision' => ServiceReservationConfirmationController::revision($booking)])->assertNotFound();
        $this->assertNull($booking->fresh()->confirmed_at);
        $booking->update(['status' => 'cancelled']);
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->postJson($url, ['revision' => ServiceReservationConfirmationController::revision($booking)])->assertConflict();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_can_confirm_lodging_without_changing_price_or_inventory(): void
    {
        $room = RoomType::factory()->create();
        $booking = LodgingBooking::create(['user_id' => User::factory()->create()->id, 'room_type_id' => $room->id, 'check_in' => now()->addDay()->toDateString(), 'check_out' => now()->addDays(2)->toDateString(), 'quantity' => 1, 'guests' => 2, 'total_price' => '150000.00', 'status' => 'reserved_sandbox', 'nightly_prices' => [], 'idempotency_key' => 'confirmation-lodging-test']);
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->postJson('/api/v1/dashboard/service-reservations/lodging/'.$booking->id.'/confirm', ['revision' => ServiceReservationConfirmationController::revision($booking)])->assertOk();
        $this->assertNotNull($booking->fresh()->confirmed_at);
        $this->assertSame('150000.00', $booking->fresh()->total_price);
        $this->assertDatabaseCount('room_inventories', 0);
        $this->getJson('/api/v1/dashboard/service-reservations?type=lodging')->assertJsonPath('data.data.0.confirmed_at', $booking->fresh()->confirmed_at->toIso8601String());
    }
}
