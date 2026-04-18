<?php

namespace App\Services\Governance;

use App\Models\User;
use Filament\Panel;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class GovernanceAccessService
{
    public function __construct(
        protected GovernanceCatalog $catalog,
        protected GovernanceTemporaryPermissionService $temporaryPermissions,
    ) {}

    public function canAccessPanel(?User $user, ?Panel $panel = null): bool
    {
        if (! $user || ! $user->exists || ! $user->is_active) {
            return false;
        }

        if (method_exists($user, 'trashed') && $user->trashed()) {
            return false;
        }

        if ($panel && $panel->getId() !== config('governance.panel_id')) {
            return false;
        }

        $superAdminRole = config('governance.super_admin_role', 'super_admin');
        $panelAuthorityRoles = config('governance.panel_authority_roles', [$superAdminRole]);

        return $user->hasAnyRole($panelAuthorityRoles);
    }

    public function allows(?User $user, string $permission): bool
    {
        if (! $this->canAccessPanel($user)) {
            return false;
        }

        if (! $this->catalog->isActivePanelPermission($permission)) {
            return false;
        }

        if ($user->hasRole(config('governance.super_admin_role'))) {
            return true;
        }

        return $this->hasPermission($user, $permission);
    }

    public function isReadOnly(?User $user): bool
    {
        if (! $user || $user->hasRole(config('governance.super_admin_role'))) {
            return false;
        }

        return $user->hasRole('auditor_readonly');
    }

    protected function hasPermission(User $user, string $permission): bool
    {
        if (! $this->catalog->isActivePanelPermission($permission)) {
            return false;
        }

        if ($this->temporaryPermissions->userHasActiveTemporary($user, $permission)) {
            return true;
        }

        try {
            return $user->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
