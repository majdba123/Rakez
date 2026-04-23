<?php

namespace App\Services\Marketing;

use App\Models\EmployeeMarketingPlan;
use App\Models\MarketingProject;
use App\Models\MarketingCampaign;
class EmployeeMarketingPlanService
{
    public function __construct(
        private ContractPricingBasisService $pricingBasisService,
        private MarketingPlanningMathService $planningMathService,
    ) {}

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
        $project = MarketingProject::with(['contract.info', 'contract.contractUnits'])->findOrFail($marketingProjectId);
        $contract = $project->contract;

        $commissionValue = $inputs['commission_value'] ?? null;

        if ($commissionValue === null) {
            $contract->loadMissing(['info', 'contractUnits']);
            $basis = $this->pricingBasisService->resolve($contract, []);
            $projectWideBase = (float) $basis['commission_base_amount'];
            $commissionPercent = $contract->getEffectiveCommissionPercent();
            $commissionValue = $this->calculateCommissionValue($projectWideBase, $commissionPercent);
        }

        $marketingPercent = $inputs['marketing_percent'] ?? 10;
        $marketingValue = $this->calculateMarketingValue((float) $commissionValue, (float) $marketingPercent);

        $platformDistribution = $this->normalizeDistribution(
            $inputs['platform_distribution'] ?? $this->buildEqualDistribution(self::PLATFORMS),
            self::PLATFORMS
        );
        
        $campaignDistributionByPlatform = $this->normalizeCampaignDistributionByPlatform(
            $inputs['campaign_distribution_by_platform'] ?? null
        );

        // Derive campaign_distribution if not provided
        $campaignDistribution = $inputs['campaign_distribution'] ?? null;
        if (!$campaignDistribution) {
            $campaignDistribution = $this->deriveCampaignDistribution($platformDistribution, $campaignDistributionByPlatform);
        } else {
            $campaignDistribution = $this->normalizeDistribution($campaignDistribution, self::CAMPAIGNS);
        }

        $plan = EmployeeMarketingPlan::create([
            'marketing_project_id' => $marketingProjectId,
            'user_id' => $userId,
            'commission_value' => $commissionValue,
            'marketing_percent' => $marketingPercent,
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

        $breakdownService = app(MarketingDistributionBreakdownService::class);
        $breakdown = $breakdownService->breakdownAll($marketingValue, $platformDistribution, $campaignDistributionByPlatform);

        $plan->breakdown = $breakdown;

        return $plan;
    }

    private function deriveCampaignDistribution(array $platformDistribution, array $campaignDistributionByPlatform): array
    {
        return $this->planningMathService->weightedCampaignDistribution(
            $platformDistribution,
            $campaignDistributionByPlatform,
            self::CAMPAIGNS
        );
    }

    /**
     * @param  float  $commissionBaseAmount  Project-wide commission base (typically sum of all unit prices × commission %).
     */
    public function calculateCommissionValue($commissionBaseAmount, $commissionPercent)
    {
        return $commissionBaseAmount * ($commissionPercent / 100);
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

    public function autoGeneratePlan($marketingProjectId, $userId, $marketingPercent = null, $strategy = 'ai')
    {
        $project = MarketingProject::with(['contract.info', 'contract.contractUnits'])->findOrFail($marketingProjectId);
        $contract = $project->contract;
        $contract->loadMissing(['info', 'contractUnits']);
        $basis = $this->pricingBasisService->resolve($contract, []);
        $projectWideBase = (float) $basis['commission_base_amount'];

        $commissionPercent = $contract->getEffectiveCommissionPercent() ?: 2.5;
        $commissionValue = $this->calculateCommissionValue($projectWideBase, $commissionPercent);
        
        $marketingPercent = $marketingPercent ?? 10;
        $marketingValue = $this->calculateMarketingValue($commissionValue, $marketingPercent);

        if ($strategy === 'ai') {
            $suggestionService = app(MarketingPlanSuggestionService::class);
            $suggestion = $suggestionService->suggest([
                'marketing_value' => $marketingValue,
            ]);
            $platformDistribution = $suggestion['platform_distribution'];
            $campaignDistribution = $suggestion['campaign_distribution'];
            $campaignDistributionByPlatform = $suggestion['campaign_distribution_by_platform'];
        } else {
            $platformDistribution = $this->buildEqualDistribution(self::PLATFORMS);
            $campaignDistribution = $this->buildEqualDistribution(self::CAMPAIGNS);
            $campaignDistributionByPlatform = [];
            foreach (self::PLATFORMS as $platform) {
                $campaignDistributionByPlatform[$platform] = $campaignDistribution;
            }
        }

        return $this->createPlan($marketingProjectId, $userId, [
            'commission_value' => $commissionValue,
            'marketing_percent' => $marketingPercent,
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
