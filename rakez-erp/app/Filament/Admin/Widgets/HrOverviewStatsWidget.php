<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\EmployeeContract;
use App\Models\EmployeeWarning;
use App\Models\Team;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HrOverviewStatsWidget extends StatsOverviewWidget
{
    use HasGovernanceAuthorization;

    public static function canView(): bool
    {
        return static::canAccessGovernancePage('HR Oversight', 'hr.dashboard.view');
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Teams', (string) Team::count())
                ->description('Organizational teams'),
            Stat::make('Active Employees', (string) User::query()->where('is_active', true)->count())
                ->description('Users currently marked active'),
            Stat::make('Employee Warnings', (string) EmployeeWarning::count())
                ->description('Captured warning records'),
            Stat::make('Active Contracts', (string) EmployeeContract::active()->count())
                ->description('Active HR contracts'),
        ];
    }
}
