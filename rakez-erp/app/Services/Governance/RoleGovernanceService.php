<?php

namespace App\Services\Governance;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleGovernanceService
{
    public function __construct(
        protected GovernanceCatalog $catalog,
        protected GovernanceAuditLogger $audit,
    ) {}

    public function syncPermissions(Role $role, array $permissions): Role
    {
        if (! $this->catalog->isEditableRole($role->name)) {
            throw new \DomainException("Role [{$role->name}] is not editable through the governance panel.");
        }

        $superAdminRole = config('governance.super_admin_role', 'super_admin');
        if ($role->name === $superAdminRole) {
            $actor = auth()->user();
            if (! $actor instanceof \App\Models\User || ! $actor->hasRole($superAdminRole)) {
                $this->audit->log('governance.role.super_admin.protected', $role, [
                    'attempted_action' => 'sync_permissions',
                    'role' => $role->name,
                ], $actor instanceof \App\Models\User ? $actor : null);

                throw new \DomainException("Only a super_admin can modify the [{$superAdminRole}] role permissions.");
            }
        }

        $blocked = array_values(array_intersect($permissions, $this->catalog->postponedPanelPermissions()));
        if ($blocked !== []) {
            throw new \DomainException(
                'Postponed governance permissions cannot be assigned: '.implode(', ', $blocked)
            );
        }

        return DB::transaction(function () use ($role, $permissions): Role {
            $before = $role->permissions()->pluck('name')->sort()->values()->all();

            $filtered = array_values(array_intersect($permissions, $this->catalog->activePanelPermissionNames()));

            $role->syncPermissions($filtered);

            $this->audit->log('governance.role.permissions_synced', $role, [
                'before' => $before,
                'after' => $role->fresh()->permissions()->pluck('name')->sort()->values()->all(),
            ]);

            return $role->fresh('permissions');
        });
    }
}
