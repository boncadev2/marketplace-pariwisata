<?php

namespace Database\Factories;

use App\Models\CulinaryPlace;
use App\Models\MealSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MealSlot> */
class MealSlotFactory extends Factory
{
    public function definition(): array
    {
        return ['culinary_place_id' => CulinaryPlace::factory(), 'time_slot' => now()->addWeek(), 'package_name' => 'Paket Makan Demo', 'price' => '75000.25', 'capacity' => 10, 'reserved' => 0, 'is_active' => true];
    }
}
