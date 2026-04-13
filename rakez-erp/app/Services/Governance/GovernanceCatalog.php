<?php

namespace App\Services\Governance;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GovernanceCatalog
{
    /**
     * Postponed permissions remain in the frozen dictionary, but must stay out of
     * active panel-facing governance surfaces until their rollout phase opens.
     *
     * @return array<int, string>
     */
    public function postponedPanelPermissions(): array
    {
        if (config('governance.temporary_permissions.enabled', false)) {
            return [];
        }

        return [
            'admin.temp_permissions.view',
            'admin.temp_permissions.manage',
        ];
    }

    public function permissionDefinitions(): array
    {
        return config('ai_capabilities.definitions', []);
    }

    public function panelPermissionDefinitions(): array
    {
        return Arr::except(
            $this->permissionDefinitions(),
            $this->postponedPanelPermissions(),
        );
    }

    /**
     * @return string[]
     */
    public function activePanelPermissionNames(): array
    {
        return array_keys($this->panelPermissionDefinitions());
    }

    public function permissionOptions(): array
    {
        return collect(array_keys($this->panelPermissionDefinitions()))
            ->sort()
            ->mapWithKeys(fn (string $permission): array => [$permission => $permission])
            ->all();
    }

    public function groupedPermissionOptions(): array
    {
        return collect($this->permissionOptions())
            ->groupBy(function (string $label, string $permission): string {
                $prefix = Str::before($permission, '.');

                if ($prefix === $permission) {
                    return 'Misc';
                }

                return Str::of($prefix)
                    ->replace(['_', '-'], ' ')
                    ->title()
                    ->toString();
            })
            ->map(fn ($permissions) => $permissions->all())
            ->sortKeys()
            ->all();
    }

    public function userTypeOptions(): array
    {
        return collect(config('user_types.all', []))
            ->mapWithKeys(fn (string $type): array => [$type => $this->label($type)])
            ->all();
    }

    public function governanceRoleOptions(): array
    {
        return collect(config('governance.managed_panel_roles', []))
            ->mapWithKeys(fn (string $role): array => [$role => $this->label($role)])
            ->all();
    }

    /**
     * Governance roles the given actor is allowed to assign.
     * Only super_admin can assign the super_admin role.
     */
    public function assignableGovernanceRoleOptions(?User $actor = null): array
    {
        $superAdminRole = config('governance.super_admin_role', 'super_admin');
        $actorIsSuperAdmin = $actor && $actor->hasRole($superAdminRole);

        return collect(config('governance.managed_panel_roles', []))
            ->reject(fn (string $role): bool => $role === $superAdminRole && ! $actorIsSuperAdmin)
            ->mapWithKeys(fn (string $role): array => [$role => $this->label($role)])
            ->all();
    }

    public function isEditableRole(string $roleName): bool
    {
        return $this->isManagedGovernanceRole($roleName)
            || in_array($roleName, config('governance.future_section_roles', []), true);
    }

    public function allGovernanceRoles(): array
    {
        return array_values(array_unique([
            ...config('governance.managed_panel_roles', []),
            ...config('governance.future_section_roles', []),
        ]));
    }

    public function isOperationalRole(string $role): bool
    {
        return in_array($role, config('governance.operational_roles', []), true);
    }

    public function isManagedGovernanceRole(string $role): bool
    {
        return in_array($role, config('governance.managed_panel_roles', []), true);
    }

    public function isKnownPermission(string $permission): bool
    {
        return Arr::exists($this->permissionDefinitions(), $permission);
    }

    public function isActivePanelPermission(string $permission): bool
    {
        return Arr::exists($this->panelPermissionDefinitions(), $permission);
    }

    public function permissionDescription(string $permission): ?string
    {
        return $this->permissionDefinitions()[$permission] ?? null;
    }

    public function label(string $value): string
    {
        return Str::of($value)
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();
    }
}
