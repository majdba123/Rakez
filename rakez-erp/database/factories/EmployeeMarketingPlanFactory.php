<?php

namespace Database\Factories;

use App\Models\EmployeeMarketingPlan;
use App\Models\MarketingProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeMarketingPlanFactory extends Factory
{
    protected $model = EmployeeMarketingPlan::class;

    public function definition(): array
    {
        return [
            'marketing_project_id' => MarketingProject::factory(),
            'user_id' => User::factory(),
            'commission_value' => $this->faker->randomFloat(2, 1000, 50000),
            'marketing_value' => $this->faker->randomFloat(2, 10000, 100000),
            'platform_distribution' => null,
            'campaign_distribution' => null,
            'campaign_distribution_by_platform' => null,
        ];
    }
}
