<?php

namespace App\Http\Controllers\Ads;

use App\Infrastructure\Ads\Persistence\Models\AdsSyncRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdsOpsController
{
    /**
     * GET /api/ads/ops/sync-runs
     */
    public function syncRuns(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|string',
            'platform' => 'nullable|string|in:meta,snap,tiktok',
            'account_id' => 'nullable|string',
            'status' => 'nullable|string|in:running,completed,failed',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $query = AdsSyncRun::query()->orderByDesc('id');

        foreach (['type', 'platform', 'account_id', 'status'] as $k) {
            if (! empty($validated[$k])) {
                $query->where($k, $validated[$k]);
            }
        }

        return response()->json($query->paginate($validated['per_page'] ?? 50));
    }
}

