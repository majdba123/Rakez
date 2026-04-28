<?php

namespace App\Jobs\Ads;

use App\Domain\Ads\Ports\LeadStorePort;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\Meta\MetaLeadGenReader;
use App\Infrastructure\Ads\Persistence\Models\AdsAd;
use App\Infrastructure\Ads\Persistence\Models\AdsAdSet;
use App\Infrastructure\Ads\Persistence\Models\AdsSyncRun;
use App\Infrastructure\Ads\Snap\SnapLeadGenReader;
use App\Infrastructure\Ads\TikTok\TikTokLeadGenReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        public readonly string $platform,
        public readonly string $accountId,
        public readonly ?string $campaignId = null,
        public readonly ?string $adsetId = null,
        public readonly ?string $adId = null,
        public readonly ?string $formId = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
    ) {
        $this->queue = 'ads-sync';
    }

    public function handle(
        LeadStorePort $store,
        MetaLeadGenReader $metaReader,
        SnapLeadGenReader $snapReader,
        TikTokLeadGenReader $tikTokReader,
    ): void {
        $run = AdsSyncRun::create([
            'type' => 'leads',
            'platform' => $this->platform,
            'account_id' => $this->accountId,
            'status' => 'running',
            'started_at' => now(),
            'meta' => array_filter([
                'campaign_id' => $this->campaignId,
                'adset_id' => $this->adsetId,
                'ad_id' => $this->adId,
                'form_id' => $this->formId,
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
            ], fn ($v) => $v !== null && $v !== ''),
        ]);

        try {
            $platform = Platform::from($this->platform);
            $rows = match ($platform) {
                Platform::Meta => $this->fetchMeta($metaReader),
                Platform::Snap => $this->fetchSnap($snapReader),
                Platform::TikTok => $this->fetchTikTok($tikTokReader),
            };

            $result = $store->upsertLeadSubmissions($platform, $this->accountId, $rows);

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'meta' => array_merge($run->meta ?? [], $result),
            ]);

            Log::channel('ads')->info('Synced lead submissions', [
                'platform' => $this->platform,
                'account_id' => $this->accountId,
                'created' => $result['created'],
                'updated' => $result['updated'],
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => $e->getMessage(),
            ]);

            Log::channel('ads')->error('Lead sync failed', [
                'platform' => $this->platform,
                'account_id' => $this->accountId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchMeta(MetaLeadGenReader $reader): array
    {
        $fromTs = $this->dateFrom ? (int) strtotime($this->dateFrom) : null;
        $toTs = $this->dateTo ? (int) strtotime($this->dateTo . ' 23:59:59') : null;

        if ($this->adId) {
            return $reader->fetchByAdId($this->adId, $fromTs, $toTs, $this->accountId);
        }

        if ($this->formId) {
            return $reader->fetchByFormId($this->formId, $fromTs, $toTs, $this->accountId);
        }

        $adIds = $this->resolveMetaAdIdsForFilters();
        $rows = [];

        foreach (array_values(array_unique($adIds)) as $adId) {
            if ($adId === '') {
                continue;
            }
            foreach ($reader->fetchByAdId($adId, $fromTs, $toTs, $this->accountId) as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return string[]
     */
    private function resolveMetaAdIdsForFilters(): array
    {
        if ($this->adsetId) {
            return AdsAd::query()
                ->where('platform', 'meta')
                ->where('account_id', $this->accountId)
                ->where('ad_set_id', $this->adsetId)
                ->pluck('ad_id')
                ->all();
        }

        if ($this->campaignId) {
            $adsetIds = AdsAdSet::query()
                ->where('platform', 'meta')
                ->where('account_id', $this->accountId)
                ->where('campaign_id', $this->campaignId)
                ->pluck('ad_set_id')
                ->all();

            if (empty($adsetIds)) {
                return [];
            }

            return AdsAd::query()
                ->where('platform', 'meta')
                ->where('account_id', $this->accountId)
                ->whereIn('ad_set_id', $adsetIds)
                ->pluck('ad_id')
                ->all();
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSnap(SnapLeadGenReader $reader): array
    {
        $rows = $reader->fetchLeads($this->accountId, $this->dateFrom, $this->dateTo);

        return $this->filterLeadRows($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTikTok(TikTokLeadGenReader $reader): array
    {
        $rows = $reader->fetchLeads($this->accountId, $this->dateFrom, $this->dateTo, $this->accountId);

        return $this->filterLeadRows($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function filterLeadRows(array $rows): array
    {
        return array_values(array_filter($rows, function (array $row): bool {
            if ($this->campaignId && ($row['campaign_id'] ?? null) !== $this->campaignId) {
                return false;
            }
            if ($this->adsetId && ($row['adset_id'] ?? null) !== $this->adsetId) {
                return false;
            }
            if ($this->adId && ($row['ad_id'] ?? null) !== $this->adId) {
                return false;
            }
            if ($this->formId && ($row['form_id'] ?? null) !== $this->formId) {
                return false;
            }

            return true;
        }));
    }
}

