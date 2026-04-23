<?php

namespace App\Services\Marketing;

use App\Models\MarketingSetting;

class MarketingPlanningMathService
{
    public function expectedImpressions(float $marketingValue, float $averageCpm): float
    {
        return $averageCpm > 0 ? ($marketingValue / $averageCpm) * 1000 : 0.0;
    }

    public function expectedClicks(float $marketingValue, float $averageCpc): float
    {
        return $averageCpc > 0 ? $marketingValue / $averageCpc : 0.0;
    }

    public function defaultAverageCpm(): float
    {
        $value = MarketingSetting::getByKey('average_cpm')
            ?? MarketingSetting::getByKey('default_cpm')
            ?? 25.00;

        return (float) $value;
    }

    public function defaultAverageCpc(): float
    {
        $value = MarketingSetting::getByKey('average_cpc')
            ?? MarketingSetting::getByKey('default_cpc')
            ?? 2.50;

        return (float) $value;
    }

    /**
     * @param  array<string, float|int>  $platformDistribution
     * @param  array<string, array<string, float|int>>  $campaignDistributionByPlatform
     * @param  array<int, string>  $campaigns
     * @return array<string, float>
     */
    public function weightedCampaignDistribution(
        array $platformDistribution,
        array $campaignDistributionByPlatform,
        array $campaigns
    ): array {
        $derived = [];
        foreach ($campaigns as $campaign) {
            $derived[$campaign] = 0.0;
        }

        foreach ($platformDistribution as $platform => $platformPercent) {
            $platformCampaigns = $campaignDistributionByPlatform[$platform] ?? [];
            foreach ($platformCampaigns as $campaign => $campaignPercent) {
                if (isset($derived[$campaign])) {
                    $derived[$campaign] += ((float) $platformPercent / 100) * (float) $campaignPercent;
                }
            }
        }

        return $derived;
    }
}
