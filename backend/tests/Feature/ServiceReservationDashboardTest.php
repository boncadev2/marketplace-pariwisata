<?php

namespace Tests\Feature;

use App\Models\LodgingBooking;
use App\Models\MealBooking;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceReservationDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_verified_admin_can_read_all_customer_reservations(): void
    {
        $url = '/api/v1/dashboard/service-reservations?type=culinary';
        $this->getJson($url)->assertUnauthorized();
        foreach ([['platform_role' => 'customer'], ['platform_role' => 'super_admin', 'email_verified_at' => null]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            $this->getJson($url)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->getJson($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_meal_filter_uses_wib_day_and_exposes_only_safe_order_snapshot(): void
    {
        $this->travelTo(Carbon::parse('2026-10-02 01:00:00', 'UTC'));
        $user = User::factory()->create(['name' => 'Pengunjung Pengujian']);
        $meal = MealBooking::factory()->create(['user_id' => $user->id, 'time_slot' => '2026-10-02 17:30:00', 'status' => 'reserved_sandbox', 'package_name' => 'Paket saat dipesan', 'unit_price' => '25000.50', 'total_price' => '50001.00', 'quantity' => 2]);
        $meal->mealSlot->update(['price' => '90000.00', 'package_name' => 'Nama paket baru']);
        MealBooking::factory()->create(['time_slot' => '2026-10-03 17:00:00', 'status' => 'reserved_sandbox']);
        MealBooking::factory()->create(['time_slot' => '2026-10-03 05:00:00', 'status' => 'cancelled']);
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $r = $this->getJson('/api/v1/dashboard/service-reservations?type=culinary&date=2026-10-03&status=reserved_sandbox')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $meal->id)->assertJsonPath('data.data.0.customer_name', $user->name)->assertJsonPath('data.data.0.package_name', 'Paket saat dipesan')->assertJsonPath('data.data.0.total_price', '50001.00');
        $this->assertNotNull($r->json('data.data.0.place_name'));
        foreach (['user_id', 'idempotency_key', 'email', 'password', 'phone'] as $field) {
            $this->assertArrayNotHasKey($field, $r->json('data.data.0'));
        }
        $this->assertSame('reserved_sandbox', $meal->fresh()->status);
    }

    public function test_lodging_filter_and_exact_id_return_dates_rooms_guests_and_historical_total(): void
    {
        $this->freezeTime();
        $room = RoomType::factory()->create(['name' => 'Homestay Pengujian']);
        $booking = LodgingBooking::query()->create(['user_id' => User::factory()->create()->id, 'room_type_id' => $room->id, 'check_in' => '2026-10-03', 'check_out' => '2026-10-05', 'total_price' => '600000.00', 'quantity' => 2, 'guests' => 4, 'status' => 'reserved_sandbox', 'idempotency_key' => 'test-reservation-dashboard', 'nightly_prices' => []]);
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->getJson('/api/v1/dashboard/service-reservations?type=lodging&date=2026-10-03&id='.$booking->id)->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.place_name', $room->name)->assertJsonPath('data.data.0.nights', 2)->assertJsonPath('data.data.0.quantity', 2)->assertJsonPath('data.data.0.guests', 4)->assertJsonPath('data.data.0.total_price', '600000.00');
        $this->getJson('/api/v1/dashboard/service-reservations?type=lodging&date=2026-10-04')->assertJsonCount(0, 'data.data');
    }

    public function test_validation_pagination_and_missing_id_do_not_leak_unfiltered_results(): void
    {
        $this->freezeTime();
        MealBooking::factory()->count(21)->create(['status' => 'reserved_sandbox']);
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->getJson('/api/v1/dashboard/service-reservations?type=culinary')->assertJsonCount(20, 'data.data')->assertJsonPath('data.total', 21)->assertJsonPath('data.last_page', 2);
        $this->getJson('/api/v1/dashboard/service-reservations?type=culinary&page=2')->assertJsonCount(1, 'data.data');
        $this->getJson('/api/v1/dashboard/service-reservations?type=culinary&id=999999')->assertJsonCount(0, 'data.data');
        foreach (['type=anything', 'type=culinary&status=paid', 'type=culinary&date=invalid', 'type=culinary&id=-1', 'type=culinary&page=-1'] as $query) {
            $this->getJson('/api/v1/dashboard/service-reservations?'.$query)->assertUnprocessable();
        }
    }
}
