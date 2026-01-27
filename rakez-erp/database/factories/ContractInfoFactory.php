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
            'second_party_name' => $this->faker->name,
            'second_party_email' => $this->faker->email,
            'second_party_phone' => $this->faker->phoneNumber,
        ];
    }
}
