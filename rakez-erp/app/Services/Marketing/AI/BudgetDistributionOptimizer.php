<?php

namespace App\Services\Marketing\AI;

use App\Domain\Marketing\ValueObjects\PlatformPerformanceSummary;
use Illuminate\Support\Collection;

class BudgetDistributionOptimizer
{
    private const MIN_PLATFORM_PERCENT = 10;

    private const MAX_PLATFORM_PERCENT = 60;

    private const PLATFORMS = ['meta', 'snap', 'tiktok'];

    private const GOAL_WEIGHTS = [
        'awareness' => ['impressions_weight' => 0.5, 'cpl_weight' => 0.1, 'roas_weight' => 0.1, 'reach_weight' => 0.3],
        'leads' => ['impressions_weight' => 0.1, 'cpl_weight' => 0.5, 'roas_weight' => 0.2, 'reach_weight' => 0.2],
        'bookings' => ['impressions_weight' => 0.05, 'cpl_weight' => 0.25, 'roas_weight' => 0.5, 'reach_weight' => 0.2],
    ];

    private const STATIC_FALLBACK = [
        'awareness' => ['meta' => 25, 'snap' => 35, 'tiktok' => 40],
        'leads' => ['meta' => 40, 'snap' => 30, 'tiktok' => 30],
        'bookings' => ['meta' => 45, 'snap' => 25, 'tiktok' => 30],
    ];

    public function __construct(
        private readonly CampaignPerformanceAggregator $aggregator,
    ) {}

    /**
     * Calculate optimal budget distribution across platforms.
     */
    public function optimize(
        float $totalBudget,
        string $goal = 'leads',
        ?string $projectType = null,
        ?string $region = null,
        ?string $dateStart = null,
        ?string $dateEnd = null,
    ): array {
        $hasData = $this->aggregator->hasEnoughData(14);
        $platformData = $this->aggregator->byPlatform($dateStart, $dateEnd);

        if (! $hasData || $platformData->isEmpty()) {
            return $this->staticFallback($totalBudget, $goal, $projectType);
        }

        $scores = $this->scorePlatforms($platformData, $goal);
        $distribution = $this->normalizeWithConstraints($scores);
        $confidence = $this->calculateConfidence($platformData);

        if ($confidence < 0.5) {
            $distribution = $this->blendWithStatic($distribution, $goal, $confidence);
        }

        $budgets = $this->allocateBudget($totalBudget, $distribution);
        $expectedOutcomes = $this->projectOutcomes($budgets, $platformData);

        return [
            'distribution' => $distribution,
            'budgets' => $budgets,
            'expected_outcomes' => $expectedOutcomes,
            'confidence' => round($confidence, 2),
            'data_source' => $hasData ? 'real_data' : 'static_benchmarks',
            'data_days' => $this->aggregator->dataAvailableDays(),
            'insights' => $this->generateInsights($platformData, $distribution, $goal),
            'risk_flags' => $this->detectRisks($platformData, $dateStart, $dateEnd),
        ];
    }

    /**
     * Get campaign type split recommendation per platform.
     */
    public function campaignTypeSplit(string $platform, string $goal): array
    {
        return match ($goal) {
            'awareness' => [
                'Impression' => 50,
                'Hand Raise' => 25,
                'Direct Communication' => 15,
                'Sales' => 10,
            ],
            'bookings' => [
                'Sales' => 45,
                'Direct Communication' => 30,
                'Hand Raise' => 15,
                'Impression' => 10,
            ],
            default => [
                'Hand Raise' => 35,
                'Direct Communication' => 30,
                'Sales' => 25,
                'Impression' => 10,
            ],
        };
    }

    private function scorePlatforms(Collection $platformData, string $goal): array
    {
        $weights = self::GOAL_WEIGHTS[$goal] ?? self::GOAL_WEIGHTS['leads'];
        $scores = [];

        $maxImpressions = $platformData->max('totalImpressions') ?: 1;
        $maxReach = $platformData->max('totalReach') ?: 1;

        foreach ($platformData as $summary) {
            /** @var PlatformPerformanceSummary $summary */
            if (! in_array($summary->platform, self::PLATFORMS)) {
                continue;
            }

            $impressionScore = $summary->totalImpressions / $maxImpressions;
            $cplScore = $summary->cpl > 0 ? 1 / (1 + ($summary->cpl / 100)) : 0;
            $roasScore = min($summary->roas / 5, 1);
            $reachScore = $summary->totalReach / $maxReach;

            $totalScore = ($impressionScore * $weights['impressions_weight'])
                + ($cplScore * $weights['cpl_weight'])
                + ($roasScore * $weights['roas_weight'])
                + ($reachScore * $weights['reach_weight']);

            $scores[$summary->platform] = max($totalScore, 0.01);
        }

        foreach (self::PLATFORMS as $p) {
            if (! isset($scores[$p])) {
                $scores[$p] = 0.01;
            }
        }

        return $scores;
    }

    private function normalizeWithConstraints(array $scores): array
    {
        $total = array_sum($scores);
        $distribution = [];

        foreach ($scores as $platform => $score) {
            $pct = ($score / $total) * 100;
            $pct = max($pct, self::MIN_PLATFORM_PERCENT);
            $pct = min($pct, self::MAX_PLATFORM_PERCENT);
            $distribution[$platform] = round($pct, 1);
        }

        $sum = array_sum($distribution);
        if ($sum !== 100.0) {
            $diff = 100.0 - $sum;
            $largest = array_search(max($distribution), $distribution);
            $distribution[$largest] = round($distribution[$largest] + $diff, 1);
        }

        return $distribution;
    }

    private function blendWithStatic(array $dataDistribution, string $goal, float $confidence): array
    {
        $static = self::STATIC_FALLBACK[$goal] ?? self::STATIC_FALLBACK['leads'];
        $blended = [];

        foreach (self::PLATFORMS as $p) {
            $dataVal = $dataDistribution[$p] ?? 33.3;
            $staticVal = $static[$p] ?? 33.3;
            $blended[$p] = round(($dataVal * $confidence) + ($staticVal * (1 - $confidence)), 1);
        }

        $sum = array_sum($blended);
        if ($sum !== 100.0) {
            $largest = array_search(max($blended), $blended);
            $blended[$largest] = round($blended[$largest] + (100.0 - $sum), 1);
        }

        return $blended;
    }

    private function calculateConfidence(Collection $platformData): float
    {
        $days = $this->aggregator->dataAvailableDays();
        $platformCount = $platformData->count();

        $dayScore = min($days / 60, 1.0);
        $platformScore = min($platformCount / 3, 1.0);

        return round(($dayScore * 0.7) + ($platformScore * 0.3), 2);
    }

    private function allocateBudget(float $total, array $distribution): array
    {
        $budgets = [];
        foreach ($distribution as $platform => $pct) {
            $budgets[$platform] = round($total * ($pct / 100), 2);
        }

        return $budgets;
    }

    private function projectOutcomes(array $budgets, Collection $platformData): array
    {
        $outcomes = [];
        $totalLeads = 0;
        $totalRevenue = 0;

        foreach ($budgets as $platform => $budget) {
            $summary = $platformData->firstWhere('platform', $platform);

            $estimatedLeads = 0;
            $estimatedRevenue = 0;

            if ($summary && $summary->cpl > 0) {
                $estimatedLeads = (int) floor($budget / $summary->cpl);
            }
            if ($summary && $summary->roas > 0) {
                $estimatedRevenue = round($budget * $summary->roas, 2);
            }

            $outcomes[$platform] = [
                'budget' => $budget,
                'estimated_leads' => $estimatedLeads,
                'estimated_revenue' => $estimatedRevenue,
                'estimated_cpl' => $summary?->cpl ? round($summary->cpl, 2) : null,
            ];

            $totalLeads += $estimatedLeads;
            $totalRevenue += $estimatedRevenue;
        }

        $outcomes['total'] = [
            'estimated_leads' => $totalLeads,
            'estimated_revenue' => $totalRevenue,
            'estimated_roas' => array_sum($budgets) > 0 ? round($totalRevenue / array_sum($budgets), 2) : 0,
        ];

        return $outcomes;
    }

    private function generateInsights(Collection $platformData, array $distribution, string $goal): array
    {
        $insights = [];
        $benchmarks = $this->aggregator->benchmarkAgainstGuardrails();

        foreach ($platformData as $summary) {
            $bench = $benchmarks[$summary->platform] ?? null;

            if ($bench && $bench['cpl_status'] === 'below_benchmark') {
                $insights[] = [
                    'type' => 'positive',
                    'platform' => $summary->platform,
                    'message' => "تكلفة الليد على {$summary->platform} أقل من المتوسط — أداء ممتاز! ننصح بزيادة الميزانية.",
                ];
            }
            if ($bench && $bench['cpl_status'] === 'above_benchmark') {
                $insights[] = [
                    'type' => 'warning',
                    'platform' => $summary->platform,
                    'message' => "تكلفة الليد على {$summary->platform} أعلى من المتوسط. راجع استهداف الجمهور والمحتوى الإعلاني.",
                ];
            }
            if ($bench && $bench['is_outperformer']) {
                $insights[] = [
                    'type' => 'positive',
                    'platform' => $summary->platform,
                    'message' => "ROAS على {$summary->platform} ممتاز ({$summary->roas}x). هذه المنصة تحقق أفضل عائد.",
                ];
            }
        }

        return $insights;
    }

    private function detectRisks(Collection $platformData, ?string $dateStart, ?string $dateEnd): array
    {
        $risks = [];

        foreach ($platformData as $summary) {
            if ($summary->totalSpend > 0 && $summary->totalConversions === 0) {
                $risks[] = [
                    'platform' => $summary->platform,
                    'level' => 'high',
                    'message' => "إنفاق بدون تحويلات على {$summary->platform}. أوقف الحملات وراجع الإعدادات.",
                ];
            }

            if ($summary->roas > 0 && $summary->roas < 1.0) {
                $risks[] = [
                    'platform' => $summary->platform,
                    'level' => 'medium',
                    'message' => "ROAS أقل من 1 على {$summary->platform}. الإنفاق أكبر من العائد.",
                ];
            }
        }

        return $risks;
    }

    private function staticFallback(float $totalBudget, string $goal, ?string $projectType): array
    {
        $dist = self::STATIC_FALLBACK[$goal] ?? self::STATIC_FALLBACK['leads'];

        if ($projectType === 'luxury' || $projectType === 'exclusive') {
            $dist['meta'] = min($dist['meta'] + 10, 60);
            $dist['tiktok'] = max($dist['tiktok'] - 5, 10);
            $dist['snap'] = max($dist['snap'] - 5, 10);
            $sum = array_sum($dist);
            if ($sum !== 100) {
                $dist['meta'] += (100 - $sum);
            }
        }

        $budgets = $this->allocateBudget($totalBudget, $dist);

        return [
            'distribution' => $dist,
            'budgets' => $budgets,
            'expected_outcomes' => [],
            'confidence' => 0.0,
            'data_source' => 'static_benchmarks',
            'data_days' => 0,
            'insights' => [['type' => 'info', 'platform' => 'all', 'message' => 'لا توجد بيانات كافية. التوزيع مبني على معايير السوق السعودي.']],
            'risk_flags' => [],
        ];
    }
}
