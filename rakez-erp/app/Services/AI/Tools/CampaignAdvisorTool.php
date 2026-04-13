<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\AI\NumericGuardrails;

/**
 * Planning helper using repository-backed guardrail config only — not live ad performance.
 */
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

        $platformKey = "platform_{$channel}";
        $platformBenchmarks = $guardrails[$platformKey] ?? [];

        $conversionRates = $guardrails['project_type_conversion_rates'][$projectType] ?? null;

        $cplRange = $guardrails['cpl']['channels'][$channel] ?? $guardrails['cpl'] ?? ['min' => 15, 'max' => 150];

        $data = [
            'tool_kind' => 'guardrail_planning',
            'channel' => $channel,
            'goal' => $goal,
            'budget' => $budget,
            'region' => $region,
            'project_type' => $projectType,
            'cpl_range' => $cplRange,
            'platform_benchmarks' => $platformBenchmarks,
            'warnings' => [
                'Figures come from config/ai_guardrails (or defaults), not from synced ad accounts.',
                'No automatic channel budget split is provided; mixed channel requires explicit planning outside this tool.',
            ],
            'assumptions' => [
                'Estimated leads use midpoint CPL from cpl_range after optional regional multiplier.',
            ],
        ];

        if ($budget !== null && $budget > 0) {
            $avgCpl = ($cplRange['min'] + $cplRange['max']) / 2;

            if ($region && isset($guardrails['regional_benchmarks'][$region])) {
                $multiplier = $guardrails['regional_benchmarks'][$region]['cpl_multiplier'] ?? 1.0;
                $avgCpl *= $multiplier;
            }

            $expectedLeads = (int) round($budget / $avgCpl);
            $data['estimated_leads'] = $expectedLeads;
            $data['avg_cpl_used'] = round($avgCpl, 2);

            if ($conversionRates) {
                $avgConversion = ($conversionRates['min'] + $conversionRates['max']) / 200;
                $data['estimated_conversions'] = (int) round($expectedLeads * $avgConversion);
                $data['conversion_rate_range'] = $conversionRates;
            }

            $data['seasonal_adjustments'] = $guardrails['seasonal_adjustments'] ?? [];
        }

        $response = ToolResponse::success('tool_campaign_advisor', $args, $data, [
            ['type' => 'tool', 'title' => 'Campaign planning (guardrails)', 'ref' => 'advisor:campaign:guardrails'],
        ], [], 'config');

        if (isset($data['avg_cpl_used'])) {
            $cplCheck = $this->guardrails->validateCPL($data['avg_cpl_used'], $channel, $region);
            if (! $cplCheck->isOk()) {
                $response = ToolResponse::withGuardrails($response, $cplCheck);
            }
        }

        return $response;
    }
}
