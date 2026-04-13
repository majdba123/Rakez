<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Widgets\AccountingOverviewStatsWidget;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class AccountingOverview extends Dashboard
{
    use HasGovernanceAuthorization;

    protected static string $routePath = '/accounting-overview';

    protected static ?string $title = 'Accounting Overview';

    protected static ?string $navigationLabel = 'Accounting Overview';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string | \UnitEnum | null $navigationGroup = 'Accounting & Finance';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return static::canAccessGovernancePage('Accounting & Finance', 'accounting.dashboard.view');
    }

    public function getWidgets(): array
    {
        return [
            AccountingOverviewStatsWidget::class,
        ];
    }
}
