<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InventoryOverviewStatsWidget extends StatsOverviewWidget
{
    use HasGovernanceAuthorization;

    public static function canView(): bool
    {
        return static::canAccessGovernancePage('Inventory Oversight', 'units.view');
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Total Units', (string) ContractUnit::withTrashed()->count())
                ->description('Inventory units linked to contracts'),
            Stat::make('Available Units', (string) ContractUnit::query()->where('status', 'available')->count())
                ->description('Units currently marked available'),
            Stat::make('Reserved Units', (string) ContractUnit::query()->whereIn('status', ['reserved', 'booked'])->count())
                ->description('Units reserved or booked'),
            Stat::make('Units With Sales Activity', (string) SalesReservation::query()->distinct('contract_unit_id')->count('contract_unit_id'))
                ->description('Units already visible to sales/credit'),
        ];
    }
}
