<?php

namespace App\Services\Marketing;

class MarketingDistributionBreakdownService
{
    /**
     * Calculate SAR amounts for platforms based on marketing value and ensure the sum matches exactly.
     * Uses largest remainder method for adjustment.
     */
    public function breakdownPlatforms(float $marketingValue, array $platformDistribution): array
    {
        return $this->calculateExactDistribution($marketingValue, $platformDistribution);
    }

    /**
     * Calculate SAR amounts for campaigns within a platform based on platform amount.
     */
    public function breakdownCampaigns(float $platformAmount, array $campaignDistribution): array
    {
        return $this->calculateExactDistribution($platformAmount, $campaignDistribution);
    }

    /**
     * Calculate breakdown for all platforms and their respective campaigns
     */
    public function breakdownAll(float $marketingValue, array $platformDistribution, array $campaignDistributionByPlatform): array
    {
        $platformBreakdown = $this->breakdownPlatforms($marketingValue, $platformDistribution);
        
        $campaignAmountsByPlatform = [];
        $campaignAdjustmentsByPlatform = [];
        
        foreach ($platformDistribution as $platform => $percentage) {
            $platformAmount = $platformBreakdown['amounts'][$platform] ?? 0;
            $campaignDistribution = $campaignDistributionByPlatform[$platform] ?? [];
            
            if (empty($campaignDistribution)) {
                $campaignAmountsByPlatform[$platform] = [];
                $campaignAdjustmentsByPlatform[$platform] = [];
                continue;
            }
            
            $campaignBreakdown = $this->breakdownCampaigns($platformAmount, $campaignDistribution);
            $campaignAmountsByPlatform[$platform] = $campaignBreakdown['amounts'];
            $campaignAdjustmentsByPlatform[$platform] = $campaignBreakdown['adjustments'];
        }
        
        return [
            'platform_amounts_sar' => $platformBreakdown['amounts'],
            'platform_adjustments' => $platformBreakdown['adjustments'],
            'campaign_amounts_by_platform_sar' => $campaignAmountsByPlatform,
            'campaign_adjustments_by_platform' => $campaignAdjustmentsByPlatform,
        ];
    }

    /**
     * Core logic for exact distribution of an amount based on percentages.
     * Guarantees sum of outputs equals total amount.
     */
    private function calculateExactDistribution(float $totalAmount, array $percentages): array
    {
        $totalAmount = round($totalAmount); // Ensure we are distributing a whole integer if we want SAR integer
        $amounts = [];
        $exactAmounts = [];
        $remainders = [];
        
        $sumPercentages = array_sum($percentages);
        if ($sumPercentages == 0) {
            foreach ($percentages as $key => $percent) {
                $amounts[$key] = 0;
            }
            return [
                'amounts' => $amounts,
                'adjustments' => [],
            ];
        }

        // Normalize percentages just in case they don't exactly equal 100
        foreach ($percentages as $key => $percent) {
            $normalizedPercent = ($percent / $sumPercentages) * 100;
            $exact = ($totalAmount * $normalizedPercent) / 100;
            $rounded = floor($exact);
            
            $exactAmounts[$key] = $exact;
            $amounts[$key] = $rounded;
            $remainders[$key] = $exact - $rounded;
        }
        
        $sumAmounts = array_sum($amounts);
        $diff = $totalAmount - $sumAmounts;
        
        $adjustments = [];
        
        // Sort keys by largest remainder
        arsort($remainders);
        
        // Distribute the difference 1 by 1
        foreach ($remainders as $key => $remainder) {
            if ($diff <= 0) break;
            $amounts[$key] += 1;
            $adjustments[$key] = 1;
            $diff -= 1;
        }
        
        return [
            'amounts' => $amounts,
            'adjustments' => $adjustments,
        ];
    }
}
