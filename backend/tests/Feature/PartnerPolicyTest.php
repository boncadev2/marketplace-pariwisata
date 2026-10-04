<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_member_cannot_access_another_partner(): void
    {
        $region = Region::create(['code' => 'TEST-01', 'name' => 'Wilayah Test', 'type' => 'regency']);
        $partnerA = Partner::create(['region_id' => $region->id, 'name' => 'Mitra A', 'slug' => 'mitra-a', 'status' => 'approved']);
        $partnerB = Partner::create(['region_id' => $region->id, 'name' => 'Mitra B', 'slug' => 'mitra-b', 'status' => 'approved']);
        $user = User::factory()->create();
        PartnerMember::create(['partner_id' => $partnerA->id, 'user_id' => $user->id, 'role' => 'owner']);

        $this->assertFalse($user->can('view', $partnerB));
        $this->assertFalse($user->can('update', $partnerB));
    }
}
