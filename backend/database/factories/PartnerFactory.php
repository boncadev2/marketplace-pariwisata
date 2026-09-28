<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'name' => fake()->company(),
            'slug' => fake()->unique()->uuid(),
            'status' => 'approved',
        ];
    }
}
