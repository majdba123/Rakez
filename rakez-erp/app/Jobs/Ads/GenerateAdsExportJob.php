<?php

namespace App\Jobs\Ads;

use App\Infrastructure\Ads\Persistence\Models\AdsAd;
use App\Infrastructure\Ads\Persistence\Models\AdsAdSet;
use App\Infrastructure\Ads\Persistence\Models\AdsCampaign;
use App\Infrastructure\Ads\Persistence\Models\AdsExport;
use App\Infrastructure\Ads\Persistence\Models\AdsLeadSubmission;
use App\Infrastructure\Ads\Persistence\Models\AdsSyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateAdsExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(
        public readonly int $exportId,
    ) {
        $this->queue = 'ads-export';
    }

    public function handle(): void
    {
        $export = AdsExport::find($this->exportId);
        if (! $export) {
            return;
        }

        $run = AdsSyncRun::create([
            'type' => 'exports',
            'platform' => null,
            'account_id' => null,
            'status' => 'running',
            'started_at' => now(),
            'meta' => ['export_id' => $export->id, 'export_type' => $export->type],
        ]);

        $export->update([
            'status' => 'running',
            'started_at' => now(),
            'last_error' => null,
        ]);

        $tmpFile = null;

        try {
            if ($export->type !== 'leads_csv') {
                throw new \RuntimeException("Unsupported export type: {$export->type}");
            }

            $filters = $export->filters ?? [];
            $disk = $export->storage_disk ?: 'local';

            $path = sprintf('exports/ads/leads/leads_%d_%s.csv', $export->id, now()->format('Ymd_His'));

            $tmpFile = tempnam(sys_get_temp_dir(), 'ads_export_');
            if ($tmpFile === false) {
                throw new \RuntimeException('Failed to create temporary export file.');
            }

            $fh = fopen($tmpFile, 'w');
            if ($fh === false) {
                throw new \RuntimeException('Failed to open export file for writing.');
            }

            $campaignNames = $this->loadCampaignNames($filters);
            $adsetNames = $this->loadAdSetNames($filters);
            $adNames = $this->loadAdNames($filters);

            fputcsv($fh, [
                'platform',
                'account_id',
                'campaign_id',
                'campaign_name',
                'adset_id',
                'adset_name',
                'ad_id',
                'ad_name',
                'form_id',
                'lead_id',
                'created_time',
                'name',
                'email',
                'phone',
                'extra_data',
            ]);

            $rowCount = 0;
            $this->buildLeadsQuery($filters)
                ->orderBy('id')
                ->chunkById(1000, function ($chunk) use ($fh, &$rowCount, $campaignNames, $adsetNames, $adNames) {
                    foreach ($chunk as $lead) {
                        /** @var AdsLeadSubmission $lead */
                        fputcsv($fh, [
                            $lead->platform,
                            $lead->account_id,
                            $lead->campaign_id,
                            $lead->campaign_id ? ($campaignNames[$lead->campaign_id] ?? '') : '',
                            $lead->adset_id,
                            $lead->adset_id ? ($adsetNames[$lead->adset_id] ?? '') : '',
                            $lead->ad_id,
                            $lead->ad_id ? ($adNames[$lead->ad_id] ?? '') : '',
                            $lead->form_id,
                            $lead->lead_id,
                            $lead->created_time?->toIso8601String() ?? '',
                            $lead->name,
                            $lead->email,
                            $lead->phone,
                            $lead->extra_data ? json_encode($lead->extra_data, JSON_UNESCAPED_UNICODE) : '',
                        ]);
                        $rowCount++;
                    }
                });

            fclose($fh);

            Storage::disk($disk)->put($path, fopen($tmpFile, 'r'));
            @unlink($tmpFile);
            $tmpFile = null;

            $filename = $export->download_filename ?: basename($path);

            $export->update([
                'status' => 'completed',
                'finished_at' => now(),
                'storage_path' => $path,
                'download_filename' => $filename,
                'row_count' => $rowCount,
            ]);

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'meta' => array_merge($run->meta ?? [], [
                    'row_count' => $rowCount,
                    'storage_disk' => $disk,
                    'storage_path' => $path,
                ]),
            ]);

            Log::channel('ads')->info('Ads export completed', [
                'export_id' => $export->id,
                'type' => $export->type,
                'row_count' => $rowCount,
            ]);
        } catch (\Throwable $e) {
            $export->update([
                'status' => 'failed',
                'finished_at' => now(),
                'retry_count' => (int) $export->retry_count + 1,
                'last_error' => $e->getMessage(),
            ]);

            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => $e->getMessage(),
            ]);

            Log::channel('ads')->error('Ads export failed', [
                'export_id' => $export->id,
                'type' => $export->type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if (is_string($tmpFile) && $tmpFile !== '') {
                @unlink($tmpFile);
            }
        }
    }

    private function buildLeadsQuery(array $filters)
    {
        $query = AdsLeadSubmission::query();

        foreach (['platform', 'account_id', 'campaign_id', 'adset_id', 'ad_id', 'form_id'] as $k) {
            if (! empty($filters[$k])) {
                $query->where($k, $filters[$k]);
            }
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_time', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_time', '<=', $filters['date_to'] . ' 23:59:59');
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    private function loadCampaignNames(array $filters): array
    {
        if (empty($filters['platform']) || empty($filters['account_id'])) {
            return [];
        }

        return AdsCampaign::query()
            ->where('platform', $filters['platform'])
            ->where('account_id', $filters['account_id'])
            ->pluck('name', 'campaign_id')
            ->filter()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function loadAdSetNames(array $filters): array
    {
        if (empty($filters['platform']) || empty($filters['account_id'])) {
            return [];
        }

        return AdsAdSet::query()
            ->where('platform', $filters['platform'])
            ->where('account_id', $filters['account_id'])
            ->pluck('name', 'ad_set_id')
            ->filter()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function loadAdNames(array $filters): array
    {
        if (empty($filters['platform']) || empty($filters['account_id'])) {
            return [];
        }

        return AdsAd::query()
            ->where('platform', $filters['platform'])
            ->where('account_id', $filters['account_id'])
            ->pluck('name', 'ad_id')
            ->filter()
            ->all();
    }
}
