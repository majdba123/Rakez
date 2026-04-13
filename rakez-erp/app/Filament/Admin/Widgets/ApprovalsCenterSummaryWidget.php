<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\AdminNotification;
use App\Models\Task;
use App\Models\UserNotification;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ApprovalsCenterSummaryWidget extends StatsOverviewWidget
{
    use HasGovernanceAuthorization;

    public static function canView(): bool
    {
        return static::canAccessGovernancePage('Requests & Workflow', 'governance.approvals.center.view');
    }

    protected function getStats(): array
    {
        return [
            Stat::make('In-progress tasks', (string) Task::query()->where('status', Task::STATUS_IN_PROGRESS)->count())
                ->description('Workflow tasks'),
            Stat::make('Pending admin notifications', (string) AdminNotification::pending()->count()),
            Stat::make('Pending user notifications', (string) UserNotification::pending()->count()),
        ];
    }
}
