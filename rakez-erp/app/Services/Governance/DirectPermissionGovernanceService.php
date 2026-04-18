<?php

namespace App\Services\Governance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class DirectPermissionGovernanceService
{
    public function __construct(
        protected GovernanceCatalog $catalog,
        protected GovernanceAuditLogger $audit,
    ) {}

    public function sync(User $user, array $permissions): User
    {
        $this->guardSuperAdminTarget($user);
        $this->guardPostponedPermissions($permissions);

        return DB::transaction(function () use ($user, $permissions): User {
            $before = $user->permissions()->pluck('name')->sort()->values()->all();

            $filtered = array_values(array_intersect($permissions, $this->catalog->activeDatabasePermissionNames()));

            $user->syncPermissions($filtered);

            // Clear Spatie permission cache to ensure fresh queries reflect the sync
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            $this->audit->log('governance.user.direct_permissions_synced', $user, [
                'before' => $before,
                'after' => $user->fresh()->permissions()->pluck('name')->sort()->values()->all(),
            ]);

            return $user->fresh(['roles', 'permissions']);
        });
    }

    public function grant(User $user, string $permission): User
    {
        $permissions = $user->permissions()
            ->pluck('name')
            ->push($permission)
            ->unique()
            ->values()
            ->all();

        return $this->sync($user, $permissions);
    }

    public function revoke(User $user, string $permission): User
    {
        $permissions = $user->permissions()
            ->pluck('name')
            ->reject(fn (string $assignedPermission): bool => $assignedPermission === $permission)
            ->values()
            ->all();

        return $this->sync($user, $permissions);
    }

    protected function guardSuperAdminTarget(User $target): void
    {
        $superAdminRole = config('governance.super_admin_role', 'super_admin');

        if (! $target->hasRole($superAdminRole)) {
            return;
        }

        $actor = auth()->user();

        if ($actor instanceof User && $actor->hasRole($superAdminRole)) {
            return;
        }

        throw new \DomainException('Only admin can modify direct permissions for a top-level admin user.');
    }

    protected function guardPostponedPermissions(array $permissions): void
    {
        $blocked = array_values(array_intersect($permissions, $this->catalog->postponedPanelPermissions()));

        if ($blocked === []) {
            return;
        }

        throw new \DomainException(
            'Postponed governance permissions cannot be assigned: '.implode(', ', $blocked)
        );
    }
}
