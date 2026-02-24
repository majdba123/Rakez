<?php

namespace App\Http\Controllers\Ads;

use App\Infrastructure\Ads\Persistence\Models\AdsCampaign;
use App\Infrastructure\Ads\Persistence\Models\AdsInsightRow;
use App\Infrastructure\Ads\Persistence\Models\AdsPlatformAccount;
use App\Jobs\Ads\SyncCampaignStructureJob;
use App\Jobs\Ads\SyncInsightsJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdsInsightsController
{
    /**
     * GET /api/ads/accounts
     */
    public function accounts(): JsonResponse
    {
        $accounts = AdsPlatformAccount::where('is_active', true)
            ->select('id', 'platform', 'account_id', 'account_name', 'token_expires_at', 'is_active')
            ->get();

        return response()->json($accounts);
    }

    /**
     * GET /api/ads/campaigns
     */
    public function campaigns(Request $request): JsonResponse
    {
        $query = AdsCampaign::query()->orderByDesc('updated_at');

        if ($platform = $request->input('platform')) {
            $query->where('platform', $platform);
        }
        if ($accountId = $request->input('account_id')) {
            $query->where('account_id', $accountId);
        }

        return response()->json($query->paginate($request->input('per_page', 50)));
    }

    /**
     * GET /api/ads/insights
     */
    public function insights(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'nullable|string|in:meta,snap,tiktok',
            'account_id' => 'nullable|string',
            'level' => 'nullable|string|in:campaign,adset,ad',
            'date_start' => 'nullable|date',
            'date_stop' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:500',
        ]);

        $query = AdsInsightRow::query()->orderByDesc('date_start');

        if (! empty($validated['platform'])) {
            $query->where('platform', $validated['platform']);
        }
        if (! empty($validated['account_id'])) {
            $query->where('account_id', $validated['account_id']);
        }
        if (! empty($validated['level'])) {
            $query->where('level', $validated['level']);
        }
        if (! empty($validated['date_start'])) {
            $query->where('date_start', '>=', $validated['date_start']);
        }
        if (! empty($validated['date_stop'])) {
            $query->where('date_stop', '<=', $validated['date_stop']);
        }

        return response()->json($query->paginate($validated['per_page'] ?? 100));
    }

    /**
     * POST /api/ads/sync
     * Trigger a manual sync.
     */
    public function triggerSync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string|in:meta,snap,tiktok',
            'account_id' => 'required|string',
            'action' => 'required|string|in:campaigns,insights',
            'days' => 'nullable|integer|min:1|max:90',
        ]);

        match ($validated['action']) {
            'campaigns' => SyncCampaignStructureJob::dispatch(
                $validated['platform'],
                $validated['account_id'],
            ),
            'insights' => SyncInsightsJob::dispatch(
                $validated['platform'],
                $validated['account_id'],
                now()->subDays($validated['days'] ?? 7)->toDateString(),
                now()->toDateString(),
                ['campaign', 'adset', 'ad'],
            ),
        };

        return response()->json([
            'message' => "Sync job dispatched: {$validated['action']} for {$validated['platform']}",
        ]);
    }
}
