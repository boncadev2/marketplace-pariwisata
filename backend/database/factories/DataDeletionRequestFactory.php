<?php

namespace Database\Factories;

use App\Models\DataDeletionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataDeletionRequest>
 */
class DataDeletionRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'requested',
            'reason' => fake()->optional()->sentence(),
            'requested_at' => now(),
        ];
    }
}
