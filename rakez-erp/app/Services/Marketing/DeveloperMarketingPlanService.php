<?php

namespace App\Services\Marketing;

use App\Models\DeveloperMarketingPlan;
use App\Models\Contract;
use App\Models\MarketingSetting;

class DeveloperMarketingPlanService
{
    public function __construct(
        private ContractPricingBasisService $pricingBasisService
    ) {}

    public function createOrUpdatePlan($contractId, $data)
    {
        $contract = Contract::findOrFail($contractId);
        app(MarketingProjectBootstrapService::class)->ensureForCompletedContract($contract);

        $marketingValue = isset($data['marketing_value']) ? (float) $data['marketing_value'] : 0;
        $marketingPercent = isset($data['marketing_percent']) ? (float) $data['marketing_percent'] : null;
        $averageCpm = $data['average_cpm'] ?? $this->getDefaultAverageCpm();
        $averageCpc = $data['average_cpc'] ?? $this->getDefaultAverageCpc();

        $expectedImpressions = $this->calculateExpectedImpressions($marketingValue, $averageCpm);
        $expectedClicks = $this->calculateExpectedClicks($marketingValue, $averageCpc);

        $payload = [
            'average_cpm' => $averageCpm,
            'average_cpc' => $averageCpc,
            'marketing_value' => $marketingValue,
            'expected_impressions' => $expectedImpressions,
            'expected_clicks' => $expectedClicks,
        ];
        if ($marketingPercent !== null) {
            $payload['marketing_percent'] = $marketingPercent;
        }

        if (isset($data['platforms']) && is_array($data['platforms'])) {
            $payload['platforms'] = $this->normalizePlatforms($data['platforms']);
        }

        return DeveloperMarketingPlan::updateOrCreate(
            ['contract_id' => $contractId],
            $payload
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

    /**
     * Contract formulas (UI): total commission = sum(all unit prices)×commission%; marketing money = commission×marketing%.
     *
     * @return array<string, float|int>
     */
    public function buildCalculatedContractBudget(Contract $contract, array $pricingBasis, float $marketingPercent): array
    {
        $commissionPercent = (float) $contract->getEffectiveCommissionPercent();
        $base = (float) $pricingBasis['commission_base_amount'];
        $commissionValue = round($base * ($commissionPercent / 100), 2);
        $marketingValue = round($commissionValue * ($marketingPercent / 100), 2);

        return [
            'total_unit_price_all_sum' => (float) ($pricingBasis['total_unit_price_all_sum'] ?? 0),
            'all_units_count' => (int) ($pricingBasis['all_units_count'] ?? 0),
            'average_unit_price_all' => (float) ($pricingBasis['average_unit_price_all'] ?? 0),
            'commission_percent' => $commissionPercent,
            'commission_value' => $commissionValue,
            'commission_value_total' => $commissionValue,
            'marketing_percent' => $marketingPercent,
            'marketing_value' => $marketingValue,
        ];
    }

    /**
     * Developer plan payload for API / PDF. Numeric money/count fields are JSON numbers; human copy is *_display_*.
     *
     * `total_budget` / `total_budget_display` follow the **calculated** marketing money (قيمة التسويق المحسوبة).
     * `stored_plan_financials` holds persisted DB values; `plan.marketing_value` remains the saved campaign budget.
     *
     * @return array<string, mixed>
     */
    public function getPlanForDeveloper($contractId): array
    {
        $contract = Contract::with(['info', 'contractUnits'])->findOrFail($contractId);
        app(MarketingProjectBootstrapService::class)->ensureForCompletedContract($contract);
        $info = $contract->info;

        $commissionPercent = (float) $contract->getEffectiveCommissionPercent();
        $pricingBasis = $this->pricingBasisService->resolve($contract, []);

        $contractData = [
            'commission_percent' => $commissionPercent,
            'pricing_basis' => $pricingBasis,
            /** Full-project average unit price (متوسط سعر الوحدات للمشروع) — same as pricing_basis.average_unit_price_all */
            'average_unit_price' => (float) $pricingBasis['average_unit_price_all'],
            /** Same as pricing_basis.commission_base_amount / total_unit_price — full project sum */
            'total_unit_price' => (float) $pricingBasis[ContractPricingBasisService::COMMISSION_BASE_KEY],
        ];

        $defaultMarketingPercent = 10.0;

        $plan = DeveloperMarketingPlan::where('contract_id', $contractId)->first();
        if (!$plan) {
            $calculated = $this->buildCalculatedContractBudget($contract, $pricingBasis, $defaultMarketingPercent);

            return [
                'contract' => $contractData,
                'plan' => null,
                'calculated_contract_budget' => $calculated,
                'stored_plan_financials' => null,
                'total_budget' => $calculated['marketing_value'],
                'total_budget_display' => number_format($calculated['marketing_value'], 2, '.', ','),
                'stored_marketing_value' => null,
                'stored_marketing_value_display' => null,
                'expected_impressions' => null,
                'expected_clicks' => null,
                'expected_impressions_display_en' => null,
                'expected_impressions_display_ar' => null,
                'expected_clicks_display_en' => null,
                'expected_clicks_display_ar' => null,
                'marketing_duration' => null,
                'marketing_duration_ar' => null,
                'platforms' => [],
            ];
        }

        $mktPct = $plan->marketing_percent !== null ? (float) $plan->marketing_percent : $defaultMarketingPercent;
        $calculatedContractBudget = $this->buildCalculatedContractBudget($contract, $pricingBasis, $mktPct);
        $planPayload = $this->serializeDeveloperPlan($plan);

        $durationDays = (int) ($info->agreement_duration_days ?? 0);
        $impressionsFormatted = number_format($plan->expected_impressions, 0, '.', ',');
        $clicksFormatted = number_format($plan->expected_clicks, 0, '.', ',');
        $durationTextEn = $durationDays > 0 ? "According to contract duration ({$durationDays} days)" : 'According to contract duration';
        $durationTextAr = $durationDays > 0 ? "حسب مدة العقد ({$durationDays} يوم)" : 'حسب مدة العقد';

        $storedMv = (float) $plan->marketing_value;
        $calcMv = $calculatedContractBudget['marketing_value'];

        return [
            'contract' => $contractData,
            'plan' => $planPayload,
            'calculated_contract_budget' => $calculatedContractBudget,
            'stored_plan_financials' => [
                'marketing_value_stored' => $storedMv,
                'marketing_percent_saved' => $plan->marketing_percent !== null ? (float) $plan->marketing_percent : null,
                /** True when persisted budget differs from formula (rounding or manual edit). */
                'stored_differs_from_calculated' => abs($storedMv - $calcMv) > 0.01,
            ],
            /** Primary UI campaign budget = formula result (قيمة التسويق المحسوبة). */
            'total_budget' => round($calcMv, 2),
            'total_budget_display' => number_format($calcMv, 2, '.', ','),
            'stored_marketing_value' => round($storedMv, 2),
            'stored_marketing_value_display' => number_format($storedMv, 2, '.', ','),
            'expected_impressions' => (int) $plan->expected_impressions,
            'expected_clicks' => (int) $plan->expected_clicks,
            'expected_impressions_display_en' => 'Approximately ' . $impressionsFormatted . ' impressions',
            'expected_impressions_display_ar' => 'تقريباً ' . $impressionsFormatted . ' ظهور',
            'expected_clicks_display_en' => 'Approximately ' . $clicksFormatted . ' clicks',
            'expected_clicks_display_ar' => 'تقريباً ' . $clicksFormatted . ' نقرة',
            'marketing_duration' => $durationTextEn,
            'marketing_duration_ar' => $durationTextAr,
            'platforms' => $planPayload['platforms'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeDeveloperPlan(DeveloperMarketingPlan $plan): array
    {
        $platforms = $plan->platforms;
        if (! is_array($platforms)) {
            $platforms = [];
        }

        return [
            'id' => (int) $plan->id,
            'contract_id' => (int) $plan->contract_id,
            'average_cpm' => (float) $plan->average_cpm,
            'average_cpc' => (float) $plan->average_cpc,
            /** Persisted campaign budget at last save (may differ from `calculated_contract_budget.marketing_value`). */
            'marketing_value' => (float) $plan->marketing_value,
            'marketing_value_stored' => (float) $plan->marketing_value,
            'marketing_percent' => $plan->marketing_percent !== null ? (float) $plan->marketing_percent : null,
            'direct_contact_percent' => $plan->direct_contact_percent !== null ? (float) $plan->direct_contact_percent : null,
            'expected_impressions' => (int) $plan->expected_impressions,
            'expected_clicks' => (int) $plan->expected_clicks,
            'platforms' => $platforms,
        ];
    }

    /**
     * Normalize platforms array from request: ensure each item has platform_key, cpm, cpc, views, clicks.
     */
    protected function normalizePlatforms(array $platforms): array
    {
        return array_values(array_map(function ($p) {
            return [
                'platform_key' => $p['platform_key'] ?? $p['platform'] ?? '',
                'platform_name_ar' => $p['platform_name_ar'] ?? $p['platform_name'] ?? '',
                'cpm' => isset($p['cpm']) ? (float) $p['cpm'] : null,
                'cpc' => isset($p['cpc']) ? (float) $p['cpc'] : null,
                'views' => isset($p['views']) ? (int) $p['views'] : null,
                'clicks' => isset($p['clicks']) ? (int) $p['clicks'] : null,
            ];
        }, $platforms));
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
