<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\AI\NumericGuardrails;

class CampaignAdvisorTool implements ToolContract
{
    public function __construct(
        private readonly NumericGuardrails $guardrails,
    ) {}

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('marketing.dashboard.view')) {
            return ToolResponse::denied('marketing.dashboard.view');
        }

        $budget = $args['budget'] ?? null;
        $channel = $args['channel'] ?? 'mixed';
        $goal = $args['goal'] ?? 'leads';
        $region = $args['region'] ?? null;
        $projectType = $args['project_type'] ?? null;

        $guardrails = config('ai_guardrails', []);

        // Get platform-specific benchmarks
        $platformKey = "platform_{$channel}";
        $platformBenchmarks = $guardrails[$platformKey] ?? [];

        // Get project type conversion rates
        $conversionRates = $guardrails['project_type_conversion_rates'][$projectType] ?? null;

        // Calculate expected metrics
        $cplRange = $guardrails['cpl']['channels'][$channel] ?? $guardrails['cpl'] ?? ['min' => 15, 'max' => 150];

        $data = [
            'channel' => $channel,
            'goal' => $goal,
            'budget' => $budget,
            'region' => $region,
            'project_type' => $projectType,
            'cpl_range' => $cplRange,
            'platform_benchmarks' => $platformBenchmarks,
        ];

        if ($budget !== null && $budget > 0) {
            $avgCpl = ($cplRange['min'] + $cplRange['max']) / 2;

            // Apply regional multiplier
            if ($region && isset($guardrails['regional_benchmarks'][$region])) {
                $multiplier = $guardrails['regional_benchmarks'][$region]['cpl_multiplier'] ?? 1.0;
                $avgCpl *= $multiplier;
            }

            $expectedLeads = (int) round($budget / $avgCpl);
            $data['estimated_leads'] = $expectedLeads;
            $data['avg_cpl'] = round($avgCpl, 2);

            if ($conversionRates) {
                $avgConversion = ($conversionRates['min'] + $conversionRates['max']) / 200; // /100 for % and /2 for avg
                $data['estimated_conversions'] = (int) round($expectedLeads * $avgConversion);
                $data['conversion_rate_range'] = $conversionRates;
            }

            // Budget distribution recommendation
            if ($channel === 'mixed') {
                $data['recommended_distribution'] = [
                    'google' => round($budget * 0.30, 2),
                    'snapchat' => round($budget * 0.25, 2),
                    'instagram' => round($budget * 0.25, 2),
                    'tiktok' => round($budget * 0.20, 2),
                ];
            }

            // Seasonal adjustment
            $data['seasonal_adjustments'] = $guardrails['seasonal_adjustments'] ?? [];
        }

        // Apply guardrail checks if we have CPL data
        $response = ToolResponse::success('tool_campaign_advisor', $args, $data, [
            ['type' => 'tool', 'title' => 'Campaign Advisor', 'ref' => 'advisor:campaign'],
        ]);

        if (isset($data['avg_cpl'])) {
            $cplCheck = $this->guardrails->validateCPL($data['avg_cpl'], $channel, $region);
            if (! $cplCheck->isOk()) {
                $response = ToolResponse::withGuardrails($response, $cplCheck);
            }
        }

        return $response;
    }
}
