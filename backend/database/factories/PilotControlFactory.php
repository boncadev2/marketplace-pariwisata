<?php

namespace Database\Factories;

use App\Models\PilotControl;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PilotControl>
 */
class PilotControlFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checkout_enabled' => false,
            'reason' => fake()->sentence(),
            'changed_by' => null,
        ];
    }
}
