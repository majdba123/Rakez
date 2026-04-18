<?php

namespace App\Services\AI\Tools;

use App\Models\DailyMarketingSpend;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;

class MarketingAnalyticsTool implements ToolContract
{
    private const REPORT_OVERVIEW = 'overview';

    private const REPORT_CHANNEL = 'channel_comparison';

    private const REPORT_TEAM = 'team_performance';

    private const REPORT_QUALITY = 'lead_quality';

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('marketing.dashboard.view')) {
            return ToolResponse::denied('marketing.dashboard.view');
        }

        $reportType = $args['report_type'] ?? null;
        $allowed = [self::REPORT_OVERVIEW, self::REPORT_CHANNEL, self::REPORT_TEAM, self::REPORT_QUALITY];
        if (! is_string($reportType) || ! in_array($reportType, $allowed, true)) {
            return ToolResponse::invalidArguments(
                'report_type must be one of: '.implode(', ', $allowed).'.'
            );
        }

        try {
            $dateFrom = isset($args['date_from']) ? Carbon::parse($args['date_from']) : now()->subDays(30);
            $dateTo = isset($args['date_to']) ? Carbon::parse($args['date_to']) : now();
        } catch (\Exception) {
            return ToolResponse::invalidArguments('date_from or date_to is not a valid date.');
        }

        $data = match ($reportType) {
            self::REPORT_OVERVIEW => $this->overview($dateFrom, $dateTo),
            self::REPORT_CHANNEL => $this->channelComparison($dateFrom, $dateTo),
            self::REPORT_TEAM => $this->teamPerformance($dateFrom, $dateTo),
            self::REPORT_QUALITY => $this->leadQuality($dateFrom, $dateTo),
        };

        $data['report_type'] = $reportType;
        $data['period'] = ['from' => $dateFrom->toDateString(), 'to' => $dateTo->toDateString()];
        $data['warnings'] = array_merge($data['warnings'] ?? [], [
            'Lead quality bands use configurable score thresholds (see config/ai_marketing_tools.php), not external CRM scoring.',
        ]);

        return ToolResponse::success('tool_marketing_analytics', $args, $data, [
            ['type' => 'tool', 'title' => 'Marketing Analytics', 'ref' => 'analytics:marketing'],
        ]);
    }

    private function overview(Carbon $from, Carbon $to): array
    {
        $totalLeads = Lead::whereBetween('created_at', [$from, $to])->count();
        $totalSpend = DailyMarketingSpend::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $avgCpl = $totalLeads > 0 ? round($totalSpend / $totalLeads, 2) : 0;

        $byPlatform = Lead::whereBetween('created_at', [$from, $to])
            ->selectRaw('campaign_platform, COUNT(*) as count')
            ->groupBy('campaign_platform')
            ->pluck('count', 'campaign_platform')
            ->toArray();

        return [
            'total_leads' => $totalLeads,
            'total_spend' => round($totalSpend, 2),
            'avg_cpl' => $avgCpl,
            'leads_by_platform' => $byPlatform,
        ];
    }

    private function channelComparison(Carbon $from, Carbon $to): array
    {
        $channels = Lead::whereBetween('created_at', [$from, $to])
            ->selectRaw('campaign_platform, COUNT(*) as leads_count')
            ->groupBy('campaign_platform')
            ->get();

        $comparison = [];
        foreach ($channels as $channel) {
            $platform = $channel->campaign_platform ?? 'unknown';
            $spend = DailyMarketingSpend::where('platform', $platform)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->sum('amount');

            $comparison[$platform] = [
                'leads' => $channel->leads_count,
                'spend' => round($spend, 2),
                'cpl' => $channel->leads_count > 0 ? round($spend / $channel->leads_count, 2) : 0,
            ];
        }

        return ['channel_comparison' => $comparison];
    }

    private function teamPerformance(Carbon $from, Carbon $to): array
    {
        $byAssignee = Lead::whereBetween('created_at', [$from, $to])
            ->selectRaw('assigned_to, COUNT(*) as count')
            ->groupBy('assigned_to')
            ->with('assignedTo:id,name')
            ->get()
            ->map(fn ($item) => [
                'user_id' => $item->assigned_to,
                'name' => $item->assignedTo?->name ?? 'Unknown',
                'leads_count' => $item->count,
            ])->toArray();

        return ['team_performance' => $byAssignee];
    }

    private function leadQuality(Carbon $from, Carbon $to): array
    {
        $hot = max(0, min(100, (int) config('ai_marketing_tools.lead_quality_score_thresholds.hot', 80)));
        $warm = max(0, min(100, (int) config('ai_marketing_tools.lead_quality_score_thresholds.warm', 50)));

        $leads = Lead::whereBetween('created_at', [$from, $to]);

        $byStatus = (clone $leads)->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byScore = (clone $leads)->selectRaw("
            CASE
                WHEN lead_score >= ? THEN 'hot'
                WHEN lead_score >= ? THEN 'warm'
                ELSE 'cold'
            END as quality,
            COUNT(*) as count
        ", [$hot, $warm])->groupBy('quality')->pluck('count', 'quality')->toArray();

        return [
            'by_status' => $byStatus,
            'by_quality' => $byScore,
            'quality_thresholds' => ['hot_min' => $hot, 'warm_min' => $warm],
        ];
    }
}
