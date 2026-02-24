<?php

namespace App\Services\AI\Tools;

use App\Infrastructure\Ads\Persistence\Models\AdsInsightRow;
use App\Models\Commission;
use App\Models\MarketingSalesAttribution;
use App\Models\SalesReservation;
use App\Models\User;
use App\Services\AI\NumericGuardrails;
use App\Services\Marketing\AI\CampaignPerformanceAggregator;
use Illuminate\Support\Facades\DB;

class RoasOptimizerTool implements ToolContract
{
    public function __construct(
        private readonly CampaignPerformanceAggregator $aggregator,
        private readonly NumericGuardrails $guardrails,
    ) {}

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('marketing.dashboard.view') && ! $user->can('accounting.dashboard.view')) {
            return ToolResponse::denied('marketing.dashboard.view');
        }

        $dateStart = $args['date_start'] ?? now()->subDays(30)->toDateString();
        $dateEnd = $args['date_end'] ?? now()->toDateString();
        $platform = $args['platform'] ?? null;

        $inputs = compact('dateStart', 'dateEnd', 'platform');

        $adSpend = $this->getAdSpend($platform, $dateStart, $dateEnd);
        $salesRevenue = $this->getSalesRevenue($dateStart, $dateEnd);
        $attributionData = $this->getAttributionData($platform, $dateStart, $dateEnd);

        $platformBreakdown = $this->buildPlatformBreakdown($dateStart, $dateEnd);
        $trueRoas = $adSpend > 0 ? round($salesRevenue / $adSpend, 2) : 0;
        $costPerDeal = $this->computeCostPerDeal($dateStart, $dateEnd);

        $efficiencyScore = $this->computeEfficiencyScore($trueRoas, $costPerDeal);
        $recommendations = $this->generateRecommendations($platformBreakdown, $trueRoas);

        $response = ToolResponse::success('tool_roas_optimizer', $inputs, [
            'total_ad_spend' => round($adSpend, 2),
            'total_sales_revenue' => round($salesRevenue, 2),
            'true_roas' => $trueRoas,
            'cost_per_closed_deal' => $costPerDeal,
            'efficiency_score' => $efficiencyScore,
            'platform_breakdown' => $platformBreakdown,
            'attribution_data' => $attributionData,
            'recommendations' => $recommendations,
        ], [['type' => 'tool', 'title' => 'ROAS Optimizer', 'ref' => 'tool_roas_optimizer']], [
            'العائد محسوب من الإيرادات الفعلية (عمولات ومبيعات) مقسومة على الإنفاق الإعلاني',
            "الفترة: {$dateStart} إلى {$dateEnd}",
        ]);

        $romiCheck = $this->guardrails->validateROI($trueRoas * 100, 'romi');

        return ToolResponse::withGuardrails($response, $romiCheck);
    }

    private function getAdSpend(?string $platform, string $dateStart, string $dateEnd): float
    {
        $query = AdsInsightRow::where('date_start', '>=', $dateStart)
            ->where('date_stop', '<=', $dateEnd);

        if ($platform) {
            $query->where('platform', $platform);
        }

        return (float) $query->sum('spend');
    }

    private function getSalesRevenue(string $dateStart, string $dateEnd): float
    {
        return (float) Commission::where('created_at', '>=', $dateStart)
            ->where('created_at', '<=', $dateEnd)
            ->sum('final_selling_price');
    }

    private function getAttributionData(?string $platform, string $dateStart, string $dateEnd): array
    {
        $query = MarketingSalesAttribution::where('created_at', '>=', $dateStart)
            ->where('created_at', '<=', $dateEnd);

        if ($platform) {
            $query->where('platform', $platform);
        }

        $data = $query->select(
            'platform',
            DB::raw('SUM(marketing_spend) as total_spend'),
            DB::raw('SUM(revenue) as total_revenue'),
            DB::raw('COUNT(*) as attributions_count'),
        )->groupBy('platform')->get();

        return $data->map(fn ($row) => [
            'platform' => $row->platform,
            'spend' => round((float) $row->total_spend, 2),
            'revenue' => round((float) $row->total_revenue, 2),
            'roas' => $row->total_spend > 0 ? round($row->total_revenue / $row->total_spend, 2) : 0,
            'count' => $row->attributions_count,
        ])->toArray();
    }

    private function buildPlatformBreakdown(string $dateStart, string $dateEnd): array
    {
        $platforms = $this->aggregator->byPlatform($dateStart, $dateEnd);
        $breakdown = [];

        foreach ($platforms as $summary) {
            $breakdown[$summary->platform] = [
                'ad_spend' => round($summary->totalSpend, 2),
                'ad_revenue' => round($summary->totalRevenue, 2),
                'ad_roas' => round($summary->roas, 2),
                'cpl' => round($summary->cpl, 2),
                'conversions' => $summary->totalConversions,
            ];
        }

        return $breakdown;
    }

    private function computeCostPerDeal(string $dateStart, string $dateEnd): ?float
    {
        $totalSpend = (float) AdsInsightRow::where('date_start', '>=', $dateStart)
            ->where('date_stop', '<=', $dateEnd)
            ->sum('spend');

        $closedDeals = SalesReservation::where('created_at', '>=', $dateStart)
            ->where('created_at', '<=', $dateEnd)
            ->where('status', 'confirmed')
            ->count();

        if ($closedDeals === 0) {
            return null;
        }

        return round($totalSpend / $closedDeals, 2);
    }

    private function computeEfficiencyScore(float $roas, ?float $costPerDeal): array
    {
        $score = 0;
        $label = 'ضعيف';

        if ($roas >= 5) {
            $score = 90;
            $label = 'ممتاز';
        } elseif ($roas >= 3) {
            $score = 75;
            $label = 'جيد جداً';
        } elseif ($roas >= 1.5) {
            $score = 60;
            $label = 'جيد';
        } elseif ($roas >= 1) {
            $score = 40;
            $label = 'مقبول';
        } elseif ($roas > 0) {
            $score = 20;
            $label = 'ضعيف';
        }

        return [
            'score' => $score,
            'label' => $label,
            'roas' => $roas,
            'cost_per_deal' => $costPerDeal,
        ];
    }

    private function generateRecommendations(array $platformBreakdown, float $overallRoas): array
    {
        $recs = [];

        if ($overallRoas < 1) {
            $recs[] = [
                'priority' => 'high',
                'message' => 'العائد الإعلاني أقل من التكلفة. يجب مراجعة استراتيجية الحملات بشكل عاجل.',
            ];
        }

        $bestPlatform = null;
        $bestRoas = 0;
        $worstPlatform = null;
        $worstRoas = PHP_FLOAT_MAX;

        foreach ($platformBreakdown as $platform => $data) {
            if ($data['ad_spend'] > 0) {
                if ($data['ad_roas'] > $bestRoas) {
                    $bestRoas = $data['ad_roas'];
                    $bestPlatform = $platform;
                }
                if ($data['ad_roas'] < $worstRoas) {
                    $worstRoas = $data['ad_roas'];
                    $worstPlatform = $platform;
                }
            }
        }

        if ($bestPlatform && $bestRoas > 2) {
            $recs[] = [
                'priority' => 'medium',
                'message' => "منصة {$bestPlatform} تحقق أفضل عائد ({$bestRoas}x). ننصح بزيادة الميزانية المخصصة لها.",
            ];
        }

        if ($worstPlatform && $worstRoas < 1 && $worstPlatform !== $bestPlatform) {
            $recs[] = [
                'priority' => 'high',
                'message' => "منصة {$worstPlatform} تحقق عائد ضعيف ({$worstRoas}x). راجع الحملات أو قلل الميزانية.",
            ];
        }

        return $recs;
    }
}
