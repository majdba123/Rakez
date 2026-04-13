<?php

namespace App\Http\Controllers\Ads;

use App\Services\Marketing\AI\CampaignPerformanceAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdsReportingController
{
    public function __construct(
        private readonly CampaignPerformanceAggregator $aggregator,
    ) {}

    /**
     * GET /api/ads/reports/platform-performance
     */
    public function platformPerformance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date',
        ]);

        $data = $this->aggregator->byPlatform(
            $validated['date_start'] ?? null,
            $validated['date_end'] ?? null,
        )->map->toArray()->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * GET /api/ads/reports/campaign-performance
     */
    public function campaignPerformance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string|in:meta,snap,tiktok',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->aggregator->byCampaign(
                $validated['platform'],
                $validated['date_start'] ?? null,
                $validated['date_end'] ?? null,
            )->values(),
        ]);
    }

    /**
     * GET /api/ads/reports/daily-trend
     */
    public function dailyTrend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string|in:meta,snap,tiktok',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->aggregator->dailyTrend(
                $validated['platform'],
                $validated['date_start'] ?? null,
                $validated['date_end'] ?? null,
            )->values(),
        ]);
    }
}

