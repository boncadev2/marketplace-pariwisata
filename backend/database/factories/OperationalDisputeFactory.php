<?php

namespace Database\Factories;

use App\Models\OperationalDispute;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OperationalDisputeFactory extends Factory
{
    protected $model = OperationalDispute::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'reporter_id' => User::factory(),
            'reason' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => 'open',
            'resolution' => null,
        ];
    }
}
