<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Widgets\InventoryOverviewStatsWidget;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class InventoryOverview extends Dashboard
{
    use HasGovernanceAuthorization;

    protected static string $routePath = '/inventory-overview';

    protected static ?string $title = 'Inventory Overview';

    protected static ?string $navigationLabel = 'Inventory Overview';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string | \UnitEnum | null $navigationGroup = 'Inventory Oversight';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return static::canAccessGovernancePage('Inventory Oversight', 'units.view');
    }

    public function getWidgets(): array
    {
        return [
            InventoryOverviewStatsWidget::class,
        ];
    }
}
