<?php

namespace App\Services\Marketing;

use App\Models\DeveloperMarketingPlan;
use App\Models\Contract;

class DeveloperMarketingPlanService
{
    public function createOrUpdatePlan($contractId, $data)
    {
        $marketingValue = $data['marketing_value'] ?? 0;
        $averageCpm = $data['average_cpm'] ?? 0;
        $averageCpc = $data['average_cpc'] ?? 0;

        $expectedImpressions = $this->calculateExpectedImpressions($marketingValue, $averageCpm);
        $expectedClicks = $this->calculateExpectedClicks($marketingValue, $averageCpc);

        return DeveloperMarketingPlan::updateOrCreate(
            ['contract_id' => $contractId],
            [
                'average_cpm' => $averageCpm,
                'average_cpc' => $averageCpc,
                'marketing_value' => $marketingValue,
                'expected_impressions' => $expectedImpressions,
                'expected_clicks' => $expectedClicks,
            ]
        );
    }

    public function calculateExpectedImpressions($marketingValue, $averageCpm)
    {
        return $averageCpm > 0 ? ($marketingValue / $averageCpm) * 1000 : 0;
    }

    public function calculateExpectedClicks($marketingValue, $averageCpc)
    {
        return $averageCpc > 0 ? ($marketingValue / $averageCpc) : 0;
    }

    public function getPlanForDeveloper($contractId)
    {
        $plan = DeveloperMarketingPlan::where('contract_id', $contractId)->first();
        if (!$plan) return null;

        $contract = Contract::with('info')->findOrFail($contractId);

        return [
            'total_budget' => $plan->marketing_value,
            'expected_impressions' => $plan->expected_impressions,
            'expected_clicks' => $plan->expected_clicks,
            'marketing_duration' => ($contract->info->agreement_duration_days ?? 0) . ' days',
            'raw_plan' => $plan
        ];
    }
}
