<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\ContractWorkflowStatus;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\Contract;
use App\Models\ExclusiveProjectRequest;
use App\Models\ProjectMedia;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectsOverviewStatsWidget extends StatsOverviewWidget
{
    use HasGovernanceAuthorization;

    public static function canView(): bool
    {
        return static::canAccessGovernancePageAny('Contracts & Projects', [
            'contracts.view_all',
            'exclusive_projects.view',
            'projects.view',
        ]);
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Contracts', (string) Contract::count())
                ->description('All project contracts'),
            Stat::make('Contract Info Completed', (string) Contract::query()->where('status', ContractWorkflowStatus::Completed->value)->count())
                ->description('Contracts with completed contract-info lifecycle'),
            Stat::make('Marketing Ready Projects', (string) Contract::query()->whereHas('marketingProject')->count())
                ->description('Contracts handed off to marketing as ready'),
            Stat::make('Exclusive Requests', (string) ExclusiveProjectRequest::count())
                ->description('Exclusive project workflow records'),
            Stat::make('Project Media', (string) ProjectMedia::count())
                ->description('Uploaded media linked to contracts'),
        ];
    }
}
