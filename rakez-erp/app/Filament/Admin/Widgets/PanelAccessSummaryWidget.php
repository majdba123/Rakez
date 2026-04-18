<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PanelAccessSummaryWidget extends StatsOverviewWidget
{
    use HasGovernanceAuthorization;

    protected static ?int $sort = 10;

    public static function canView(): bool
    {
        return static::canAccessGovernancePage('Overview', 'admin.dashboard.view');
    }

    protected function getStats(): array
    {
        $panelAuthorityRoles = config('governance.panel_authority_roles', [config('governance.super_admin_role', 'super_admin')]);
        $stats = [];

        foreach ($panelAuthorityRoles as $role) {
            $count = User::role($role)->where('is_active', true)->count();
            if ($count > 0) {
                $stats[] = Stat::make(
                    str($role)->replace('_', ' ')->title()->toString(),
                    (string) $count,
                )->description('Active users');
            }
        }

        $inactiveGov = User::role($panelAuthorityRoles)->where('is_active', false)->count();
        if ($inactiveGov > 0) {
            $stats[] = Stat::make('Inactive Panel Authority', (string) $inactiveGov)
                ->description('Deactivated top-level admin users')
                ->color('danger');
        }

        return $stats;
    }
}
