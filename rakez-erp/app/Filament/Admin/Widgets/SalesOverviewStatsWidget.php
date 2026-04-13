<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\SalesAttendanceSchedule;
use App\Models\SalesProjectAssignment;
use App\Models\SalesReservation;
use App\Models\SalesTarget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesOverviewStatsWidget extends StatsOverviewWidget
{
    use HasGovernanceAuthorization;

    public static function canView(): bool
    {
        return static::canAccessGovernancePage('Sales Oversight', 'sales.dashboard.view');
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Active Reservations', (string) SalesReservation::active()->count())
                ->description('Negotiation and confirmed reservations'),
            Stat::make('Targets', (string) SalesTarget::count())
                ->description('Active sales target records'),
            Stat::make('Assignments', (string) SalesProjectAssignment::active()->count())
                ->description('Current project-team assignments'),
            Stat::make('Upcoming Schedules', (string) SalesAttendanceSchedule::query()->whereDate('schedule_date', '>=', now()->toDateString())->count())
                ->description('Scheduled sales attendance entries'),
        ];
    }
}
