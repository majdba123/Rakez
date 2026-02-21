<?php

namespace Database\Factories;

use App\Models\DeveloperMarketingPlan;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeveloperMarketingPlanFactory extends Factory
{
    protected $model = DeveloperMarketingPlan::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'average_cpm' => $this->faker->randomFloat(2, 5, 50),
            'average_cpc' => $this->faker->randomFloat(2, 0.5, 5),
            'marketing_value' => $this->faker->randomFloat(2, 100000, 1000000),
            'expected_impressions' => $this->faker->numberBetween(10000, 1000000),
            'expected_clicks' => $this->faker->numberBetween(100, 10000),
        ];
    }
}
