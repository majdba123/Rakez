<?php

namespace App\Http\Controllers\Ads;

use App\Exports\PlatformLeadsExport;
use App\Infrastructure\Ads\Meta\MetaLeadGenReader;
use App\Infrastructure\Ads\Persistence\Models\AdsLeadSubmission;
use App\Infrastructure\Ads\Persistence\Models\AdsPlatformAccount;
use App\Infrastructure\Ads\Snap\SnapLeadGenReader;
use App\Infrastructure\Ads\TikTok\TikTokLeadGenReader;
use App\Jobs\Ads\SyncLeadsJob;
use App\Services\Ads\PlatformLeadSyncService;
use App\Services\Ads\SnapCsvLeadNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdsLeadsController
{
    public function __construct(
        private readonly MetaLeadGenReader $metaLeadGenReader,
        private readonly TikTokLeadGenReader $tikTokLeadGenReader,
        private readonly SnapLeadGenReader $snapLeadGenReader,
        private readonly SnapCsvLeadNormalizer $snapCsvNormalizer,
        private readonly PlatformLeadSyncService $platformLeadSync,
    ) {}

    /**
     * GET /api/ads/leads – list normalized leads as JSON (optional, for UI/debugging).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string|in:meta,tiktok,snap',
            'form_id' => 'nullable|string',
            'ad_id' => 'nullable|string',
            'advertiser_id' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sync' => 'nullable|boolean',
        ]);

        if ($validated['platform'] === 'tiktok' && empty($validated['advertiser_id']) && empty(config('ads_platforms.tiktok.advertiser_id'))) {
            return response()->json([
                'success' => false,
                'message' => 'For TikTok, advertiser_id is required (query param or ads_platforms.tiktok.advertiser_id).',
            ], 422);
        }

        if ($validated['platform'] === 'snap' && empty($validated['advertiser_id']) && empty(config('ads_platforms.snap.ad_account_id'))) {
            return response()->json([
                'success' => false,
                'message' => 'For Snap, advertiser_id (ad account id) is required (query param or ads_platforms.snap.ad_account_id).',
            ], 422);
        }

        if ($validated['platform'] === 'meta' && empty($validated['form_id']) && empty($validated['ad_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'For Meta, either form_id or ad_id is required.',
            ], 422);
        }

        $leads = $this->fetchLeads(
            $validated['platform'],
            $validated['form_id'] ?? null,
            $validated['ad_id'] ?? null,
            $validated['advertiser_id'] ?? config('ads_platforms.tiktok.advertiser_id'),
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        );

        $syncResult = null;
        if (! empty($validated['sync']) && ! empty($leads)) {
            $syncResult = $this->platformLeadSync->sync($leads);
        }

        $response = [
            'success' => true,
            'data' => $leads,
            'count' => count($leads),
        ];
        if ($syncResult !== null) {
            $response['sync'] = $syncResult;
        }

        return response()->json($response);
    }

    /**
     * GET /api/ads/leads/export – export leads as Excel.
     */
    public function stored(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'nullable|string|in:meta,tiktok,snap',
            'account_id' => 'nullable|string',
            'campaign_id' => 'nullable|string',
            'adset_id' => 'nullable|string',
            'ad_id' => 'nullable|string',
            'form_id' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:500',
        ]);

        $query = AdsLeadSubmission::query()->orderByDesc('created_time')->orderByDesc('id');

        foreach (['platform', 'account_id', 'campaign_id', 'adset_id', 'ad_id', 'form_id'] as $k) {
            if (! empty($validated[$k])) {
                $query->where($k, $validated[$k]);
            }
        }

        if (! empty($validated['date_from'])) {
            $query->where('created_time', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->where('created_time', '<=', $validated['date_to'] . ' 23:59:59');
        }

        return response()->json($query->paginate($validated['per_page'] ?? 100));
    }

    public function triggerSync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string|in:meta,tiktok,snap',
            'account_id' => 'required|string',
            'campaign_id' => 'nullable|string',
            'adset_id' => 'nullable|string',
            'ad_id' => 'nullable|string',
            'form_id' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $account = AdsPlatformAccount::where('platform', $validated['platform'])
            ->where('account_id', $validated['account_id'])
            ->where('is_active', true)
            ->first();

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'Ads platform account not found or inactive.',
            ], 404);
        }

        SyncLeadsJob::dispatch(
            $validated['platform'],
            $validated['account_id'],
            $validated['campaign_id'] ?? null,
            $validated['adset_id'] ?? null,
            $validated['ad_id'] ?? null,
            $validated['form_id'] ?? null,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        );

        return response()->json(['message' => 'Lead sync job dispatched.']);
    }

    public function export(Request $request): BinaryFileResponse|JsonResponse|Response
    {
        $validated = $request->validate([
            'platform' => 'required|string|in:meta,tiktok,snap',
            'format' => 'nullable|string|in:excel|xlsx',
            'form_id' => 'nullable|string',
            'ad_id' => 'nullable|string',
            'advertiser_id' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sync' => 'nullable|boolean',
        ]);

        $platform = $validated['platform'];
        $formId = $validated['form_id'] ?? null;
        $adId = $validated['ad_id'] ?? null;
        $advertiserId = $validated['advertiser_id'] ?? config('ads_platforms.tiktok.advertiser_id');

        if ($platform === 'tiktok' && empty($advertiserId)) {
            return response()->json([
                'success' => false,
                'message' => 'For TikTok, advertiser_id is required (query param or ads_platforms.tiktok.advertiser_id).',
            ], 422);
        }

        if ($platform === 'snap' && empty($advertiserId) && empty(config('ads_platforms.snap.ad_account_id'))) {
            return response()->json([
                'success' => false,
                'message' => 'For Snap, advertiser_id (ad account id) is required (query param or ads_platforms.snap.ad_account_id).',
            ], 422);
        }

        if ($platform === 'meta' && ! $formId && ! $adId) {
            return response()->json([
                'success' => false,
                'message' => 'For Meta, either form_id or ad_id is required.',
            ], 422);
        }

        $leads = $this->fetchLeads(
            $platform,
            $formId,
            $adId,
            $advertiserId,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        );

        if (! empty($validated['sync']) && ! empty($leads)) {
            $this->platformLeadSync->sync($leads);
        }

        $filename = sprintf('leads_%s_%s.xlsx', $platform, now()->format('Y-m-d'));

        return Excel::download(
            new PlatformLeadsExport($leads),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    /**
     * POST /api/ads/leads/export-snap – upload Snap-exported CSV and download as Excel.
     */
    public function exportSnap(Request $request): BinaryFileResponse|JsonResponse
    {
        $validated = $request->validate([
            'csv' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $content = file_get_contents($validated['csv']->getRealPath());
        if ($content === false) {
            return response()->json(['success' => false, 'message' => 'Failed to read uploaded file.'], 422);
        }

        $leads = $this->snapCsvNormalizer->normalizeCsv($content);
        $filename = sprintf('leads_snap_%s.xlsx', now()->format('Y-m-d'));

        return Excel::download(
            new PlatformLeadsExport($leads),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchLeads(
        string $platform,
        ?string $formId,
        ?string $adId,
        ?string $advertiserId,
        ?string $dateFrom,
        ?string $dateTo,
    ): array {
        $fromTs = $dateFrom ? (int) strtotime($dateFrom) : null;
        $toTs = $dateTo ? (int) strtotime($dateTo . ' 23:59:59') : null;

        return match ($platform) {
            'meta' => $this->fetchMetaLeads($formId, $adId, $fromTs, $toTs),
            'tiktok' => $this->tikTokLeadGenReader->fetchLeads($advertiserId ?? '', $dateFrom, $dateTo),
            'snap' => $this->snapLeadGenReader->fetchLeads(
                $advertiserId ?? (string) config('ads_platforms.snap.ad_account_id'),
                $dateFrom,
                $dateTo,
            ),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchMetaLeads(?string $formId, ?string $adId, ?int $fromTs, ?int $toTs): array
    {
        if ($adId) {
            return $this->metaLeadGenReader->fetchByAdId($adId, $fromTs, $toTs);
        }

        return $this->metaLeadGenReader->fetchByFormId($formId ?? '', $fromTs, $toTs);
    }
}
