<?php

namespace App\Services\Marketing;

use App\Enums\ContractWorkflowStatus;
use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\MarketingSetting;

/**
 * Single canonical implementation for developer-plan budget math (commission → marketing → reach).
 *
 * Used by {@see DeveloperMarketingPlanController::calculateBudget} and composed into developer plan show payloads.
 * Do not duplicate these formulas elsewhere.
 */
class MarketingBudgetCalculationService
{
    public function __construct(
        private ContractPricingBasisService $pricingBasisService,
        private MarketingProjectBootstrapService $bootstrapService,
    ) {}

    /**
     * Preview / calculate: full-project commission base → commission → marketing money → daily/monthly → impressions/clicks.
     *
     * @param  array<string, mixed>  $inputs  marketing_percent (default 10), average_cpm, average_cpc, total_unit_price_override, unit_price (deprecated)
     * @return array<string, mixed>
     */
    public function calculateCampaignBudget(int $contractId, array $inputs): array
    {
        $contract = Contract::with(['info', 'contractUnits'])->findOrFail($contractId);

        if ($contract->status === ContractWorkflowStatus::Completed->value) {
            $this->bootstrapService->ensureForCompletedContract($contract);
        }

        $info = $contract->info;

        $pricingBasis = $this->pricingBasisService->resolve($contract, $inputs);
        $commissionBase = (float) $pricingBasis['commission_base_amount'];
        $commissionPercent = $contract->getEffectiveCommissionPercent();

        $commissionValue = round($commissionBase * ($commissionPercent / 100), 2);

        $marketingPercent = isset($inputs['marketing_percent']) ? (float) $inputs['marketing_percent'] : 10.0;
        $marketingValue = round($commissionValue * ($marketingPercent / 100), 2);

        $durationDays = (int) ($info->agreement_duration_days ?? 30);
        $durationMonths = $this->resolveDurationMonths($info, $durationDays);

        $averageCpm = isset($inputs['average_cpm']) ? (float) $inputs['average_cpm'] : $this->getDefaultAverageCpm();
        $averageCpc = isset($inputs['average_cpc']) ? (float) $inputs['average_cpc'] : $this->getDefaultAverageCpc();

        $expectedImpressions = (int) round($this->expectedImpressions($marketingValue, $averageCpm));
        $expectedClicks = (int) round($this->expectedClicks($marketingValue, $averageCpc));

        $calculatedContractBudget = [
            'total_unit_price_available_sum' => (float) ($pricingBasis['total_unit_price_available_sum'] ?? 0),
            'available_units_count' => (int) ($pricingBasis['available_units_count'] ?? 0),
            'average_unit_price_available' => (float) ($pricingBasis['average_unit_price_available'] ?? 0),
            'commission_percent' => (float) $commissionPercent,
            'commission_value' => $commissionValue,
            'commission_value_total' => $commissionValue,
            'marketing_percent' => $marketingPercent,
            'marketing_value' => $marketingValue,
        ];

        return [
            'commission_percent' => (float) $commissionPercent,
            'commission_value' => $commissionValue,
            'commission_value_total' => $commissionValue,
            'marketing_percent' => $marketingPercent,
            'marketing_value' => $marketingValue,
            'daily_budget' => round($this->dailyBudget($marketingValue, $durationDays), 2),
            'monthly_budget' => round($this->monthlyBudget($marketingValue, $durationMonths), 2),
            'expected_impressions' => $expectedImpressions,
            'expected_clicks' => $expectedClicks,
            'average_cpm' => $averageCpm,
            'average_cpc' => $averageCpc,
            'pricing_basis' => $pricingBasis,
            'calculated_contract_budget' => $calculatedContractBudget,
        ];
    }

    /**
     * Commission only (no marketing %) — for project source / GET marketing/projects/{id}.
     *
     * @return array<string, mixed>
     */
    public function commissionValueFromPricingBasis(Contract $contract, array $pricingBasis): float
    {
        $base = (float) $pricingBasis['commission_base_amount'];
        $commissionPercent = (float) $contract->getEffectiveCommissionPercent();

        return round($base * ($commissionPercent / 100), 2);
    }

    private function dailyBudget(float $marketingValue, int $durationDays): float
    {
        return $durationDays > 0 ? $marketingValue / $durationDays : 0.0;
    }

    private function monthlyBudget(float $marketingValue, int $durationMonths): float
    {
        return $durationMonths > 0 ? $marketingValue / $durationMonths : 0.0;
    }

    private function expectedImpressions(float $marketingValue, float $averageCpm): float
    {
        return $averageCpm > 0 ? ($marketingValue / $averageCpm) * 1000 : 0.0;
    }

    private function expectedClicks(float $marketingValue, float $averageCpc): float
    {
        return $averageCpc > 0 ? ($marketingValue / $averageCpc) : 0.0;
    }

    private function resolveDurationMonths(?ContractInfo $info, int $durationDays): int
    {
        if ($info && !empty($info->agreement_duration_months)) {
            return max(1, (int) $info->agreement_duration_months);
        }

        if ($durationDays <= 0) {
            return 1;
        }

        return (int) ceil($durationDays / 30);
    }

    private function getDefaultAverageCpm(): float
    {
        $value = MarketingSetting::getByKey('average_cpm')
            ?? MarketingSetting::getByKey('default_cpm')
            ?? 25.00;

        return (float) $value;
    }

    private function getDefaultAverageCpc(): float
    {
        $value = MarketingSetting::getByKey('average_cpc')
            ?? MarketingSetting::getByKey('default_cpc')
            ?? 2.50;

        return (float) $value;
    }
}
