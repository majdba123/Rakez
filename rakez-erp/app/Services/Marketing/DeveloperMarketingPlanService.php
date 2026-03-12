<?php

namespace App\Services\Marketing;

use App\Models\DeveloperMarketingPlan;
use App\Models\Contract;
use App\Models\MarketingSetting;

class DeveloperMarketingPlanService
{
    public function createOrUpdatePlan($contractId, $data)
    {
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

    public function getPlanForDeveloper($contractId)
    {
        $contract = Contract::with('info')->findOrFail($contractId);
        $info = $contract->info;

        // نسبة السعي من العقد: تفاصيل العقد (contract_infos) أولاً ثم جدول العقود
        $commissionPercent = $contract->getEffectiveCommissionPercent();
        $averageUnitPrice = $info ? (float) ($info->avg_property_value ?? 0) : 0;

        $contractData = [
            'commission_percent' => $commissionPercent,
            'average_unit_price' => $averageUnitPrice,
        ];

        $plan = DeveloperMarketingPlan::where('contract_id', $contractId)->first();
        if (!$plan) {
            return [
                'contract' => $contractData,
                'plan' => null,
                'total_budget' => null,
                'expected_impressions' => null,
                'expected_clicks' => null,
                'marketing_duration' => null,
                'marketing_duration_ar' => null,
                'expected_impressions_ar' => null,
                'expected_clicks_ar' => null,
                'raw_plan' => null,
            ];
        }

        $durationDays = $info->agreement_duration_days ?? 0;
        $impressionsFormatted = number_format($plan->expected_impressions, 0, '.', ',');
        $clicksFormatted = number_format($plan->expected_clicks, 0, '.', ',');
        $durationTextEn = $durationDays > 0 ? "According to contract duration ({$durationDays} days)" : 'According to contract duration';
        $durationTextAr = $durationDays > 0 ? "حسب مدة العقد ({$durationDays} يوم)" : 'حسب مدة العقد';

        return [
            'contract' => $contractData,
            'plan' => $plan,
            'total_budget' => number_format($plan->marketing_value, 0, '.', ','),
            'expected_impressions' => 'Approximately ' . $impressionsFormatted . ' impressions',
            'expected_clicks' => 'Approximately ' . $clicksFormatted . ' clicks',
            'marketing_duration' => $durationTextEn,
            'expected_impressions_ar' => 'تقريباً ' . $impressionsFormatted . ' ظهور',
            'expected_clicks_ar' => 'تقريباً ' . $clicksFormatted . ' نقرة',
            'marketing_duration_ar' => $durationTextAr,
            'raw_plan' => $plan,
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
