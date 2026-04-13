<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Widgets\SalesOverviewStatsWidget;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class SalesOverview extends Dashboard
{
    use HasGovernanceAuthorization;

    protected static string $routePath = '/sales-overview';

    protected static ?string $title = 'Sales Overview';

    protected static ?string $navigationLabel = 'Sales Overview';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string | \UnitEnum | null $navigationGroup = 'Sales Oversight';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return static::canAccessGovernancePage('Sales Oversight', 'sales.dashboard.view');
    }

    public function getWidgets(): array
    {
        return [
            SalesOverviewStatsWidget::class,
        ];
    }
}
