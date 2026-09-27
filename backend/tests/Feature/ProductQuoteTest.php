<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_uses_the_highest_priority_active_rule_for_the_visit_date(): void
    {
        $product = $this->publishedProduct();
        ProductPriceRule::create([
            'product_id' => $product->id,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-31',
            'price' => 75_000,
            'priority' => 10,
        ]);
        ProductPriceRule::create([
            'product_id' => $product->id,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-31',
            'price' => 60_000,
            'priority' => 5,
        ]);

        $this->getJson('/api/v1/products/'.$product->slug.'/quote?visit_date=2026-10-10&quantity=3')
            ->assertOk()
            ->assertJsonPath('data.unit_price', 75_000)
            ->assertJsonPath('data.currency', 'IDR')
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonPath('data.total', 225_000);
    }

    public function test_quote_falls_back_to_the_base_price_when_no_rule_applies(): void
    {
        $product = $this->publishedProduct();

        $this->getJson('/api/v1/products/'.$product->slug.'/quote?visit_date=2026-11-01&quantity=2')
            ->assertOk()
            ->assertJsonPath('data.unit_price', 50_000)
            ->assertJsonPath('data.total', 100_000);
    }

    private function publishedProduct(): Product
    {
        $region = Region::create(['code' => 'QUOTE-01', 'name' => 'Wilayah Uji', 'type' => 'regency']);
        $partner = Partner::create([
            'region_id' => $region->id,
            'name' => 'Mitra Uji',
            'slug' => 'mitra-uji',
            'status' => 'approved',
        ]);

        return Product::create([
            'partner_id' => $partner->id,
            'name' => 'Tiket Uji',
            'slug' => 'tiket-uji',
            'type' => 'ticket',
            'base_price' => 50_000,
            'status' => 'published',
        ]);
    }
}
