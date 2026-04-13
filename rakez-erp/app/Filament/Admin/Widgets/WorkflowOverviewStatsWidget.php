<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\AdminNotification;
use App\Models\ExclusiveProjectRequest;
use App\Models\Task;
use App\Models\UserNotification;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WorkflowOverviewStatsWidget extends StatsOverviewWidget
{
    use HasGovernanceAuthorization;

    public static function canView(): bool
    {
        return static::canAccessGovernancePageAny('Requests & Workflow', [
            'governance.oversight.workflow.view',
            'governance.approvals.center.view',
        ]);
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Open Tasks', (string) Task::query()->where('status', '!=', Task::STATUS_COMPLETED)->count())
                ->description('Tasks still in progress'),
            Stat::make('Admin Notifications', (string) AdminNotification::pending()->count())
                ->description('Unread admin notifications'),
            Stat::make('User Notifications', (string) UserNotification::pending()->count())
                ->description('Unread user notifications'),
            Stat::make('Pending Exclusive Requests', (string) ExclusiveProjectRequest::pending()->count())
                ->description('Workflow items awaiting project decision'),
        ];
    }
}
