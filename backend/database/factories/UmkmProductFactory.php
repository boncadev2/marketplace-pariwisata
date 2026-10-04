<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\UmkmProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UmkmProduct> */
class UmkmProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(), 'name' => fake()->words(3, true), 'slug' => fake()->unique()->uuid(),
            'description' => fake()->paragraph(), 'location' => 'Desa Wisata', 'price' => 35000,
            'unit' => 'bungkus', 'status' => 'draft', 'is_demo' => false];
    }
}
