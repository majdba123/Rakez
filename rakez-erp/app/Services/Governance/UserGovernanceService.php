<?php

namespace App\Services\Governance;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserGovernanceService
{
    public function __construct(
        protected GovernanceCatalog $catalog,
        protected GovernanceAuditLogger $audit,
        protected DirectPermissionGovernanceService $directPermissions,
    ) {}

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $attributes = $this->extractUserAttributes($data);
            $attributes['password'] = Hash::make($data['password']);
            $attributes['is_active'] = $attributes['is_active'] ?? true;

            $user = User::create($attributes);

            $this->syncManagedRoles($user, $data);
            $this->syncDirectPermissions($user, $data);

            $this->audit->log('governance.user.created', $user, [
                'roles' => $user->roles()->pluck('name')->sort()->values()->all(),
            ]);

            return $user->fresh(['roles', 'permissions', 'team']);
        });
    }

    public function update(User $user, array $data): User
    {
        $this->guardSuperAdminTarget($user, 'update');

        return DB::transaction(function () use ($user, $data): User {
            $before = [
                'type' => $user->type,
                'is_manager' => $user->is_manager,
                'is_active' => $user->is_active,
                'team_id' => $user->team_id,
                'roles' => $user->roles()->pluck('name')->sort()->values()->all(),
            ];

            $attributes = $this->extractUserAttributes($data);

            if (filled($data['password'] ?? null)) {
                $attributes['password'] = Hash::make($data['password']);
            }

            $user->update($attributes);

            $this->syncManagedRoles($user, $data);
            $this->syncDirectPermissions($user, $data);

            $afterUser = $user->fresh();

            $this->audit->log('governance.user.updated', $user, [
                'before' => $before,
                'after' => [
                    'type' => $afterUser->type,
                    'is_manager' => $afterUser->is_manager,
                    'is_active' => $afterUser->is_active,
                    'team_id' => $afterUser->team_id,
                    'roles' => $afterUser->roles()->pluck('name')->sort()->values()->all(),
                ],
            ]);

            return $user->fresh(['roles', 'permissions', 'team']);
        });
    }

    public function delete(User $user): void
    {
        $this->guardSuperAdminTarget($user, 'delete');

        DB::transaction(function () use ($user): void {
            $user->delete();

            $this->audit->log('governance.user.deleted', $user);
        });
    }

    public function restore(User $user): User
    {
        $this->guardSuperAdminTarget($user, 'restore');

        return DB::transaction(function () use ($user): User {
            $user->restore();

            $this->audit->log('governance.user.restored', $user);

            return $user->fresh(['roles', 'permissions', 'team']);
        });
    }

    protected function guardSuperAdminTarget(User $target, string $action): void
    {
        $superAdminRole = config('governance.super_admin_role', 'super_admin');

        if (! $target->hasRole($superAdminRole)) {
            return;
        }

        $actor = auth()->user();

        if ($actor instanceof User && $actor->hasRole($superAdminRole)) {
            return;
        }

        $this->audit->log('governance.user.super_admin.protected', $target, [
            'attempted_action' => $action,
            'target_roles' => $target->roles()->pluck('name')->sort()->values()->all(),
        ], $actor instanceof User ? $actor : null);

        throw new \DomainException('Only a super_admin can modify or delete another super_admin user.');
    }

    protected function extractUserAttributes(array $data): array
    {
        if (array_key_exists('team', $data) && ! array_key_exists('team_id', $data)) {
            $data['team_id'] = $data['team'];
        }

        return Arr::only($data, [
            'name',
            'email',
            'phone',
            'type',
            'is_manager',
            'team_id',
            'is_active',
        ]);
    }

    protected function syncManagedRoles(User $user, array $data): void
    {
        $superAdminRole = config('governance.super_admin_role', 'super_admin');
        $actor = auth()->user();
        $actorIsSuperAdmin = $actor instanceof User && $actor->hasRole($superAdminRole);

        $selectedGovernanceRoles = array_values(array_intersect(
            $data['governance_roles'] ?? [],
            config('governance.managed_panel_roles', []),
        ));

        if (! $actorIsSuperAdmin) {
            $selectedGovernanceRoles = array_values(array_diff($selectedGovernanceRoles, [$superAdminRole]));
        }

        $preservedRoles = $user->roles()
            ->pluck('name')
            ->reject(fn (string $role): bool => $this->catalog->isOperationalRole($role) || $this->catalog->isManagedGovernanceRole($role))
            ->values()
            ->all();

        $operationalRole = $this->resolveOperationalRole(
            $data['type'] ?? $user->type,
            (bool) ($data['is_manager'] ?? $user->is_manager),
        );

        $roles = array_values(array_unique(array_filter([
            ...$preservedRoles,
            $operationalRole,
            ...$selectedGovernanceRoles,
        ])));

        $user->syncRoles($roles);
    }

    protected function syncDirectPermissions(User $user, array $data): void
    {
        if (! array_key_exists('direct_permissions', $data)) {
            return;
        }

        $this->directPermissions->sync($user, $data['direct_permissions'] ?? []);
    }

    protected function resolveOperationalRole(?string $type, bool $isManager): ?string
    {
        if ($type === 'sales' && $isManager) {
            return 'sales_leader';
        }

        if ($type === 'user') {
            return 'default';
        }

        return $type;
    }
}
