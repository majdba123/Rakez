<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Widgets\MarketingOverviewStatsWidget;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class MarketingOverview extends Dashboard
{
    use HasGovernanceAuthorization;

    protected static string $routePath = '/marketing-overview';

    protected static ?string $title = 'Marketing Overview';

    protected static ?string $navigationLabel = 'Marketing Overview';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string | \UnitEnum | null $navigationGroup = 'Marketing Oversight';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return static::canAccessGovernancePage('Marketing Oversight', 'marketing.dashboard.view');
    }

    public function getWidgets(): array
    {
        return [
            MarketingOverviewStatsWidget::class,
        ];
    }
}
