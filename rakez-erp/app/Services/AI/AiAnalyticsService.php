<?php

namespace App\Services\AI;

use App\Models\AiInteractionLog;
use Illuminate\Support\Carbon;

class AiAnalyticsService
{
    /**
     * Log an AI interaction.
     */
    public function logInteraction(array $data): void
    {
        AiInteractionLog::create($data);
    }

    /**
     * Get daily usage statistics.
     */
    public function dailyUsageStats(?Carbon $date = null): array
    {
        $date = $date ?? now();
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $logs = AiInteractionLog::whereBetween('created_at', [$start, $end]);

        return [
            'date' => $date->toDateString(),
            'total_requests' => (clone $logs)->count(),
            'total_tokens' => (int) (clone $logs)->sum('total_tokens'),
            'avg_latency_ms' => round((clone $logs)->avg('latency_ms') ?? 0, 2),
            'error_count' => (clone $logs)->where('had_error', true)->count(),
            'unique_users' => (clone $logs)->distinct('user_id')->count('user_id'),
            'by_type' => (clone $logs)->selectRaw('request_type, COUNT(*) as count')
                ->groupBy('request_type')
                ->pluck('count', 'request_type')
                ->toArray(),
        ];
    }

    /**
     * Get usage statistics for a specific user.
     */
    public function userUsageStats(int $userId, Carbon $from, Carbon $to): array
    {
        $logs = AiInteractionLog::where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to]);

        return [
            'user_id' => $userId,
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'total_requests' => (clone $logs)->count(),
            'total_tokens' => (int) (clone $logs)->sum('total_tokens'),
            'avg_latency_ms' => round((clone $logs)->avg('latency_ms') ?? 0, 2),
            'total_tool_calls' => (int) (clone $logs)->sum('tool_calls_count'),
            'by_section' => (clone $logs)->selectRaw('section, COUNT(*) as count')
                ->groupBy('section')
                ->pluck('count', 'section')
                ->toArray(),
        ];
    }
}
