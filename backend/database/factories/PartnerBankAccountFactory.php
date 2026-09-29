<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartnerBankAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'bank_name' => 'BCA',
            'account_number' => $this->faker->bankAccountNumber,
            'account_name' => $this->faker->name,
            'is_verified' => false,
        ];
    }
}
