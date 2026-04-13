<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Widgets\CreditOverviewStatsWidget;
use App\Filament\Admin\Widgets\RecentCreditAuditWidget;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class CreditOverview extends Dashboard
{
    use HasGovernanceAuthorization;

    protected static string $routePath = '/credit-overview';

    protected static ?string $title = 'Credit Overview';

    protected static ?string $navigationLabel = 'Credit Overview';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string | \UnitEnum | null $navigationGroup = 'Credit Oversight';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return static::canAccessGovernancePage('Credit Oversight', 'credit.dashboard.view');
    }

    public function getWidgets(): array
    {
        return [
            CreditOverviewStatsWidget::class,
            RecentCreditAuditWidget::class,
        ];
    }
}
