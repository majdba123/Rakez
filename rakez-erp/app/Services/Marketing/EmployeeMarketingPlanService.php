<?php

namespace App\Services\Marketing;

use App\Models\EmployeeMarketingPlan;
use App\Models\MarketingProject;
use App\Models\MarketingCampaign;
use App\Models\ContractUnit;
use App\Models\ContractInfo;

class EmployeeMarketingPlanService
{
    public const PLATFORMS = [
        'TikTok',
        'Meta',
        'Snapchat',
        'YouTube',
        'LinkedIn',
        'X',
    ];

    public const CAMPAIGNS = [
        'Direct Communication',
        'Hand Raise',
        'Impression',
        'Sales',
    ];

    public function createPlan($marketingProjectId, $userId, $inputs)
    {
        $commissionValue = $inputs['commission_value'] ?? 0;
        $marketingValue = $inputs['marketing_value'] ?? 0;

        $platformDistribution = $this->normalizeDistribution(
            $inputs['platform_distribution'] ?? $this->buildEqualDistribution(self::PLATFORMS),
            self::PLATFORMS
        );
        $campaignDistribution = $this->normalizeDistribution(
            $inputs['campaign_distribution'] ?? $this->buildEqualDistribution(self::CAMPAIGNS),
            self::CAMPAIGNS
        );
        $campaignDistributionByPlatform = $this->normalizeCampaignDistributionByPlatform(
            $inputs['campaign_distribution_by_platform'] ?? null
        );

        $plan = EmployeeMarketingPlan::create([
            'marketing_project_id' => $marketingProjectId,
            'user_id' => $userId,
            'commission_value' => $commissionValue,
            'marketing_value' => $marketingValue,
            'platform_distribution' => $platformDistribution,
            'campaign_distribution' => $campaignDistribution,
            'campaign_distribution_by_platform' => $campaignDistributionByPlatform,
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

        $platformDistribution = $this->buildEqualDistribution(self::PLATFORMS);
        $campaignDistribution = $this->buildEqualDistribution(self::CAMPAIGNS);
        $campaignDistributionByPlatform = [];
        foreach (self::PLATFORMS as $platform) {
            $campaignDistributionByPlatform[$platform] = $campaignDistribution;
        }

        return $this->createPlan($marketingProjectId, $userId, [
            'commission_value' => $commissionValue,
            'marketing_value' => $marketingValue,
            'platform_distribution' => $platformDistribution,
            'campaign_distribution' => $campaignDistribution,
            'campaign_distribution_by_platform' => $campaignDistributionByPlatform,
        ]);
    }

    private function buildEqualDistribution(array $keys): array
    {
        $count = count($keys);
        if ($count === 0) return [];

        $base = floor((100 / $count) * 100) / 100;
        $distribution = [];
        $total = 0.0;

        foreach ($keys as $index => $key) {
            $distribution[$key] = $index === $count - 1 ? round(100 - $total, 2) : $base;
            $total += $distribution[$key];
        }

        return $distribution;
    }

    private function normalizeDistribution(array $input, array $allowed): array
    {
        $normalized = [];
        foreach ($allowed as $key) {
            $normalized[$key] = isset($input[$key]) ? (float) $input[$key] : 0.0;
        }
        return $normalized;
    }

    private function normalizeCampaignDistributionByPlatform(?array $input): array
    {
        $distribution = [];
        $defaultCampaignDistribution = $this->buildEqualDistribution(self::CAMPAIGNS);

        foreach (self::PLATFORMS as $platform) {
            $distribution[$platform] = $this->normalizeDistribution(
                $input[$platform] ?? $defaultCampaignDistribution,
                self::CAMPAIGNS
            );
        }

        return $distribution;
    }
}
