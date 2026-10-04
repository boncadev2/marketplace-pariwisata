<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Destination;
use App\Models\InventoryBucket;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceAndMobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_destination_catalog_uses_bounded_queries_and_supports_conditional_cache(): void
    {
        [$destination] = $this->catalogFixture();
        Cache::flush();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $response = $this->getJson('/api/v1/destinations?per_page=20')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $destination->name)
            ->assertJsonMissingPath('data.0.partner_id');

        $this->assertLessThanOrEqual(2, count($queries));
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=60', $cacheControl);
        $etag = $response->headers->get('ETag');
        $this->assertNotNull($etag);
        $this->withHeader('If-None-Match', $etag)->getJson('/api/v1/destinations?per_page=20')->assertNotModified();
    }

    public function test_destination_cache_is_invalidated_after_public_content_changes(): void
    {
        [$destination] = $this->catalogFixture();
        $first = $this->getJson('/api/v1/destinations')->assertOk();

        $destination->update(['name' => 'Nama Destinasi Baru']);

        $second = $this->getJson('/api/v1/destinations')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Nama Destinasi Baru');
        $this->assertNotSame($first->headers->get('ETag'), $second->headers->get('ETag'));
    }

    public function test_product_catalog_only_caches_public_fields_and_invalidates_on_publish_change(): void
    {
        [, $partner] = $this->catalogFixture();
        $published = Product::factory()->create([
            'partner_id' => $partner->id,
            'name' => 'Tiket Publik',
            'slug' => 'tiket-publik',
            'status' => 'published',
            'base_price' => 75_000,
        ]);
        Product::factory()->create(['partner_id' => $partner->id, 'name' => 'Produk Draft', 'status' => 'draft']);

        $first = $this->getJson('/api/v1/products')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'tiket-publik')
            ->assertJsonMissingPath('data.0.partner_id');

        $cacheControl = (string) $first->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=60', $cacheControl);

        $published->update(['name' => 'Tiket Publik Baru']);

        $second = $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.0.name', 'Tiket Publik Baru');
        $this->assertNotSame($first->headers->get('ETag'), $second->headers->get('ETag'));
    }

    public function test_quote_and_checkout_responses_are_never_publicly_cached(): void
    {
        [, $partner] = $this->catalogFixture();
        $product = Product::factory()->create([
            'partner_id' => $partner->id,
            'slug' => 'mobile-checkout',
            'status' => 'published',
            'base_price' => 50_000,
        ]);
        InventoryBucket::create([
            'product_id' => $product->id,
            'service_date' => '2026-10-10',
            'session_key' => 'default',
            'capacity' => 1,
        ]);

        $quoteResponse = $this->getJson('/api/v1/products/mobile-checkout/quote?visit_date=2026-10-10&quantity=1')
            ->assertOk();
        $quoteCacheControl = (string) $quoteResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $quoteCacheControl);
        $this->assertStringNotContainsString('public', $quoteCacheControl);

        $inventoryResponse = $this->getJson('/api/v1/products/mobile-checkout/inventory?from=2026-10-10&to=2026-10-10')
            ->assertOk();
        $inventoryCacheControl = (string) $inventoryResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $inventoryCacheControl);
        $this->assertStringNotContainsString('public', $inventoryCacheControl);

        $checkoutResponse = $this->postJson('/api/v1/checkout', [
            'product_slug' => 'mobile-checkout',
            'visit_date' => '2026-10-10',
            'quantity' => 1,
            'customer_name' => 'Pengunjung Mobile',
            'customer_email' => 'mobile@example.test',
        ], ['Idempotency-Key' => 'mobile-checkout-key-0001'])
            ->assertCreated();
        $checkoutCacheControl = (string) $checkoutResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $checkoutCacheControl);
        $this->assertStringNotContainsString('public', $checkoutCacheControl);
    }

    public function test_repeated_booking_cannot_exceed_available_inventory(): void
    {
        [, $partner] = $this->catalogFixture();
        $product = Product::factory()->create([
            'partner_id' => $partner->id,
            'slug' => 'limited-checkout',
            'status' => 'published',
            'base_price' => 50_000,
        ]);
        InventoryBucket::create([
            'product_id' => $product->id,
            'service_date' => '2026-10-10',
            'session_key' => 'default',
            'capacity' => 1,
        ]);
        $payload = [
            'product_slug' => 'limited-checkout',
            'visit_date' => '2026-10-10',
            'quantity' => 1,
            'customer_name' => 'Pengunjung Mobile',
            'customer_email' => 'mobile@example.test',
        ];

        $this->postJson('/api/v1/checkout', $payload, ['Idempotency-Key' => 'limited-checkout-key-001'])->assertCreated();
        $this->postJson('/api/v1/checkout', $payload, ['Idempotency-Key' => 'limited-checkout-key-002'])
            ->assertConflict()
            ->assertJsonPath('error.code', 'INVENTORY_UNAVAILABLE');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_holds', 1);
    }

    /** @return array{0: Destination, 1: Partner} */
    private function catalogFixture(): array
    {
        $region = Region::factory()->create(['name' => 'Wilayah Performa']);
        $category = Category::factory()->create(['name' => 'Alam', 'slug' => fake()->unique()->slug()]);
        $partner = Partner::factory()->create(['region_id' => $region->id]);
        $destination = Destination::factory()->create([
            'partner_id' => $partner->id,
            'region_id' => $region->id,
            'category_id' => $category->id,
            'name' => 'Air Terjun Performa',
            'slug' => 'air-terjun-performa',
            'publication_status' => 'published',
        ]);

        return [$destination, $partner];
    }
}
