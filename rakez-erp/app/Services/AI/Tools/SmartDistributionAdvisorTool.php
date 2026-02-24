<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\AI\NumericGuardrails;
use App\Services\Marketing\AI\BudgetDistributionOptimizer;
use App\Services\Marketing\AI\CampaignPerformanceAggregator;

class SmartDistributionAdvisorTool implements ToolContract
{
    public function __construct(
        private readonly BudgetDistributionOptimizer $optimizer,
        private readonly CampaignPerformanceAggregator $aggregator,
        private readonly NumericGuardrails $guardrails,
    ) {}

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('marketing.dashboard.view') && ! $user->can('marketing.budgets.manage')) {
            return ToolResponse::denied('marketing.dashboard.view');
        }

        $budget = (float) ($args['budget'] ?? 0);
        $goal = $args['goal'] ?? 'leads';
        $projectType = $args['project_type'] ?? 'on_map';
        $region = $args['region'] ?? 'الرياض';
        $dateStart = $args['date_start'] ?? now()->subDays(30)->toDateString();
        $dateEnd = $args['date_end'] ?? now()->toDateString();

        $inputs = compact('budget', 'goal', 'projectType', 'region', 'dateStart', 'dateEnd');

        $result = $this->optimizer->optimize($budget, $goal, $projectType, $region, $dateStart, $dateEnd);

        $campaignSplits = [];
        foreach ($result['distribution'] as $platform => $pct) {
            $campaignSplits[$platform] = $this->optimizer->campaignTypeSplit($platform, $goal);
        }

        $platformComparison = $this->buildPlatformComparison($dateStart, $dateEnd);

        $response = ToolResponse::success('tool_smart_distribution', $inputs, [
            'distribution' => $result['distribution'],
            'budgets' => $result['budgets'],
            'campaign_type_splits' => $campaignSplits,
            'expected_outcomes' => $result['expected_outcomes'],
            'confidence' => $result['confidence'],
            'data_source' => $result['data_source'],
            'data_days_available' => $result['data_days'],
            'insights' => $result['insights'],
            'risk_flags' => $result['risk_flags'],
            'platform_comparison' => $platformComparison,
        ], [['type' => 'tool', 'title' => 'Smart Distribution Advisor', 'ref' => 'tool_smart_distribution']], [
            'التوزيع مبني على بيانات الحملات الفعلية',
            "مستوى الثقة: {$result['confidence']}",
            "مصدر البيانات: {$result['data_source']}",
        ]);

        foreach ($result['expected_outcomes'] as $platform => $outcome) {
            if (isset($outcome['estimated_cpl']) && $outcome['estimated_cpl'] > 0) {
                $cplCheck = $this->guardrails->validateCPL($outcome['estimated_cpl'], $platform, $region);
                $response = ToolResponse::withGuardrails($response, $cplCheck);
            }
        }

        return $response;
    }

    private function buildPlatformComparison(string $dateStart, string $dateEnd): array
    {
        $platforms = $this->aggregator->byPlatform($dateStart, $dateEnd);
        $comparison = [];

        foreach ($platforms as $summary) {
            $comparison[$summary->platform] = [
                'cpl' => round($summary->cpl, 2),
                'cpc' => round($summary->cpc, 2),
                'ctr' => round($summary->ctr * 100, 2) . '%',
                'roas' => round($summary->roas, 2),
                'total_spend' => round($summary->totalSpend, 2),
                'conversions' => $summary->totalConversions,
            ];
        }

        return $comparison;
    }
}
