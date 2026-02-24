<?php

namespace App\Services\Marketing;

class MarketingPlanSuggestionService
{
    private MarketingDistributionBreakdownService $breakdownService;

    private ?AI\BudgetDistributionOptimizer $optimizer;

    public function __construct(
        MarketingDistributionBreakdownService $breakdownService,
        ?AI\BudgetDistributionOptimizer $optimizer = null,
    ) {
        $this->breakdownService = $breakdownService;
        $this->optimizer = $optimizer;
    }

    public function suggest(array $inputs): array
    {
        $marketingValue = $inputs['marketing_value'] ?? 0;
        $goal = $inputs['goal'] ?? 'leads';
        $region = $inputs['region'] ?? 'الرياض';
        $projectType = $inputs['project_type'] ?? 'on_map';

        if ($this->optimizer && $marketingValue > 0) {
            $aiResult = $this->tryDataDrivenDistribution($marketingValue, $goal, $projectType, $region);
            if ($aiResult !== null) {
                return $aiResult;
            }
        }

        if ($goal === 'awareness') {
            $platformDistribution = [
                'TikTok' => 30,
                'Snapchat' => 30,
                'Meta' => 20,
                'YouTube' => 15,
                'X' => 5,
                'LinkedIn' => 0,
            ];
        } elseif ($goal === 'bookings') {
            $platformDistribution = [
                'Meta' => 40,
                'TikTok' => 20,
                'Snapchat' => 20,
                'Google' => 0, // Assuming Google is outside PLATFORMS, but if we need 100% in PLATFORMS:
                'YouTube' => 10,
                'LinkedIn' => 10,
                'X' => 0,
            ];
        } else {
            // leads
            $platformDistribution = [
                'Meta' => 35,
                'Snapchat' => 25,
                'TikTok' => 25,
                'LinkedIn' => 10,
                'X' => 5,
                'YouTube' => 0,
            ];
        }

        // Adjust for Project Type
        if ($projectType === 'luxury' || $projectType === 'exclusive') {
            $platformDistribution['LinkedIn'] += 5;
            $platformDistribution['Meta'] += 5;
            $platformDistribution['Snapchat'] -= 5;
            $platformDistribution['TikTok'] -= 5;
        }

        // Fix missing / zeroes ensuring exactly 100%
        $platformDistribution = $this->normalizeDistribution($platformDistribution, EmployeeMarketingPlanService::PLATFORMS);

        // Define campaigns under platforms
        $campaignDistributionByPlatform = [];
        foreach (EmployeeMarketingPlanService::PLATFORMS as $platform) {
            $platformPercent = $platformDistribution[$platform] ?? 0;
            
            if ($platformPercent == 0) {
                $campaignDistributionByPlatform[$platform] = [
                    'Direct Communication' => 0,
                    'Hand Raise' => 0,
                    'Impression' => 0,
                    'Sales' => 0,
                ];
                continue;
            }

            if ($goal === 'awareness') {
                $campaigns = [
                    'Impression' => 60,
                    'Hand Raise' => 20,
                    'Direct Communication' => 10,
                    'Sales' => 10,
                ];
            } elseif ($goal === 'bookings') {
                $campaigns = [
                    'Sales' => 50,
                    'Direct Communication' => 30,
                    'Hand Raise' => 10,
                    'Impression' => 10,
                ];
            } else {
                // leads
                $campaigns = [
                    'Hand Raise' => 40,
                    'Direct Communication' => 30,
                    'Sales' => 20,
                    'Impression' => 10,
                ];
            }

            // Platform specific tweaks
            if ($platform === 'TikTok' || $platform === 'Snapchat') {
                $campaigns['Impression'] += 10;
                $campaigns['Sales'] -= 10;
            }

            $campaignDistributionByPlatform[$platform] = $this->normalizeDistribution($campaigns, EmployeeMarketingPlanService::CAMPAIGNS);
        }

        $campaignDistribution = $this->deriveCampaignDistribution($platformDistribution, $campaignDistributionByPlatform);

        $breakdown = [];
        if ($marketingValue > 0) {
            $breakdown = $this->breakdownService->breakdownAll((float) $marketingValue, $platformDistribution, $campaignDistributionByPlatform);
        }

        return [
            'platform_distribution' => $platformDistribution,
            'campaign_distribution_by_platform' => $campaignDistributionByPlatform,
            'campaign_distribution' => $campaignDistribution,
            'breakdown' => $breakdown,
            'rationale' => $this->generateRationale($goal, $projectType, $region),
            'assumptions' => ['تم بناء الاقتراح على بيانات السوق السعودي', 'تم مراعاة هدف الحملة المختار'],
            'warnings' => $this->generateWarnings($marketingValue, $platformDistribution)
        ];
    }

    /**
     * Attempt data-driven distribution using real campaign performance.
     * Returns null if insufficient data, falling back to static benchmarks.
     */
    private function tryDataDrivenDistribution(float $marketingValue, string $goal, string $projectType, string $region): ?array
    {
        try {
            $result = $this->optimizer->optimize($marketingValue, $goal, $projectType, $region);

            if ($result['confidence'] < 0.3) {
                return null;
            }

            $aiDist = $result['distribution'] ?? [];
            $platformMapping = ['meta' => 'Meta', 'snap' => 'Snapchat', 'tiktok' => 'TikTok'];

            $platformDistribution = [];
            foreach (EmployeeMarketingPlanService::PLATFORMS as $p) {
                $platformDistribution[$p] = 0;
            }
            foreach ($aiDist as $key => $pct) {
                $mapped = $platformMapping[$key] ?? null;
                if ($mapped && isset($platformDistribution[$mapped])) {
                    $platformDistribution[$mapped] = $pct;
                }
            }

            $remaining = 100 - array_sum($platformDistribution);
            if ($remaining > 0) {
                foreach (['YouTube', 'LinkedIn', 'X'] as $extra) {
                    if ($remaining <= 0) break;
                    $share = min($remaining, 5);
                    $platformDistribution[$extra] = $share;
                    $remaining -= $share;
                }
                if ($remaining > 0) {
                    $platformDistribution['Meta'] += $remaining;
                }
            }

            $platformDistribution = $this->normalizeDistribution($platformDistribution, EmployeeMarketingPlanService::PLATFORMS);

            $campaignDistributionByPlatform = [];
            foreach (EmployeeMarketingPlanService::PLATFORMS as $platform) {
                $pct = $platformDistribution[$platform] ?? 0;
                if ($pct == 0) {
                    $campaignDistributionByPlatform[$platform] = array_fill_keys(EmployeeMarketingPlanService::CAMPAIGNS, 0);
                    continue;
                }
                $lowKey = strtolower($platform === 'Snapchat' ? 'snap' : $platform);
                $splits = $this->optimizer->campaignTypeSplit($lowKey, $goal);
                $campaignDistributionByPlatform[$platform] = $this->normalizeDistribution($splits, EmployeeMarketingPlanService::CAMPAIGNS);
            }

            $campaignDistribution = $this->deriveCampaignDistribution($platformDistribution, $campaignDistributionByPlatform);
            $breakdown = $this->breakdownService->breakdownAll($marketingValue, $platformDistribution, $campaignDistributionByPlatform);

            return [
                'platform_distribution' => $platformDistribution,
                'campaign_distribution_by_platform' => $campaignDistributionByPlatform,
                'campaign_distribution' => $campaignDistribution,
                'breakdown' => $breakdown,
                'rationale' => 'التوزيع مبني على بيانات الحملات الفعلية من المنصات (Meta, Snapchat, TikTok). مستوى الثقة: ' . round($result['confidence'] * 100) . '%',
                'assumptions' => ['توزيع مبني على أداء الحملات الحقيقي', 'مدعوم ببيانات ' . ($result['data_days'] ?? 0) . ' يوم'],
                'warnings' => [],
                'ai_insights' => $result['insights'] ?? [],
                'ai_risk_flags' => $result['risk_flags'] ?? [],
                'data_driven' => true,
                'confidence' => $result['confidence'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function generateRationale(string $goal, string $projectType, string $region): string
    {
        $reasons = [];
        if ($goal === 'awareness') {
            $reasons[] = 'التركيز على تيك توك وسناب شات لزيادة الوصول وتخفيض التكلفة للمشاهدة.';
        } elseif ($goal === 'bookings') {
            $reasons[] = 'توجيه الميزانية لمنصات ميتا لكونها تعطي جودة عملاء أعلى وقدرة شرائية أفضل.';
        } else {
            $reasons[] = 'توزيع متوازن لجمع بيانات المهتمين بأفضل تكلفة عبر المنصات الأكثر استخداماً.';
        }

        if ($projectType === 'luxury' || $projectType === 'exclusive') {
            $reasons[] = 'تم تخصيص نسبة لـ LinkedIn لاستهداف أصحاب الدخل المرتفع.';
        }

        return implode(' ', $reasons);
    }

    private function generateWarnings(float $budget, array $platformDistribution): array
    {
        $warnings = [];
        if ($budget > 0 && $budget < 5000) {
            $activePlatforms = count(array_filter($platformDistribution, fn($v) => $v > 0));
            if ($activePlatforms > 2) {
                $warnings[] = 'الميزانية صغيرة جداً لتوزيعها على أكثر من منصتين، نوصي بالتركيز على منصة واحدة أو اثنتين.';
            }
        }
        return $warnings;
    }

    private function normalizeDistribution(array $input, array $allowed): array
    {
        $normalized = [];
        $sum = 0;
        foreach ($allowed as $key) {
            $val = isset($input[$key]) ? (float) $input[$key] : 0.0;
            if ($val < 0) $val = 0;
            $normalized[$key] = $val;
            $sum += $val;
        }

        if ($sum > 0) {
            $diff = 100 - $sum;
            if ($diff !== 0) {
                // Adjust largest to make it exactly 100
                $largestKey = array_keys($normalized, max($normalized))[0];
                $normalized[$largestKey] += $diff;
            }
        } else {
            // equal split if all 0
            $base = 100 / count($allowed);
            foreach ($allowed as $key) {
                $normalized[$key] = $base;
            }
            // fix rounding
            $sum = array_sum($normalized);
            $diff = 100 - $sum;
            $normalized[$allowed[0]] += $diff;
        }

        return $normalized;
    }

    private function deriveCampaignDistribution(array $platformDistribution, array $campaignDistributionByPlatform): array
    {
        $derived = [];
        foreach (EmployeeMarketingPlanService::CAMPAIGNS as $campaign) {
            $derived[$campaign] = 0.0;
        }

        foreach ($platformDistribution as $platform => $platformPercent) {
            $campaigns = $campaignDistributionByPlatform[$platform] ?? [];
            foreach ($campaigns as $campaign => $campaignPercent) {
                if (isset($derived[$campaign])) {
                    $derived[$campaign] += ($platformPercent / 100) * $campaignPercent;
                }
            }
        }

        return $this->normalizeDistribution($derived, EmployeeMarketingPlanService::CAMPAIGNS);
    }
}
