<?php

namespace Database\Factories;

use App\Models\ContractUnit;
use App\Models\SecondPartyData;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractUnitFactory extends Factory
{
    protected $model = ContractUnit::class;

    public function definition(): array
    {
        return [
            'second_party_data_id' => SecondPartyData::factory(),
            'unit_type' => $this->faker->word,
            'unit_number' => $this->faker->bothify('U-###'),
            'price' => $this->faker->randomFloat(2, 100000, 1000000),
            'area' => $this->faker->numberBetween(50, 500),
            'status' => 'available',
            'description' => $this->faker->sentence,
        ];
    }
}
