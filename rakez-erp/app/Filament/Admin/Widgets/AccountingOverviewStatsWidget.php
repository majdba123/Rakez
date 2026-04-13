<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\AccountingSalaryDistribution;
use App\Models\CommissionDistribution;
use App\Models\Deposit;
use App\Models\SalesReservation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingOverviewStatsWidget extends StatsOverviewWidget
{
    use HasGovernanceAuthorization;

    public static function canView(): bool
    {
        return static::canAccessGovernancePage('Accounting & Finance', 'accounting.dashboard.view');
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Pending Deposits', (string) Deposit::pending()->count())
                ->description('Deposits awaiting accounting action'),
            Stat::make('Pending Commissions', (string) CommissionDistribution::pending()->count())
                ->description('Commission distributions still pending'),
            Stat::make('Pending Salaries', (string) AccountingSalaryDistribution::pending()->count())
                ->description('Salary distributions not finalized'),
            Stat::make('Sold Units', (string) SalesReservation::query()->where('status', 'confirmed')->count())
                ->description('Confirmed reservations visible to accounting'),
        ];
    }
}
