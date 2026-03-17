<?php

namespace App\Services\AI\Tools;

use App\Models\DailyMarketingSpend;
use App\Models\Lead;
use App\Models\MarketingCampaign;
use App\Models\User;
use Illuminate\Support\Carbon;

class MarketingAnalyticsTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('marketing.dashboard.view')) {
            return ToolResponse::denied('marketing.dashboard.view');
        }

        $reportType = $args['report_type'] ?? 'overview';
        $dateFrom = isset($args['date_from']) ? Carbon::parse($args['date_from']) : now()->subDays(30);
        $dateTo = isset($args['date_to']) ? Carbon::parse($args['date_to']) : now();

        $data = match ($reportType) {
            'overview' => $this->overview($dateFrom, $dateTo),
            'channel_comparison' => $this->channelComparison($dateFrom, $dateTo),
            'team_performance' => $this->teamPerformance($dateFrom, $dateTo),
            'lead_quality' => $this->leadQuality($dateFrom, $dateTo),
            default => $this->overview($dateFrom, $dateTo),
        };

        $data['report_type'] = $reportType;
        $data['period'] = ['from' => $dateFrom->toDateString(), 'to' => $dateTo->toDateString()];

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
        $leads = Lead::whereBetween('created_at', [$from, $to]);

        $byStatus = (clone $leads)->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byScore = (clone $leads)->selectRaw('
            CASE
                WHEN lead_score >= 80 THEN "hot"
                WHEN lead_score >= 50 THEN "warm"
                ELSE "cold"
            END as quality,
            COUNT(*) as count
        ')->groupBy('quality')->pluck('count', 'quality')->toArray();

        return [
            'by_status' => $byStatus,
            'by_quality' => $byScore,
        ];
    }
}
