<?php

namespace App\Http\Controllers\Ads;

use App\Infrastructure\Ads\Persistence\Models\AdsExport;
use App\Jobs\Ads\GenerateAdsExportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdsExportsController
{
    /**
     * GET /api/ads/exports
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|string',
            'status' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $query = AdsExport::query()->orderByDesc('id');
        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return response()->json($query->paginate($validated['per_page'] ?? 50));
    }

    /**
     * POST /api/ads/exports/leads
     * Create a queued campaign-level leads CSV export from DB-synced lead submissions.
     */
    public function createLeadsCsv(Request $request): JsonResponse
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
            'filename' => 'nullable|string|max:200',
        ]);

        $export = AdsExport::create([
            'type' => 'leads_csv',
            'status' => 'queued',
            'filters' => array_filter([
                'platform' => $validated['platform'],
                'account_id' => $validated['account_id'],
                'campaign_id' => $validated['campaign_id'] ?? null,
                'adset_id' => $validated['adset_id'] ?? null,
                'ad_id' => $validated['ad_id'] ?? null,
                'form_id' => $validated['form_id'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            'download_filename' => $validated['filename'] ?? null,
            'requested_by_user_id' => $request->user()?->id,
        ]);

        GenerateAdsExportJob::dispatch($export->id);

        return response()->json([
            'export_id' => $export->id,
            'status' => $export->status,
        ], 201);
    }

    /**
     * GET /api/ads/exports/{id}
     */
    public function show(int $id): JsonResponse
    {
        $export = AdsExport::findOrFail($id);

        return response()->json($export);
    }

    /**
     * GET /api/ads/exports/{id}/download
     */
    public function download(int $id): BinaryFileResponse|StreamedResponse|JsonResponse
    {
        $export = AdsExport::findOrFail($id);
        if ($export->status !== 'completed' || ! $export->storage_path) {
            return response()->json([
                'success' => false,
                'message' => 'Export is not ready.',
                'status' => $export->status,
            ], 409);
        }

        $disk = $export->storage_disk ?: 'local';
        if (! Storage::disk($disk)->exists($export->storage_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Export file is missing from storage.',
            ], 404);
        }

        $filename = $export->download_filename ?: basename($export->storage_path);

        return Storage::disk($disk)->download($export->storage_path, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
