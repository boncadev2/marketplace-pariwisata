<?php

namespace Tests\Feature;

use App\Models\CulinaryPlace;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServicePartnerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_only_owned_places_and_cannot_read_other_calendars(): void
    {
        $owner = User::factory()->create();
        $partner = Partner::factory()->create(['status' => 'approved']);
        PartnerMember::create(['user_id' => $owner->id, 'partner_id' => $partner->id, 'role' => 'owner', 'is_active' => true]);
        $own = CulinaryPlace::factory()->create(['partner_id' => $partner->id]);
        $other = CulinaryPlace::factory()->create();
        $room = RoomType::factory()->create(['partner_id' => $partner->id]);
        $otherRoom = RoomType::factory()->create();
        $this->actingAs($owner);
        $this->getJson('/api/v1/me')->assertJsonPath('data.can_manage_services', true);
        $this->getJson('/api/v1/dashboard/culinary-places')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $date = now()->toDateString();
        $this->getJson('/api/v1/dashboard/lodging-rooms?date='.$date)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $room->id);
        $this->getJson('/api/v1/dashboard/culinary-places/'.$other->id.'/slots?date='.$date)->assertNotFound();
        $this->getJson('/api/v1/dashboard/lodging-rooms/'.$otherRoom->id.'/calendar?start_date='.$date.'&end_date='.$date)->assertNotFound();
        $this->getJson('/api/v1/dashboard/service-reservations?type=culinary')->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson('/api/v1/dashboard/culinary-places/'.$own->id.'/slots?date='.$date)->assertOk();
    }

    public function test_staff_inactive_and_unapproved_members_are_denied(): void
    {
        foreach ([['staff', true, 'approved'], ['owner', false, 'approved'], ['manager', true, 'pending']] as [$role, $active, $status]) {
            $user = User::factory()->create();
            $partner = Partner::factory()->create(['status' => $status]);
            PartnerMember::create(['user_id' => $user->id, 'partner_id' => $partner->id, 'role' => $role, 'is_active' => $active]);
            $this->actingAs($user)->getJson('/api/v1/dashboard/culinary-places')->assertForbidden();
        }
    }

    public function test_owner_creation_requires_authorized_partner_and_cannot_edit_another_place(): void
    {
        $owner = User::factory()->create();
        $partner = Partner::factory()->create();
        $foreign = Partner::factory()->create();
        PartnerMember::create(['user_id' => $owner->id, 'partner_id' => $partner->id, 'role' => 'owner', 'is_active' => true]);
        $other = CulinaryPlace::factory()->create(['partner_id' => $foreign->id]);
        $this->actingAs(User::factory()->create(['platform_role' => 'super_admin']));
        $otherData = collect($this->getJson('/api/v1/dashboard/culinary-places')->json('data'))->firstWhere('id', $other->id);
        $payload = ['name' => 'Rumah Makan Pengujian', 'description' => 'Deskripsi tempat untuk pengujian akses.', 'location' => null, 'latitude' => null, 'longitude' => null, 'image_url' => null, 'location_is_demo' => true, 'photos_are_illustrations' => false, 'is_active' => false];
        $this->actingAs($owner);
        $this->withHeader('Idempotency-Key', 'fde388de-4bb4-4502-83bd-5f57478c5ae0');
        $this->postJson('/api/v1/dashboard/culinary-places', $payload)->assertUnprocessable();
        $this->postJson('/api/v1/dashboard/culinary-places', [...$payload, 'partner_id' => $foreign->id])->assertNotFound();
        $created = $this->postJson('/api/v1/dashboard/culinary-places', [...$payload, 'partner_id' => $partner->id])->assertCreated()->assertJsonPath('data.partner_id', $partner->id)->json('data');
        $this->postJson('/api/v1/dashboard/culinary-places', [...$payload, 'partner_id' => $partner->id])->assertOk()->assertJsonPath('data.id', $created['id']);
        $this->patchJson('/api/v1/dashboard/culinary-places/'.$other->id, [...$payload, 'revision' => $otherData['revision']])->assertNotFound();
        $this->patchJson('/api/v1/dashboard/culinary-places/'.$created['id'], [...$payload, 'revision' => $created['revision']])->assertOk();
        $this->patchJson('/api/v1/dashboard/culinary-places/'.$created['id'], [...$payload, 'partner_id' => $foreign->id, 'revision' => $created['revision']])->assertNotFound();
        $this->assertSame($partner->id, CulinaryPlace::findOrFail($created['id'])->partner_id);
    }

    public function test_private_photos_and_schedule_mutations_are_scoped_to_owner(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $partner = Partner::factory()->create();
        PartnerMember::create(['user_id' => $owner->id, 'partner_id' => $partner->id, 'role' => 'owner', 'is_active' => true]);
        $own = CulinaryPlace::factory()->create(['partner_id' => $partner->id, 'is_active' => false]);
        $other = CulinaryPlace::factory()->create(['is_active' => false]);
        foreach ([$own, $other] as $place) {
            $place->photo_path = 'culinary/'.$place->id.'/test.jpg';
            $place->save();
            Storage::disk('local')->put($place->photo_path, 'test-photo');
        }
        $this->actingAs($owner);
        $this->get('/api/v1/culinary/places/'.$own->id.'/photo')->assertOk();
        $this->get('/api/v1/culinary/places/'.$other->id.'/photo')->assertNotFound();
        $payload = ['date' => now('Asia/Jakarta')->addDays(2)->toDateString(), 'time' => '12:00', 'package_name' => 'Paket Uji', 'price' => 50000, 'capacity' => 5, 'is_active' => false];
        $this->postJson('/api/v1/dashboard/culinary-places/'.$other->id.'/slots', $payload)->assertNotFound();
        $this->assertDatabaseCount('meal_slots', 0);
        $this->postJson('/api/v1/dashboard/culinary-places/'.$own->id.'/slots', $payload)->assertCreated();
    }
}
