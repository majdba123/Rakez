<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\DeveloperMarketingPlan;
use App\Models\Lead;
use App\Models\MarketingProject;
use App\Models\MarketingTask;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MarketingOverviewStatsWidget extends StatsOverviewWidget
{
    use HasGovernanceAuthorization;

    public static function canView(): bool
    {
        return static::canAccessGovernancePage('Marketing Oversight', 'marketing.dashboard.view');
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Marketing Projects', (string) MarketingProject::count())
                ->description('Projects active in marketing'),
            Stat::make('Developer Plans', (string) DeveloperMarketingPlan::count())
                ->description('Developer marketing budget plans'),
            Stat::make('Open Tasks', (string) MarketingTask::query()->where('status', '!=', 'completed')->count())
                ->description('Marketing tasks not completed yet'),
            Stat::make('Leads', (string) Lead::count())
                ->description('Leads connected to projects'),
        ];
    }
}
