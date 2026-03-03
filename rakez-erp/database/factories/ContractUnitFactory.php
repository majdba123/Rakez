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
        $area = $this->faker->numberBetween(80, 450);
        $privateArea = $this->faker->numberBetween(5, 25);
        $bedrooms = $this->faker->numberBetween(1, 6);
        $bathrooms = min($bedrooms + 1, $this->faker->numberBetween(1, 4));

        return [
            'second_party_data_id' => SecondPartyData::factory(),
            'unit_type' => $this->faker->randomElement(['أدوار', 'بنتهاوس', 'تاون هاوس', 'شقق', 'فيلا']),
            'unit_number' => $this->faker->bothify('U-###'),
            'price' => $this->faker->randomFloat(2, 300000, 2500000),
            'area' => (string) $area,
            'floor' => (string) $this->faker->numberBetween(0, 15),
            'bedrooms' => $bedrooms,
            'bathrooms' => $bathrooms,
            'private_area_m2' => $privateArea,
            'total_area_m2' => $area + $privateArea,
            'facade' => $this->faker->randomElement(['شمال', 'جنوب', 'شرق', 'غرب', 'شمال شرق', 'شمال غرب']),
            'status' => 'available',
            'description' => 'وحدة سكنية بتشطيبات جيدة ومساحة مناسبة.',
        ];
    }
}
