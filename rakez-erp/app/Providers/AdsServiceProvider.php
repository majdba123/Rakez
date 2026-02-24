<?php

namespace App\Providers;

use App\Domain\Ads\Ports\InsightStorePort;
use App\Domain\Ads\Ports\OutcomeStorePort;
use App\Domain\Ads\Ports\TokenStorePort;
use App\Infrastructure\Ads\Meta\MetaInsightsReader;
use App\Infrastructure\Ads\Meta\MetaConversionsWriter;
use App\Infrastructure\Ads\Meta\MetaCrmWriter;
use App\Infrastructure\Ads\Snap\SnapInsightsReader;
use App\Infrastructure\Ads\Snap\SnapConversionsWriter;
use App\Infrastructure\Ads\TikTok\TikTokInsightsReader;
use App\Infrastructure\Ads\TikTok\TikTokEventsWriter;
use App\Infrastructure\Ads\Persistence\EloquentInsightStore;
use App\Infrastructure\Ads\Persistence\EloquentOutcomeStore;
use App\Infrastructure\Ads\Persistence\EloquentTokenStore;
use App\Infrastructure\Ads\Services\EventIdGenerator;
use App\Infrastructure\Ads\Services\HashingService;
use App\Infrastructure\Ads\EventMapping\PlatformEventMapper;
use App\Domain\Ads\Ports\AdsReadPort;
use App\Domain\Ads\Ports\AdsWritePort;
use App\Jobs\Ads\PublishOutcomeEventsJob;
use App\Jobs\Ads\SyncCampaignStructureJob;
use App\Jobs\Ads\SyncInsightsJob;
use App\Jobs\Marketing\RefreshCampaignPerformanceCacheJob;
use App\Jobs\Sales\RefreshEmployeeScoresJob;
use App\Infrastructure\Ads\Persistence\Models\AdsPlatformAccount;
use App\Services\Marketing\AI\CampaignPerformanceAggregator;
use App\Services\Marketing\AI\BudgetDistributionOptimizer;
use App\Services\Marketing\AI\LeadFunnelAnalyzer;
use App\Services\Sales\AI\EmployeeSuccessScorer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class AdsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HashingService::class);
        $this->app->singleton(EventIdGenerator::class);
        $this->app->singleton(PlatformEventMapper::class, fn () => PlatformEventMapper::fromConfig());

        $this->app->singleton(TokenStorePort::class, EloquentTokenStore::class);
        $this->app->singleton(InsightStorePort::class, EloquentInsightStore::class);
        $this->app->singleton(OutcomeStorePort::class, EloquentOutcomeStore::class);

        $this->app->singleton(AdsReadPort::class . '.meta', fn () => $this->app->make(MetaInsightsReader::class));
        $this->app->singleton(AdsReadPort::class . '.snap', fn () => $this->app->make(SnapInsightsReader::class));
        $this->app->singleton(AdsReadPort::class . '.tiktok', fn () => $this->app->make(TikTokInsightsReader::class));

        $this->app->singleton(AdsWritePort::class . '.meta', fn () => $this->app->make(MetaConversionsWriter::class));
        $this->app->singleton(AdsWritePort::class . '.snap', fn () => $this->app->make(SnapConversionsWriter::class));
        $this->app->singleton(AdsWritePort::class . '.tiktok', fn () => $this->app->make(TikTokEventsWriter::class));

        $this->app->singleton(AdsWritePort::class . '.meta_crm', fn () => $this->app->make(MetaCrmWriter::class));

        $this->app->singleton(CampaignPerformanceAggregator::class);
        $this->app->singleton(EmployeeSuccessScorer::class);
        $this->app->singleton(LeadFunnelAnalyzer::class);
        $this->app->singleton(BudgetDistributionOptimizer::class);
    }

    public function boot(): void
    {
        $this->app->booted(function () {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            $hours = (int) config('ads_platforms.sync.campaign_structure_interval', 6);
            $schedule->command("ads:sync sync-campaigns")->cron("0 */{$hours} * * *");

            $schedule->command("ads:sync sync-insights")->dailyAt('04:00');

            $seconds = (int) config('ads_platforms.sync.outcome_publish_interval_seconds', 60);
            $schedule->command("ads:sync publish-outcomes")->everyMinute();

            $schedule->job(new RefreshEmployeeScoresJob)->dailyAt('02:00');
            $schedule->job(new RefreshCampaignPerformanceCacheJob)->everySixHours();
        });
    }
}
