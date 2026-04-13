<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Widgets\HrOverviewStatsWidget;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class HrOverview extends Dashboard
{
    use HasGovernanceAuthorization;

    protected static string $routePath = '/hr-overview';

    protected static ?string $title = 'HR Overview';

    protected static ?string $navigationLabel = 'HR Overview';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string | \UnitEnum | null $navigationGroup = 'HR Oversight';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return static::canAccessGovernancePage('HR Oversight', 'hr.dashboard.view');
    }

    public function getWidgets(): array
    {
        return [
            HrOverviewStatsWidget::class,
        ];
    }
}
