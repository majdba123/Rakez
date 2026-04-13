<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Widgets\WorkflowOverviewStatsWidget;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class WorkflowOverview extends Dashboard
{
    use HasGovernanceAuthorization;

    protected static string $routePath = '/workflow-overview';

    protected static ?string $title = 'Workflow Overview';

    protected static ?string $navigationLabel = 'Workflow Overview';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string | \UnitEnum | null $navigationGroup = 'Requests & Workflow';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return static::canAccessGovernancePageAny('Requests & Workflow', [
            'governance.oversight.workflow.view',
            'governance.approvals.center.view',
        ]);
    }

    public function getWidgets(): array
    {
        return [
            WorkflowOverviewStatsWidget::class,
        ];
    }
}
