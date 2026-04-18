<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\GovernanceAuditLog;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class GovernanceOverviewWidget extends StatsOverviewWidget
{
    use HasGovernanceAuthorization;

    public static function canView(): bool
    {
        return static::canAccessGovernancePage('Overview', 'admin.dashboard.view');
    }

    protected function getStats(): array
    {
        $governanceRoles = config('governance.managed_governance_roles', []);
        $panelAuthorityRoles = config('governance.panel_authority_roles', [config('governance.super_admin_role', 'super_admin')]);
        $totalGovUsers = User::role($governanceRoles)->count();
        $activeGovUsers = User::role($governanceRoles)->where('is_active', true)->count();
        $panelAuthorityUsers = User::role($panelAuthorityRoles)->where('is_active', true)->count();

        $auditLast24h = GovernanceAuditLog::where('created_at', '>=', now()->subDay())->count();
        $auditTotal = GovernanceAuditLog::count();

        $usersWithDirect = User::has('permissions')->count();

        return [
            Stat::make('Governance Users', "{$activeGovUsers} / {$totalGovUsers}")
                ->description('Active / Total with governance roles'),
            Stat::make('Panel Authority', (string) $panelAuthorityUsers)
                ->description('Active top-level admin users'),
            Stat::make('Roles', (string) Role::count())
                ->description('Legacy and governance roles'),
            Stat::make('Permissions', (string) Permission::count())
                ->description('Frozen permission dictionary'),
            Stat::make('Audit (24h)', (string) $auditLast24h)
                ->description("{$auditTotal} total events")
                ->color($auditLast24h > 0 ? 'success' : 'gray'),
            Stat::make('Users with Direct Perms', (string) $usersWithDirect)
                ->description('Direct permission assignments'),
        ];
    }
}
