<?php

namespace Tests\Feature;

use App\Models\InventoryBucket;
use App\Models\PartnerMember;
use App\Models\PilotControl;
use App\Models\Product;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function package(): Product
    {
        $product = Product::factory()->create(['type' => 'package', 'base_price' => 250000]);
        TourPackage::create(['product_id' => $product->id, 'departure_type' => 'open', 'duration_days' => 1, 'meeting_point' => 'Balai desa', 'pricing_mode' => 'per_person', 'minimum_participants' => 2, 'maximum_participants' => 10, 'status' => 'published']);

        return $product;
    }

    private function owner(Product $product): User
    {
        $user = User::factory()->create();
        PartnerMember::create(['user_id' => $user->id, 'partner_id' => $product->partner_id, 'role' => 'owner', 'is_active' => true]);

        return $user;
    }

    private function dates(): array
    {
        return ['start_date' => now('Asia/Jakarta')->addDays(3)->toDateString(), 'end_date' => now('Asia/Jakarta')->addDays(4)->toDateString()];
    }

    private function calendarUrl(Product $product, array $dates): string
    {
        return '/api/v1/dashboard/packages/'.$product->id.'/calendar?'.http_build_query($dates);
    }

    public function test_owner_initializes_dates_and_public_availability_obeys_closed_dates(): void
    {
        $product = $this->package();
        $dates = $this->dates();
        $this->actingAs($this->owner($product));
        $before = $this->getJson($this->calendarUrl($product, $dates))->assertOk()->assertJsonPath('data.days.0.capacity', null)->json('data');
        $saved = $this->patchJson('/api/v1/dashboard/packages/'.$product->id.'/calendar', [...$dates, 'capacity' => 10, 'is_closed' => false, 'revision' => $before['revision'], 'held' => 99])->assertOk()->assertJsonPath('data.days.0.available', 10)->json('data');
        $this->getJson('/api/v1/products/'.$product->slug.'/inventory?from='.$dates['start_date'].'&to='.$dates['end_date'])->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.available', 10);
        $this->patchJson('/api/v1/dashboard/packages/'.$product->id.'/calendar', [...$dates, 'capacity' => 10, 'is_closed' => true, 'revision' => $saved['revision']])->assertOk()->assertJsonPath('data.days.0.available', 0);
        $this->assertDatabaseCount('inventory_buckets', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'package.calendar_updated', 'auditable_id' => $product->id]);
        $this->assertDatabaseHas('inventory_buckets', ['product_id' => $product->id, 'held' => 0]);
    }

    public function test_capacity_cannot_drop_below_holds_and_paid_participants_and_stale_edits_fail(): void
    {
        $product = $this->package();
        $dates = $this->dates();
        $bucket = InventoryBucket::factory()->create(['product_id' => $product->id, 'service_date' => $dates['end_date'], 'capacity' => 10, 'held' => 2, 'confirmed' => 3]);
        $this->actingAs($this->owner($product));
        $before = $this->getJson($this->calendarUrl($product, $dates))->json('data');
        $url = '/api/v1/dashboard/packages/'.$product->id.'/calendar';
        $this->patchJson($url, [...$dates, 'capacity' => 4, 'is_closed' => false, 'revision' => $before['revision']])->assertUnprocessable();
        $this->assertDatabaseCount('inventory_buckets', 1);
        $bucket->increment('held');
        $this->patchJson($url, [...$dates, 'capacity' => 10, 'is_closed' => false, 'revision' => $before['revision']])->assertConflict();
        $current = $this->getJson($this->calendarUrl($product, $dates))->json('data');
        $this->patchJson($url, [...$dates, 'capacity' => 6, 'is_closed' => true, 'revision' => $current['revision']])->assertOk();
        $this->assertDatabaseHas('inventory_buckets', ['id' => $bucket->id, 'held' => 3, 'confirmed' => 3, 'capacity' => 6, 'is_closed' => true]);
    }

    public function test_calendar_rejects_other_partner_guest_visitor_and_non_package(): void
    {
        $product = $this->package();
        $other = $this->package();
        $dates = $this->dates();
        $this->getJson($this->calendarUrl($product, $dates))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($this->calendarUrl($product, $dates))->assertForbidden();
        $this->actingAs($this->owner($product))->getJson($this->calendarUrl($other, $dates))->assertNotFound();
        $this->patchJson('/api/v1/dashboard/packages/'.$other->id.'/calendar', [...$dates, 'capacity' => 10, 'is_closed' => false, 'revision' => str_repeat('a', 64)])->assertNotFound();
        $ticket = Product::factory()->create(['partner_id' => $product->partner_id, 'type' => 'ticket']);
        $this->getJson($this->calendarUrl($ticket, $dates))->assertNotFound();
        $this->assertDatabaseCount('inventory_buckets', 0);
    }

    public function test_calendar_rejects_past_dates_negative_capacity_and_range_above_ninety_days(): void
    {
        $product = $this->package();
        $this->actingAs($this->owner($product));
        $dates = $this->dates();
        $this->getJson($this->calendarUrl($product, ['start_date' => '2020-01-01', 'end_date' => '2020-01-02']))->assertUnprocessable();
        $this->getJson($this->calendarUrl($product, ['start_date' => now('Asia/Jakarta')->toDateString(), 'end_date' => now('Asia/Jakarta')->addDays(90)->toDateString()]))->assertUnprocessable();
        $before = $this->getJson($this->calendarUrl($product, $dates))->json('data');
        $this->patchJson('/api/v1/dashboard/packages/'.$product->id.'/calendar', [...$dates, 'capacity' => -1, 'is_closed' => false, 'revision' => $before['revision']])->assertUnprocessable();
        $this->assertDatabaseCount('inventory_buckets', 0);
    }

    public function test_package_checkout_uses_configured_capacity_and_rejects_invalid_participants_and_dates(): void
    {
        PilotControl::factory()->create(['id' => 1, 'checkout_enabled' => true]);
        $product = $this->package();
        $dates = $this->dates();
        $manager = $this->owner($product);
        $this->actingAs($manager);
        $before = $this->getJson($this->calendarUrl($product, $dates))->json('data');
        $this->patchJson('/api/v1/dashboard/packages/'.$product->id.'/calendar', [...$dates, 'capacity' => 3, 'is_closed' => false, 'revision' => $before['revision']])->assertOk();
        $buyer = User::factory()->create();
        $this->actingAs($buyer);
        $payload = ['product_slug' => $product->slug, 'visit_date' => $dates['start_date'], 'quantity' => 2, 'customer_name' => $buyer->name, 'customer_email' => $buyer->email];
        $this->postJson('/api/v1/checkout', [...$payload, 'quantity' => 1], ['Idempotency-Key' => fake()->uuid()])->assertUnprocessable();
        $this->postJson('/api/v1/checkout', [...$payload, 'quantity' => 11], ['Idempotency-Key' => fake()->uuid()])->assertUnprocessable();
        $this->postJson('/api/v1/checkout', [...$payload, 'visit_date' => '2020-01-01'], ['Idempotency-Key' => fake()->uuid()])->assertUnprocessable();
        $this->postJson('/api/v1/checkout', $payload, ['Idempotency-Key' => fake()->uuid()])->assertCreated()->assertJsonPath('data.total', 500000);
        $this->postJson('/api/v1/checkout', $payload, ['Idempotency-Key' => fake()->uuid()])->assertConflict();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, (int) InventoryBucket::query()->where('product_id', $product->id)->whereDate('service_date', $dates['start_date'])->firstOrFail()->held);
    }
}
