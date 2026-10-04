<?php

namespace Tests\Feature;

use App\Models\CulinaryPlace;
use App\Models\MealSlot;
use App\Models\User;
use App\Services\MealReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CulinaryScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return [...['date' => '2026-10-03', 'time' => '12:30', 'package_name' => 'Paket Makan Pengujian', 'price' => '75000.25', 'capacity' => 10, 'is_active' => true], ...$extra];
    }

    public function test_admin_creates_wib_schedule_and_duplicate_retry_does_not_create_another_slot(): void
    {
        $this->travelTo(Carbon::parse('2026-10-02 01:00:00', 'UTC'));
        $place = CulinaryPlace::factory()->create();
        $url = '/api/v1/dashboard/culinary-places/'.$place->id.'/slots';
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $r = $this->postJson($url, $this->payload())->assertCreated()->assertJsonPath('data.time_slot', '2026-10-03T05:30:00+00:00')->assertJsonPath('data.reserved', 0);
        $this->postJson($url, $this->payload())->assertConflict();
        $this->assertDatabaseCount('meal_slots', 1);
        $this->getJson($url.'?date=2026-10-03')->assertOk()->assertJsonPath('data.data.0.id', $r->json('data.id'));
        $this->getJson('/api/v1/culinary/places/'.$place->id.'/slots?date=2026-10-03')->assertOk()->assertJsonPath('data.data.0.id', $r->json('data.id'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'culinary.schedule_created']);
    }

    public function test_price_and_capacity_edits_preserve_reserved_count_and_booking_snapshot(): void
    {
        $this->travelTo(Carbon::parse('2026-10-02 01:00:00', 'UTC'));
        $place = CulinaryPlace::factory()->create();
        $slot = MealSlot::factory()->create(['culinary_place_id' => $place->id, 'time_slot' => '2026-10-03 05:30:00', 'package_name' => 'Paket Makan Pengujian', 'price' => '75000.25', 'capacity' => 10, 'reserved' => 0]);
        $booking = app(MealReservationService::class)->reserve(User::factory()->create(), $slot->id, 2, 'schedule-test', '150000.50');
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $url = '/api/v1/dashboard/culinary-places/'.$place->id.'/slots';
        $data = $this->getJson($url.'?date=2026-10-03')->json('data.data.0');
        $this->patchJson($url.'/'.$slot->id, $this->payload(['revision' => $data['revision'], 'capacity' => 1]))->assertUnprocessable();
        $this->patchJson($url.'/'.$slot->id, $this->payload(['revision' => $data['revision'], 'time' => '13:30']))->assertConflict();
        $this->patchJson($url.'/'.$slot->id, $this->payload(['revision' => $data['revision'], 'price' => '90000.50', 'capacity' => 4]))->assertOk()->assertJsonPath('data.reserved', 2)->assertJsonPath('data.available', 2);
        $this->patchJson($url.'/'.$slot->id, $this->payload(['revision' => $data['revision']]))->assertConflict();
        $this->assertDatabaseHas('meal_slots', ['id' => $slot->id, 'reserved' => 2, 'capacity' => 4, 'price' => '90000.50']);
        $this->assertSame('150000.50', $booking->fresh()->total_price);
        $this->assertDatabaseHas('audit_logs', ['action' => 'culinary.schedule_updated']);
    }

    public function test_slot_edit_requires_current_reservation_revision_and_place_scope(): void
    {
        $this->travelTo(Carbon::parse('2026-10-02 01:00:00', 'UTC'));
        $place = CulinaryPlace::factory()->create();
        $slot = MealSlot::factory()->create(['culinary_place_id' => $place->id, 'time_slot' => '2026-10-03 05:30:00']);
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $url = '/api/v1/dashboard/culinary-places/'.$place->id.'/slots';
        $data = $this->getJson($url.'?date=2026-10-03')->json('data.data.0');
        $slot->update(['reserved' => 3]);
        $this->patchJson($url.'/'.$slot->id, $this->payload(['revision' => $data['revision']]))->assertConflict();
        $other = CulinaryPlace::factory()->create();
        $this->patchJson('/api/v1/dashboard/culinary-places/'.$other->id.'/slots/'.$slot->id, $this->payload(['revision' => $data['revision']]))->assertNotFound();
    }

    public function test_guest_customer_and_unverified_admin_cannot_write_schedules(): void
    {
        $this->travelTo(Carbon::parse('2026-10-02 01:00:00', 'UTC'));
        $place = CulinaryPlace::factory()->create();
        $url = '/api/v1/dashboard/culinary-places/'.$place->id.'/slots';
        $this->getJson($url.'?date=2026-10-03')->assertUnauthorized();
        $this->postJson($url, $this->payload())->assertUnauthorized();
        $this->patchJson($url.'/1', $this->payload())->assertUnauthorized();
        foreach ([['platform_role' => 'customer'], ['platform_role' => 'super_admin', 'email_verified_at' => null]] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            $this->getJson($url.'?date=2026-10-03')->assertForbidden();
            $this->postJson($url, $this->payload())->assertForbidden();
            $this->patchJson($url.'/1', $this->payload())->assertForbidden();
        }
        $this->assertDatabaseCount('meal_slots', 0);
    }

    public function test_invalid_and_past_schedule_are_rejected_and_deactivation_hides_public_availability(): void
    {
        $this->travelTo(Carbon::parse('2026-10-02 01:00:00', 'UTC'));
        $place = CulinaryPlace::factory()->create();
        $url = '/api/v1/dashboard/culinary-places/'.$place->id.'/slots';
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        foreach ([['date' => '2026-10-01'], ['time' => '25:00'], ['date' => '2026-10-02', 'time' => '07:00'], ['price' => 0], ['capacity' => -1]] as $extra) {
            $this->postJson($url, $this->payload($extra))->assertUnprocessable();
        }
        $data = $this->postJson($url, $this->payload())->json('data');
        $this->patchJson($url.'/'.$data['id'], $this->payload(['revision' => $data['revision'], 'is_active' => false]))->assertOk();
        $this->getJson('/api/v1/culinary/places/'.$place->id.'/slots?date=2026-10-03')->assertJsonCount(0,'data.data');
        $this->getJson($url.'?date=2026-10-03')->assertJsonCount(1,'data.data');
    }
}
