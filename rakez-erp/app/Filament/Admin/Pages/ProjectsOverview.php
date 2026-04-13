<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Widgets\ProjectsOverviewStatsWidget;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class ProjectsOverview extends Dashboard
{
    use HasGovernanceAuthorization;

    protected static string $routePath = '/projects-overview';

    protected static ?string $title = 'Projects Overview';

    protected static ?string $navigationLabel = 'Projects Overview';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static string | \UnitEnum | null $navigationGroup = 'Contracts & Projects';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return static::canAccessGovernancePageAny('Contracts & Projects', [
            'contracts.view_all',
            'exclusive_projects.view',
            'projects.view',
        ]);
    }

    public function getWidgets(): array
    {
        return [
            ProjectsOverviewStatsWidget::class,
        ];
    }
}
