<?php

namespace App\Services\Marketing\AI;

use App\Infrastructure\Ads\Persistence\Models\AdsInsightRow;
use App\Models\Lead;
use App\Models\SalesReservation;
use Illuminate\Support\Facades\DB;

class LeadFunnelAnalyzer
{
    public function __construct(
        private readonly CampaignPerformanceAggregator $aggregator,
    ) {}

    /**
     * Build a full funnel from platform spend through to closed deals.
     * Spend → Impressions → Clicks → Leads → Contacted → Qualified → Reservation → Confirmed → Sold
     */
    public function buildFunnel(
        ?string $platform = null,
        ?string $dateStart = null,
        ?string $dateEnd = null,
        ?int $projectId = null,
    ): array {
        $adsMetrics = $this->getAdsMetrics($platform, $dateStart, $dateEnd);
        $leadMetrics = $this->getLeadMetrics($platform, $dateStart, $dateEnd, $projectId);
        $salesMetrics = $this->getSalesMetrics($dateStart, $dateEnd, $projectId);

        $funnel = [
            'spend' => $adsMetrics['spend'],
            'impressions' => $adsMetrics['impressions'],
            'clicks' => $adsMetrics['clicks'],
            'leads' => $leadMetrics['total'],
            'contacted' => $leadMetrics['contacted'],
            'qualified' => $leadMetrics['qualified'],
            'reservations' => $salesMetrics['total_reservations'],
            'confirmed' => $salesMetrics['confirmed'],
            'sold' => $salesMetrics['sold'],
        ];

        $dropoffs = $this->calculateDropoffs($funnel);
        $bottleneck = $this->identifyBottleneck($dropoffs);
        $cpa = $funnel['spend'] > 0 && $funnel['sold'] > 0
            ? round($funnel['spend'] / $funnel['sold'], 2)
            : null;

        return [
            'funnel' => $funnel,
            'dropoff_rates' => $dropoffs,
            'bottleneck' => $bottleneck,
            'cost_per_acquisition' => $cpa,
            'cost_per_lead' => $funnel['spend'] > 0 && $funnel['leads'] > 0
                ? round($funnel['spend'] / $funnel['leads'], 2)
                : null,
            'overall_conversion_rate' => $funnel['leads'] > 0
                ? round(($funnel['sold'] / $funnel['leads']) * 100, 2)
                : null,
        ];
    }

    /**
     * Compare funnels across platforms.
     */
    public function comparePlatforms(?string $dateStart = null, ?string $dateEnd = null): array
    {
        $platforms = ['meta', 'snap', 'tiktok'];
        $comparison = [];

        foreach ($platforms as $platform) {
            $comparison[$platform] = $this->buildFunnel($platform, $dateStart, $dateEnd);
        }

        $bestPlatform = null;
        $bestCpa = PHP_FLOAT_MAX;
        foreach ($comparison as $p => $data) {
            if ($data['cost_per_acquisition'] !== null && $data['cost_per_acquisition'] < $bestCpa) {
                $bestCpa = $data['cost_per_acquisition'];
                $bestPlatform = $p;
            }
        }

        return [
            'platforms' => $comparison,
            'best_cpa_platform' => $bestPlatform,
            'recommendations' => $this->generateFunnelRecommendations($comparison),
        ];
    }

    /**
     * Segment funnel analysis by project type (on_map vs ready).
     */
    public function byProjectType(?string $dateStart = null, ?string $dateEnd = null): array
    {
        $results = [];

        foreach (['on_map' => true, 'ready' => false] as $label => $isOffPlan) {
            $projectIds = DB::table('contracts')
                ->where('is_off_plan', $isOffPlan)
                ->pluck('id')
                ->toArray();

            $leadCount = Lead::whereIn('project_id', $projectIds)
                ->when($dateStart, fn ($q) => $q->where('created_at', '>=', $dateStart))
                ->when($dateEnd, fn ($q) => $q->where('created_at', '<=', $dateEnd))
                ->count();

            $reservations = SalesReservation::whereIn('contract_id', $projectIds)
                ->when($dateStart, fn ($q) => $q->where('created_at', '>=', $dateStart))
                ->when($dateEnd, fn ($q) => $q->where('created_at', '<=', $dateEnd));

            $totalRes = $reservations->count();
            $confirmed = (clone $reservations)->where('status', 'confirmed')->count();

            $results[$label] = [
                'leads' => $leadCount,
                'reservations' => $totalRes,
                'confirmed' => $confirmed,
                'lead_to_reservation_rate' => $leadCount > 0 ? round(($totalRes / $leadCount) * 100, 2) : 0,
                'reservation_to_confirmed_rate' => $totalRes > 0 ? round(($confirmed / $totalRes) * 100, 2) : 0,
            ];
        }

        return $results;
    }

    private function getAdsMetrics(?string $platform, ?string $dateStart, ?string $dateEnd): array
    {
        $query = AdsInsightRow::query();

        if ($platform) {
            $query->where('platform', $platform);
        }
        if ($dateStart) {
            $query->where('date_start', '>=', $dateStart);
        }
        if ($dateEnd) {
            $query->where('date_stop', '<=', $dateEnd);
        }

        return [
            'spend' => round((float) $query->sum('spend'), 2),
            'impressions' => (int) $query->sum('impressions'),
            'clicks' => (int) $query->sum('clicks'),
        ];
    }

    private function getLeadMetrics(?string $platform, ?string $dateStart, ?string $dateEnd, ?int $projectId): array
    {
        $query = Lead::query();

        if ($platform) {
            $query->where('campaign_platform', $platform);
        }
        if ($dateStart) {
            $query->where('created_at', '>=', $dateStart);
        }
        if ($dateEnd) {
            $query->where('created_at', '<=', $dateEnd);
        }
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $total = (clone $query)->count();
        $contacted = (clone $query)->where('status', 'contacted')->count();
        $qualified = (clone $query)->where('status', 'qualified')->count();
        $converted = (clone $query)->where('status', 'converted')->count();

        return [
            'total' => $total,
            'contacted' => $contacted + $qualified + $converted,
            'qualified' => $qualified + $converted,
        ];
    }

    private function getSalesMetrics(?string $dateStart, ?string $dateEnd, ?int $projectId): array
    {
        $query = SalesReservation::query();

        if ($dateStart) {
            $query->where('created_at', '>=', $dateStart);
        }
        if ($dateEnd) {
            $query->where('created_at', '<=', $dateEnd);
        }
        if ($projectId) {
            $query->where('contract_id', $projectId);
        }

        $total = (clone $query)->count();
        $confirmed = (clone $query)->where('status', 'confirmed')->count();
        $sold = SalesReservation::query()
            ->when($dateStart, fn ($q) => $q->where('created_at', '>=', $dateStart))
            ->when($dateEnd, fn ($q) => $q->where('created_at', '<=', $dateEnd))
            ->when($projectId, fn ($q) => $q->where('contract_id', $projectId))
            ->whereHas('contractUnit', fn ($q) => $q->where('status', 'sold'))
            ->count();

        return [
            'total_reservations' => $total,
            'confirmed' => $confirmed,
            'sold' => $sold,
        ];
    }

    private function calculateDropoffs(array $funnel): array
    {
        $stages = [
            'impressions_to_clicks' => ['impressions', 'clicks'],
            'clicks_to_leads' => ['clicks', 'leads'],
            'leads_to_contacted' => ['leads', 'contacted'],
            'contacted_to_qualified' => ['contacted', 'qualified'],
            'qualified_to_reservation' => ['qualified', 'reservations'],
            'reservation_to_confirmed' => ['reservations', 'confirmed'],
            'confirmed_to_sold' => ['confirmed', 'sold'],
        ];

        $dropoffs = [];
        foreach ($stages as $name => [$from, $to]) {
            $fromVal = $funnel[$from] ?? 0;
            $toVal = $funnel[$to] ?? 0;

            $dropoffs[$name] = [
                'from' => $fromVal,
                'to' => $toVal,
                'conversion_rate' => $fromVal > 0 ? round(($toVal / $fromVal) * 100, 2) : 0,
                'drop_rate' => $fromVal > 0 ? round((1 - ($toVal / $fromVal)) * 100, 2) : 0,
            ];
        }

        return $dropoffs;
    }

    private function identifyBottleneck(array $dropoffs): ?array
    {
        $worstStage = null;
        $worstDrop = 0;

        foreach ($dropoffs as $stage => $data) {
            if ($data['from'] > 0 && $data['drop_rate'] > $worstDrop) {
                $worstDrop = $data['drop_rate'];
                $worstStage = $stage;
            }
        }

        if (! $worstStage) {
            return null;
        }

        $recommendations = $this->bottleneckRecommendations($worstStage);

        return [
            'stage' => $worstStage,
            'drop_rate' => $worstDrop,
            'recommendation' => $recommendations,
        ];
    }

    private function bottleneckRecommendations(string $stage): string
    {
        return match ($stage) {
            'impressions_to_clicks' => 'تحسين جودة الإعلانات والمحتوى الإبداعي. جرّب عناوين جذابة وصور أوضح للمشاريع.',
            'clicks_to_leads' => 'تحسين صفحات الهبوط. تأكد من سرعة التحميل ووضوح نموذج التسجيل.',
            'leads_to_contacted' => 'تسريع التواصل مع العملاء المحتملين. الهدف: تواصل خلال 30 دقيقة من التسجيل.',
            'contacted_to_qualified' => 'تحسين جودة المحادثة الأولى. تدريب الموظفين على تأهيل العملاء بشكل أفضل.',
            'qualified_to_reservation' => 'تحسين عملية الحجز. قدم عروض محفزة وسهّل إجراءات الحجز.',
            'reservation_to_confirmed' => 'متابعة الحجوزات المعلقة. تحسين عملية التفاوض والموافقات.',
            'confirmed_to_sold' => 'تسريع إجراءات نقل الملكية والتمويل. دعم العملاء في الحصول على التمويل.',
            default => 'مراجعة العملية بالكامل وتحديد نقاط الضعف.',
        };
    }

    private function generateFunnelRecommendations(array $platformComparison): array
    {
        $recs = [];

        foreach ($platformComparison as $platform => $data) {
            $bottleneck = $data['bottleneck'] ?? null;
            if ($bottleneck) {
                $recs[] = [
                    'platform' => $platform,
                    'issue' => "عنق الزجاجة في {$bottleneck['stage']} بنسبة فقد {$bottleneck['drop_rate']}%",
                    'action' => $bottleneck['recommendation'],
                ];
            }
        }

        return $recs;
    }
}
