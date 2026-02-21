<?php

namespace App\Services\Marketing;

use App\Models\DeveloperMarketingPlan;
use App\Models\Contract;
use App\Models\MarketingSetting;

class DeveloperMarketingPlanService
{
    public function createOrUpdatePlan($contractId, $data)
    {
        $marketingValue = $data['marketing_value'] ?? 0;
        $averageCpm = $data['average_cpm'] ?? $this->getDefaultAverageCpm();
        $averageCpc = $data['average_cpc'] ?? $this->getDefaultAverageCpc();

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
        $durationDays = $contract->info->agreement_duration_days ?? 0;

        return [
            'total_budget' => number_format($plan->marketing_value, 0, '.', ','),
            'expected_impressions' => 'Approximately ' . number_format($plan->expected_impressions, 0, '.', ',') . ' impressions',
            'expected_clicks' => 'Approximately ' . number_format($plan->expected_clicks, 0, '.', ',') . ' clicks',
            'marketing_duration' => $durationDays > 0 ? "According to contract duration ({$durationDays} days)" : 'According to contract duration',
            'raw_plan' => $plan
        ];
    }

    /**
     * Get system-wide average CPM from settings.
     */
    public function getDefaultAverageCpm(): float
    {
        $value = MarketingSetting::getByKey('average_cpm') 
            ?? MarketingSetting::getByKey('default_cpm')
            ?? 25.00;
        return (float) $value;
    }

    /**
     * Get system-wide average CPC from settings.
     */
    public function getDefaultAverageCpc(): float
    {
        $value = MarketingSetting::getByKey('average_cpc')
            ?? MarketingSetting::getByKey('default_cpc')
            ?? 2.50;
        return (float) $value;
    }
}
