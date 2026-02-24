<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\Marketing\AI\CampaignPerformanceAggregator;
use App\Services\Marketing\AI\LeadFunnelAnalyzer;

class CampaignFunnelAnalyticsTool implements ToolContract
{
    public function __construct(
        private readonly LeadFunnelAnalyzer $funnelAnalyzer,
        private readonly CampaignPerformanceAggregator $aggregator,
    ) {}

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('marketing.dashboard.view')) {
            return ToolResponse::denied('marketing.dashboard.view');
        }

        $reportType = $args['report_type'] ?? 'funnel';
        $platform = $args['platform'] ?? null;
        $dateStart = $args['date_start'] ?? now()->subDays(30)->toDateString();
        $dateEnd = $args['date_end'] ?? now()->toDateString();
        $projectId = isset($args['project_id']) ? (int) $args['project_id'] : null;

        $inputs = compact('reportType', 'platform', 'dateStart', 'dateEnd', 'projectId');

        $outputs = match ($reportType) {
            'funnel' => $this->funnelReport($platform, $dateStart, $dateEnd, $projectId),
            'platform_comparison' => $this->platformComparisonReport($dateStart, $dateEnd),
            'trend' => $this->trendReport($platform, $dateStart, $dateEnd),
            'project_type' => $this->projectTypeReport($dateStart, $dateEnd),
            default => $this->funnelReport($platform, $dateStart, $dateEnd, $projectId),
        };

        return ToolResponse::success('tool_campaign_funnel', $inputs, $outputs, [
            ['type' => 'tool', 'title' => 'Campaign Funnel Analytics', 'ref' => 'tool_campaign_funnel'],
        ], [
            'البيانات مبنية على الحملات الإعلانية الفعلية والمبيعات الحقيقية',
            "الفترة: {$dateStart} إلى {$dateEnd}",
        ]);
    }

    private function funnelReport(?string $platform, string $dateStart, string $dateEnd, ?int $projectId): array
    {
        $funnel = $this->funnelAnalyzer->buildFunnel($platform, $dateStart, $dateEnd, $projectId);

        return [
            'type' => 'funnel',
            'data' => $funnel,
            'summary' => $this->generateFunnelSummary($funnel),
        ];
    }

    private function platformComparisonReport(string $dateStart, string $dateEnd): array
    {
        $comparison = $this->funnelAnalyzer->comparePlatforms($dateStart, $dateEnd);
        $platformMetrics = $this->aggregator->byPlatform($dateStart, $dateEnd);

        $matrix = [];
        foreach ($platformMetrics as $summary) {
            $matrix[$summary->platform] = $summary->toArray();
        }

        return [
            'type' => 'platform_comparison',
            'funnel_comparison' => $comparison['platforms'],
            'metrics_matrix' => $matrix,
            'best_cpa_platform' => $comparison['best_cpa_platform'],
            'recommendations' => $comparison['recommendations'],
        ];
    }

    private function trendReport(?string $platform, string $dateStart, string $dateEnd): array
    {
        if (! $platform) {
            $platform = 'meta';
        }

        $daily = $this->aggregator->dailyTrend($platform, $dateStart, $dateEnd);

        $previousStart = now()->parse($dateStart)->subDays(
            now()->parse($dateStart)->diffInDays(now()->parse($dateEnd))
        )->toDateString();
        $previousEnd = now()->parse($dateStart)->subDay()->toDateString();

        $periodComparison = $this->aggregator->periodComparison(
            $dateStart,
            $dateEnd,
            $previousStart,
            $previousEnd,
        );

        return [
            'type' => 'trend',
            'platform' => $platform,
            'daily_data' => $daily->toArray(),
            'period_comparison' => $periodComparison[$platform] ?? null,
        ];
    }

    private function projectTypeReport(string $dateStart, string $dateEnd): array
    {
        $byType = $this->funnelAnalyzer->byProjectType($dateStart, $dateEnd);

        return [
            'type' => 'project_type',
            'data' => $byType,
            'insights' => $this->generateProjectTypeInsights($byType),
        ];
    }

    private function generateFunnelSummary(array $funnel): array
    {
        $summary = [];
        $data = $funnel['funnel'] ?? [];

        if (($data['leads'] ?? 0) > 0 && ($data['sold'] ?? 0) > 0) {
            $overallRate = round(($data['sold'] / $data['leads']) * 100, 2);
            $summary[] = "نسبة التحويل الإجمالية من عميل محتمل لبيع مكتمل: {$overallRate}%";
        }

        if ($funnel['cost_per_acquisition']) {
            $summary[] = "تكلفة الحصول على عميل فعلي (CPA): {$funnel['cost_per_acquisition']} ريال";
        }

        if ($funnel['cost_per_lead']) {
            $summary[] = "تكلفة الحصول على عميل محتمل (CPL): {$funnel['cost_per_lead']} ريال";
        }

        $bottleneck = $funnel['bottleneck'] ?? null;
        if ($bottleneck) {
            $summary[] = "عنق الزجاجة: {$bottleneck['stage']} بنسبة فقد {$bottleneck['drop_rate']}%";
            $summary[] = "التوصية: {$bottleneck['recommendation']}";
        }

        return $summary;
    }

    private function generateProjectTypeInsights(array $byType): array
    {
        $insights = [];

        $onMap = $byType['on_map'] ?? null;
        $ready = $byType['ready'] ?? null;

        if ($onMap && $ready) {
            if ($ready['reservation_to_confirmed_rate'] > $onMap['reservation_to_confirmed_rate']) {
                $diff = round($ready['reservation_to_confirmed_rate'] - $onMap['reservation_to_confirmed_rate'], 1);
                $insights[] = "المشاريع الجاهزة أعلى بـ {$diff}% في نسبة تأكيد الحجوزات مقارنة بمشاريع الخارطة.";
            }

            if ($onMap['lead_to_reservation_rate'] > $ready['lead_to_reservation_rate']) {
                $insights[] = 'مشاريع الخارطة تجذب حجوزات أكثر نسبياً — لكن تأكيدها يحتاج متابعة أقوى.';
            }
        }

        return $insights;
    }
}
