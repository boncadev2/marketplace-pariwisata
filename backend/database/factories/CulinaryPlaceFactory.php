<?php

namespace Database\Factories;

use App\Models\CulinaryPlace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CulinaryPlace>
 */
class CulinaryPlaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'location' => 'Desa Demonstrasi',
            'is_active' => true,
        ];
    }
}
