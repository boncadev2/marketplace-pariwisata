<?php

namespace Tests\Feature;

use App\Models\CulinaryPlace;
use App\Models\Destination;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\UmkmProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private function owner(Destination $destination): User
    {
        $user = User::factory()->create();
        PartnerMember::create(['partner_id' => $destination->partner_id, 'user_id' => $user->id, 'role' => 'owner', 'is_active' => true]);

        return $user;
    }

    private function destinationPayload(Destination $seed): array
    {
        return ['partner_id' => $seed->partner_id, 'region_id' => $seed->region_id, 'name' => 'Destinasi Baru', 'summary' => 'Wisata alam lokal', 'description' => 'Deskripsi lengkap destinasi lokal.', 'address' => 'Jalan Wisata, Desa Uji', 'publication_status' => 'published', 'latitude' => -6.2, 'longitude' => 106.8];
    }

    private function row(string $key, int $id, string $time = '09:00'): array
    {
        return [$key => $id, 'day_number' => 1, 'starts_at' => $time, 'duration_minutes' => 60, 'quantity' => 1, 'included' => true, 'additional_cost' => 0, 'description' => 'Termasuk dalam perjalanan.'];
    }

    private function packagePayload(Destination $destination): array
    {
        return ['partner_id' => $destination->partner_id, 'name' => 'Paket Lokal', 'description' => 'Perjalanan mengenal desa.', 'base_price' => 250000, 'duration_days' => 1, 'meeting_point' => 'Balai desa', 'minimum_participants' => 1, 'maximum_participants' => 10, 'status' => 'published', 'inclusions' => ['Tiket masuk'], 'exclusions' => ['Belanja pribadi'], 'items' => [$this->row('destination_id', $destination->id)]];
    }

    public function test_owner_creates_edits_destination_and_public_catalog_updates(): void
    {
        $seed = Destination::factory()->create();
        $this->actingAs($this->owner($seed));
        $payload = $this->destinationPayload($seed);
        $created = $this->postJson('/api/v1/dashboard/travel/destinations', [...$payload, 'slug' => 'injected'])->assertCreated();
        $slug = $created->json('data.slug');
        $this->assertNotSame('injected', $slug);
        $this->getJson('/api/v1/destinations/'.$slug)->assertOk()->assertJsonPath('data.address', $payload['address']);
        $this->patchJson('/api/v1/dashboard/travel/destinations/'.$created->json('data.id'), [...$payload, 'name' => 'Nama Baru', 'revision' => $created->json('data.revision')])->assertOk()->assertJsonPath('data.slug', $slug);
        $this->getJson('/api/v1/destinations')->assertJsonPath('data.0.name', 'Nama Baru');
        $this->assertDatabaseHas('audit_logs', ['action' => 'travel_catalog.saved', 'user_id' => auth()->id()]);
        $this->patchJson('/api/v1/dashboard/travel/destinations/'.$created->json('data.id'), [...$payload, 'revision' => $created->json('data.revision')])->assertConflict();
    }

    public function test_one_destination_is_enough_and_multiple_destinations_culinary_umkm_are_shown(): void
    {
        $destination = Destination::factory()->published()->create();
        $this->actingAs($this->owner($destination));
        $payload = $this->packagePayload($destination);
        $created = $this->postJson('/api/v1/dashboard/travel/packages', $payload)->assertCreated();
        $slug = $created->json('data.slug');
        $this->getJson('/api/v1/tour-packages/'.$slug)->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.base_price', 250000);
        $second = Destination::factory()->published()->create();
        $culinary = CulinaryPlace::factory()->create();
        $umkm = UmkmProduct::factory()->create(['status' => 'published']);
        $payload['items'][] = $this->row('destination_id', $second->id, '10:00');
        $payload['items'][] = $this->row('culinary_place_id', $culinary->id, '11:00');
        $payload['items'][] = [...$this->row('umkm_product_id', $umkm->id, '12:00'), 'quantity' => 2];
        $this->patchJson('/api/v1/dashboard/travel/packages/'.$created->json('data.id'), [...$payload, 'revision' => $created->json('data.revision')])->assertOk();
        $this->getJson('/api/v1/tour-packages/'.$slug)->assertOk()->assertJsonCount(4, 'data.items')->assertJsonPath('data.items.2.kind', 'kuliner')->assertJsonPath('data.items.3.kind', 'umkm')->assertJsonPath('data.items.3.quantity', 2)->assertJsonPath('data.items.3.title', $umkm->name)->assertJsonMissingPath('data.partner_id');
        $this->assertDatabaseCount('package_itinerary_items', 4);
        $this->assertDatabaseHas('products', ['slug' => $slug, 'destination_id' => $destination->id, 'base_price' => 250000]);
    }

    public function test_invalid_itinerary_does_not_publish_or_partially_replace_saved_package(): void
    {
        $destination = Destination::factory()->published()->create();
        $this->actingAs($this->owner($destination));
        $payload = $this->packagePayload($destination);
        $created = $this->postJson('/api/v1/dashboard/travel/packages', $payload)->assertCreated();
        $url = '/api/v1/dashboard/travel/packages/'.$created->json('data.id');
        $revision = $created->json('data.revision');
        foreach ([[], [$this->row('culinary_place_id', CulinaryPlace::factory()->create()->id)], [$this->row('destination_id', $destination->id), $this->row('destination_id', $destination->id, '09:30')], [[...$this->row('destination_id', $destination->id), 'day_number' => 2]], [[...$this->row('destination_id', $destination->id), 'starts_at' => '23:30']], [[...$this->row('destination_id', $destination->id), 'additional_cost' => 100]]] as $items) {
            $this->patchJson($url, [...$payload, 'revision' => $revision, 'items' => $items])->assertUnprocessable();
        }
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('package_itinerary_items', 1);
        $this->getJson('/api/v1/tour-packages/'.$created->json('data.slug'))->assertJsonCount(1, 'data.items');
    }

    public function test_drafts_are_hidden_and_optional_cost_is_separate_from_base_price(): void
    {
        $destination = Destination::factory()->published()->create();
        $this->actingAs($this->owner($destination));
        $payload = $this->packagePayload($destination);
        $payload['status'] = 'draft';
        $payload['items'][0]['included'] = false;
        $payload['items'][0]['additional_cost'] = 20000;
        $created = $this->postJson('/api/v1/dashboard/travel/packages', $payload)->assertCreated();
        $this->getJson('/api/v1/tour-packages/'.$created->json('data.slug'))->assertNotFound();
        $payload['status'] = 'published';
        $this->patchJson('/api/v1/dashboard/travel/packages/'.$created->json('data.id'), [...$payload, 'revision' => $created->json('data.revision')])->assertOk();
        $this->getJson('/api/v1/tour-packages/'.$created->json('data.slug'))->assertOk()->assertJsonPath('data.base_price', 250000)->assertJsonPath('data.items.0.additional_cost', 20000)->assertJsonPath('data.items.0.included', false);
        $unpublished = Destination::factory()->create();
        $this->postJson('/api/v1/dashboard/travel/packages', [...$payload, 'items' => [$this->row('destination_id', $unpublished->id)]])->assertUnprocessable();
    }

    public function test_tenant_isolation_guest_visitor_inactive_and_unverified_are_denied(): void
    {
        $seed = Destination::factory()->create();
        $other = Destination::factory()->create();
        $payload = $this->destinationPayload($other);
        $this->getJson('/api/v1/dashboard/travel/options')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/api/v1/dashboard/travel/destinations', $payload)->assertForbidden();
        $owner = $this->owner($seed);
        $this->actingAs($owner)->getJson('/api/v1/dashboard/travel/destinations')->assertOk()->assertJsonPath('meta.total', 1);
        $this->postJson('/api/v1/dashboard/travel/destinations', $payload)->assertNotFound();
        $this->patchJson('/api/v1/dashboard/travel/destinations/'.$other->id, [...$payload, 'revision' => str_repeat('a', 64)])->assertNotFound();
        $owner->forceFill(['email_verified_at' => null])->save();
        $this->getJson('/api/v1/dashboard/travel/options')->assertForbidden();
        $owner->forceFill(['email_verified_at' => now()])->save();
        $owner->partnerMemberships()->update(['is_active' => false]);
        $this->getJson('/api/v1/dashboard/travel/options')->assertForbidden();
        $this->assertDatabaseCount('destinations', 2);
    }

    public function test_manager_can_edit_but_staff_and_rejected_partner_cannot_manage(): void
    {
        $destination = Destination::factory()->published()->create();
        $user = $this->owner($destination);
        $user->partnerMemberships()->update(['role' => 'manager']);
        $this->actingAs($user)->getJson('/api/v1/dashboard/travel/options')->assertOk();
        $user->partnerMemberships()->update(['role' => 'staff']);
        $this->getJson('/api/v1/dashboard/travel/options')->assertForbidden();
        $user->partnerMemberships()->update(['role' => 'owner']);
        Partner::findOrFail($destination->partner_id)->update(['status' => 'rejected']);
        $this->postJson('/api/v1/dashboard/travel/packages', $this->packagePayload($destination))->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_package_rejects_mixed_references_and_stale_edits_and_preserves_partner(): void
    {
        $destination = Destination::factory()->published()->create();
        $this->actingAs($this->owner($destination));
        $payload = $this->packagePayload($destination);
        $culinary = CulinaryPlace::factory()->create();
        $mixed = [...$this->row('destination_id', $destination->id), 'culinary_place_id' => $culinary->id];
        $this->postJson('/api/v1/dashboard/travel/packages', [...$payload, 'items' => [$mixed]])->assertUnprocessable();
        $culinary->update(['is_active' => false]);
        $this->postJson('/api/v1/dashboard/travel/packages', [...$payload, 'items' => [$payload['items'][0], $this->row('culinary_place_id', $culinary->id, '12:00')]])->assertUnprocessable();
        $created = $this->postJson('/api/v1/dashboard/travel/packages', [...$payload, 'type' => 'ticket', 'currency' => 'USD'])->assertCreated()->assertJsonPath('data.type', 'package')->assertJsonPath('data.currency', 'IDR');
        $url = '/api/v1/dashboard/travel/packages/'.$created->json('data.id');
        $other = Destination::factory()->published()->create();
        $this->patchJson($url, [...$payload, 'partner_id' => $other->partner_id, 'name' => 'Nama Diperbarui', 'revision' => $created->json('data.revision')])->assertOk()->assertJsonPath('data.partner_id', $destination->partner_id);
        $this->patchJson($url, [...$payload, 'revision' => $created->json('data.revision')])->assertConflict();
        $this->assertDatabaseHas('products', ['id' => $created->json('data.id'), 'name' => 'Nama Diperbarui', 'partner_id' => $destination->partner_id]);
    }

    public function test_verified_admin_can_create_for_approved_partner_without_membership(): void
    {
        $destination = Destination::factory()->published()->create();
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $this->postJson('/api/v1/dashboard/travel/packages', $this->packagePayload($destination))->assertCreated();
        $this->getJson('/api/v1/dashboard/travel/packages')->assertOk()->assertJsonPath('meta.total', 1);
    }
}
