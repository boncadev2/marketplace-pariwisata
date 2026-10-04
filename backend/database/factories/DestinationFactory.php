<?php

namespace Database\Factories;

use App\Models\Destination;
use App\Models\Partner;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Destination>
 */
class DestinationFactory extends Factory
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
            'partner_id' => fn (array $attributes) => Partner::factory()->create(['region_id' => $attributes['region_id']])->id,
            'name' => fake()->unique()->city().' Wisata',
            'slug' => fake()->unique()->slug(),
            'publication_status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['publication_status' => 'published']);
    }
}
