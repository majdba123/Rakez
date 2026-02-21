<?php

namespace Database\Factories;

use App\Models\MarketingCampaign;
use App\Models\EmployeeMarketingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingCampaignFactory extends Factory
{
    protected $model = MarketingCampaign::class;

    public function definition(): array
    {
        return [
            'employee_marketing_plan_id' => EmployeeMarketingPlan::factory(),
            'platform' => $this->faker->randomElement(['Facebook', 'Instagram', 'Google', 'TikTok', 'LinkedIn']),
            'campaign_type' => $this->faker->randomElement(['awareness', 'conversion', 'engagement', 'retargeting']),
            'budget' => $this->faker->randomFloat(2, 100, 10000),
            'status' => $this->faker->randomElement(['active', 'paused', 'completed', 'cancelled']),
        ];
    }
}
