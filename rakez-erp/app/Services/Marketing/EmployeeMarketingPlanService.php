<?php

namespace App\Services\Marketing;

use App\Models\EmployeeMarketingPlan;
use App\Models\MarketingProject;
use App\Models\MarketingCampaign;
use App\Models\ContractUnit;
use App\Models\ContractInfo;

class EmployeeMarketingPlanService
{
    public function createPlan($marketingProjectId, $userId, $inputs)
    {
        $commissionValue = $inputs['commission_value'] ?? 0;
        $marketingValue = $inputs['marketing_value'] ?? 0;
        
        $plan = EmployeeMarketingPlan::create([
            'marketing_project_id' => $marketingProjectId,
            'user_id' => $userId,
            'commission_value' => $commissionValue,
            'marketing_value' => $marketingValue,
            'platform_distribution' => $inputs['platform_distribution'] ?? [],
            'campaign_distribution' => $inputs['campaign_distribution'] ?? [],
        ]);

        if (isset($inputs['campaigns'])) {
            foreach ($inputs['campaigns'] as $campaignData) {
                MarketingCampaign::create(array_merge($campaignData, [
                    'employee_marketing_plan_id' => $plan->id
                ]));
            }
        }

        return $plan;
    }

    public function calculateCommissionValue($availableUnitsValue, $commissionPercent)
    {
        return $availableUnitsValue * ($commissionPercent / 100);
    }

    public function calculateMarketingValue($commissionValue, $marketingPercent)
    {
        return $commissionValue * ($marketingPercent / 100);
    }

    public function distributeBudgetAcrossPlatforms($marketingValue, $platformPercentages)
    {
        $distribution = [];
        foreach ($platformPercentages as $platform => $percentage) {
            $distribution[$platform] = $marketingValue * ($percentage / 100);
        }
        return $distribution;
    }

    public function autoGeneratePlan($marketingProjectId, $userId)
    {
        $project = MarketingProject::with('contract.info')->findOrFail($marketingProjectId);
        $contract = $project->contract;
        
        // Use ContractUnit status 'available' to calculate potential commission
        $availableUnitsValue = ContractUnit::where('second_party_data_id', $contract->secondPartyData->id ?? 0)
            ->where('status', 'available')
            ->sum('price');
            
        $commissionPercent = $contract->info->commission_percent ?? 2.5;
        $commissionValue = $this->calculateCommissionValue($availableUnitsValue, $commissionPercent);
        
        // Default marketing percent 10% of commission
        $marketingPercent = 10;
        $marketingValue = $this->calculateMarketingValue($commissionValue, $marketingPercent);

        // Default platform distribution percentages
        $platformDistribution = [
            'Snapchat' => 40,
            'Instagram' => 30,
            'TikTok' => 30
        ];

        // Default campaign distribution percentages
        $campaignDistribution = [
            'Awareness' => 30,
            'Lead Generation' => 50,
            'Conversion' => 20
        ];

        return $this->createPlan($marketingProjectId, $userId, [
            'commission_value' => $commissionValue,
            'marketing_value' => $marketingValue,
            'platform_distribution' => $platformDistribution,
            'campaign_distribution' => $campaignDistribution
        ]);
    }
}
