<?php

namespace Database\Factories;

use App\Models\MealBooking;
use App\Models\MealSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MealBooking> */
class MealBookingFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'meal_slot_id' => MealSlot::factory(), 'quantity' => 1, 'unit_price' => '75000.25', 'total_price' => '75000.25', 'package_name' => 'Paket Makan Demo', 'time_slot' => now()->addWeek(), 'status' => 'pending_payment', 'idempotency_key' => (string) Str::uuid()];
    }
}
