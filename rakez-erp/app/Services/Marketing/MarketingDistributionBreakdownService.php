<?php

namespace App\Services\Marketing;

use App\Models\MarketingSetting;

class MarketingDistributionBreakdownService
{
    /** Arabic labels for platforms (printable distribution document). */
    public const PLATFORM_NAMES_AR = [
        'Meta'       => 'منصة انستغرام',
        'Instagram'  => 'منصة انستغرام',
        'Snapchat'   => 'منصة سناب',
        'TikTok'     => 'منصة تيك توك',
        'X'          => 'منصة تويتر X',
        'Twitter'    => 'منصة تويتر X',
        'YouTube'    => 'منصة جوجل (تضمن يوتيوب)',
        'Google'     => 'منصة جوجل (تضمن يوتيوب)',
        'LinkedIn'   => 'منصة لينكد إن',
        'Other'      => 'منصات اخرى (بيوت - سكني - حراج ....)',
        'Aqar'       => 'منصة عقار',
    ];

    /** Normalize platform key for aggregation (e.g. Instagram → Meta). Keys are matched case-insensitively. */
    private const CANONICAL_PLATFORM = [
        'instagram' => 'Meta',
        'twitter'   => 'X',
        'google'    => 'YouTube',
        'snap'      => 'Snapchat',
    ];

    /**
     * Build rows for the printable distribution table: platform (Arabic), clicks, impressions.
     * Uses platform budget share and average CPM/CPC to compute impressions and clicks per platform.
     *
     * @param float $marketingValue Total marketing value (SAR)
     * @param array $platformDistribution Platform key => percentage (e.g. ['Meta' => 25, 'TikTok' => 20])
     * @param float|null $averageCpm Optional CPM (default from settings)
     * @param float|null $averageCpc Optional CPC (default from settings)
     * @return array{rows: array<int, array{index: int, platform_ar: string, clicks: int, impressions: int}>, total_clicks: int, total_impressions: int}
     */
    public function buildPrintableDistributionTable(
        float $marketingValue,
        array $platformDistribution,
        ?float $averageCpm = null,
        ?float $averageCpc = null
    ): array {
        $averageCpm = $averageCpm ?? (float) (MarketingSetting::getByKey('default_cpm') ?? MarketingSetting::getByKey('average_cpm') ?? 25);
        $averageCpc = $averageCpc ?? (float) (MarketingSetting::getByKey('default_cpc') ?? MarketingSetting::getByKey('average_cpc') ?? 2.5);

        $breakdown = $this->breakdownPlatforms($marketingValue, $platformDistribution);
        $amounts = $breakdown['amounts'] ?? [];

        $rows = [];
        $totalClicks = 0;
        $totalImpressions = 0;
        $index = 1;

        foreach ($amounts as $platformKey => $amountSar) {
            $amountSar = (float) $amountSar;
            $impressions = $averageCpm > 0 ? (int) round(($amountSar / $averageCpm) * 1000) : 0;
            $clicks = $averageCpc > 0 ? (int) round($amountSar / $averageCpc) : 0;
            $platformAr = self::PLATFORM_NAMES_AR[$platformKey] ?? 'منصة ' . $platformKey;

            $rows[] = [
                'index'      => $index++,
                'platform_ar' => $platformAr,
                'clicks'     => $clicks,
                'impressions' => $impressions,
            ];
            $totalClicks += $clicks;
            $totalImpressions += $impressions;
        }

        return [
            'rows' => $rows,
            'total_clicks' => $totalClicks,
            'total_impressions' => $totalImpressions,
        ];
    }

    /**
     * Build printable distribution table from SAR amounts per platform (e.g. for project-level aggregate).
     *
     * @param array<string, float> $platformAmountsSar Platform key => amount in SAR
     * @return array{rows: array, total_clicks: int, total_impressions: int}
     */
    public function buildPrintableDistributionFromAmounts(array $platformAmountsSar): array
    {
        $merged = $this->mergeCanonicalPlatformAmounts($platformAmountsSar);
        $totalValue = array_sum($merged);
        if ($totalValue <= 0) {
            return ['rows' => [], 'total_clicks' => 0, 'total_impressions' => 0];
        }
        $percentages = [];
        foreach ($merged as $platform => $amount) {
            $percentages[$platform] = ($amount / $totalValue) * 100;
        }
        return $this->buildPrintableDistributionTable($totalValue, $percentages);
    }

    /**
     * Merge platform keys to canonical (e.g. Instagram → Meta) and sum amounts.
     *
     * @param array<string, float> $platformAmountsSar
     * @return array<string, float>
     */
    public function mergeCanonicalPlatformAmounts(array $platformAmountsSar): array
    {
        $merged = [];
        foreach ($platformAmountsSar as $key => $amount) {
            $lower = is_string($key) ? strtolower($key) : $key;
            $canonical = self::CANONICAL_PLATFORM[$lower] ?? $key;
            $merged[$canonical] = ($merged[$canonical] ?? 0) + (float) $amount;
        }
        return $merged;
    }

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
