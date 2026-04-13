<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Widgets\ApprovalsCenterSummaryWidget;
use App\Filament\Admin\Widgets\PendingRequestNotificationsWidget;
use App\Filament\Admin\Widgets\PendingWorkflowTasksWidget;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class ApprovalsCenter extends Dashboard
{
    use HasGovernanceAuthorization;

    protected static string $routePath = '/approvals-center';

    protected static ?string $title = 'Approvals Center';

    protected static ?string $navigationLabel = 'Approvals Center';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string | \UnitEnum | null $navigationGroup = 'Requests & Workflow';

    protected static ?int $navigationSort = -50;

    public static function canAccess(): bool
    {
        return static::canAccessGovernancePage('Requests & Workflow', 'governance.approvals.center.view');
    }

    public function getWidgets(): array
    {
        return [
            ApprovalsCenterSummaryWidget::class,
            PendingWorkflowTasksWidget::class,
            PendingRequestNotificationsWidget::class,
        ];
    }
}
