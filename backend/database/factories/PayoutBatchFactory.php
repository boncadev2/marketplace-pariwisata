<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PayoutBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'batch_number' => 'PO-'.strtoupper(Str::random(10)),
            'maker_id' => 1,
            'provider' => 'bank_transfer',
            'status' => 'requested',
            'total_amount' => 100000,
            'notes' => $this->faker->sentence,
        ];
    }
}
