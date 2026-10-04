<?php

namespace Database\Factories;

use App\Models\ReconciliationAlert;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReconciliationAlert>
 */
class ReconciliationAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fingerprint' => hash('sha256', Str::uuid()->toString()),
            'type' => 'payment_status_mismatch',
            'severity' => 'high',
            'status' => 'open',
            'details' => ['message' => 'Fixture rekonsiliasi'],
            'detected_at' => now(),
        ];
    }
}
