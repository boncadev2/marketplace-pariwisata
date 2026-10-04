<?php

namespace Tests\Feature;

use App\Models\Product;
use Carbon\CarbonImmutable;
use Database\Seeders\PilotDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotDatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_is_idempotent_and_keeps_checkout_closed(): void
    {
        config()->set('pilot.demo_password', 'Demo-Pilot-Password-2026!');
        $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00', 'Asia/Jakarta'));

        $this->seed(PilotDatabaseSeeder::class);
        $this->seed(PilotDatabaseSeeder::class);

        $product = Product::query()->where('slug', 'tiket-pilot-demo')->firstOrFail();
        $this->assertDatabaseHas('destinations', [
            'slug' => 'destinasi-pilot-demo',
            'publication_status' => 'published',
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'base_price' => 10_000,
            'status' => 'published',
        ]);
        $this->assertDatabaseCount('inventory_buckets', 14);
        $this->assertDatabaseCount('partner_members', 2);
        $this->assertDatabaseHas('pilot_controls', [
            'id' => 1,
            'checkout_enabled' => false,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'admin.pilot@example.test',
            'platform_role' => 'super_admin',
        ]);
    }

    public function test_demo_seed_rejects_missing_or_weak_password(): void
    {
        config()->set('pilot.demo_password', 'weak');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PILOT_DEMO_PASSWORD must contain at least 16 characters.');

        $this->seed(PilotDatabaseSeeder::class);
    }
}
