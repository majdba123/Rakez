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
        $marketingValue = $this->faker->randomFloat(2, 10000, 500000);
        $averageCpm = $this->faker->randomFloat(2, 10, 50);
        $averageCpc = $this->faker->randomFloat(2, 1, 5);
        return [
            'contract_id' => Contract::factory(),
            'average_cpm' => $averageCpm,
            'average_cpc' => $averageCpc,
            'marketing_value' => $marketingValue,
            'marketing_percent' => $this->faker->randomFloat(2, 6, 10),
            'expected_impressions' => (int) ($marketingValue / $averageCpm * 1000),
            'expected_clicks' => (int) ($marketingValue / $averageCpc),
        ];
    }
}
