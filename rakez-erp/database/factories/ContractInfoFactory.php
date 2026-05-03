<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractInfoFactory extends Factory
{
    protected $model = ContractInfo::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'contract_number' => $this->faker->unique()->bothify('ER-###-####'),
            'first_party_name' => 'Rakez',
            'first_party_email' => 'info@rakez.sa',
            'first_party_phone' => '0500000000',
            'first_party_bank_account_name' => 'Rakez',
            'second_party_name' => $this->faker->name,
            'second_party_email' => $this->faker->email,
            'second_party_phone' => $this->faker->phoneNumber,
            'second_party_bank_account_name' => $this->faker->name,
            'second_party_bank_name' => $this->faker->company,
            'second_party_iban_number' => $this->faker->iban('SA'),
            'agreement_duration_days' => $this->faker->numberBetween(90, 365),
            'agreement_duration_months' => $this->faker->numberBetween(3, 12),
            'avg_property_value' => $this->faker->randomFloat(2, 500000, 2500000),
        ];
    }
}
