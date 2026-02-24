<?php

namespace App\Console\Commands;

use App\Infrastructure\Ads\Persistence\Models\AdsPlatformAccount;
use App\Jobs\Ads\PublishOutcomeEventsJob;
use App\Jobs\Ads\SyncCampaignStructureJob;
use App\Jobs\Ads\SyncInsightsJob;
use Illuminate\Console\Command;

class AdsSyncCommand extends Command
{
    protected $signature = 'ads:sync
        {action : sync-campaigns|sync-insights|publish-outcomes}
        {--platform= : meta|snap|tiktok (empty = all active)}
        {--account= : specific account ID}
        {--days=7 : lookback days for insights}';

    protected $description = 'Dispatch Ads platform sync jobs';

    public function handle(): int
    {
        $action = $this->argument('action');
        $platformFilter = $this->option('platform');
        $accountFilter = $this->option('account');

        if ($action === 'publish-outcomes') {
            PublishOutcomeEventsJob::dispatch();
            $this->info('Dispatched outcome publishing job.');

            return self::SUCCESS;
        }

        $query = AdsPlatformAccount::where('is_active', true);
        if ($platformFilter) {
            $query->where('platform', $platformFilter);
        }
        if ($accountFilter) {
            $query->where('account_id', $accountFilter);
        }

        $accounts = $query->get();
        if ($accounts->isEmpty()) {
            $this->warn('No active platform accounts found.');

            return self::FAILURE;
        }

        foreach ($accounts as $account) {
            match ($action) {
                'sync-campaigns' => SyncCampaignStructureJob::dispatch(
                    $account->platform,
                    $account->account_id,
                ),
                'sync-insights' => SyncInsightsJob::dispatch(
                    $account->platform,
                    $account->account_id,
                    now()->subDays((int) $this->option('days'))->toDateString(),
                    now()->toDateString(),
                    ['campaign', 'adset', 'ad'],
                ),
                default => $this->error("Unknown action: {$action}"),
            };

            $this->info("Dispatched {$action} for {$account->platform}:{$account->account_id}");
        }

        return self::SUCCESS;
    }
}
