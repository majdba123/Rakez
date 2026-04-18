<?php

namespace App\Services\Governance;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class GovernanceCatalog
{
    public function displayRoleSlug(string $role): string
    {
        return config('governance.role_slug_aliases', [])[$role] ?? $role;
    }

    /**
     * @param  string[]  $roles
     * @return string[]
     */
    public function displayRoles(array $roles): array
    {
        return collect($roles)
            ->filter(fn (mixed $role): bool => is_string($role) && $role !== '')
            ->map(fn (string $role): string => $this->displayRoleSlug($role))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  string[]  $roles
     * @return string[]
     */
    public function displayRoleLabels(array $roles): array
    {
        return collect($roles)
            ->map(fn (string $role): string => $this->displayRoleLabel($role))
            ->values()
            ->all();
    }

    public function displayRoleLabel(string $role): string
    {
        $displaySlug = $this->displayRoleSlug($role);

        return match ($displaySlug) {
            'admin' => __('filament-admin.role_aliases.admin'),
            'legacy_admin' => __('filament-admin.role_aliases.legacy_admin'),
            default => $this->label($displaySlug),
        };
    }

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
        return collect($this->activeDatabasePermissionNames())
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
            }, true)
            ->map(fn ($permissions) => $permissions->all())
            ->sortKeys()
            ->all();
    }

    public function userTypeOptions(): array
    {
        return collect(config('user_types.all', []))
            ->mapWithKeys(fn (string $type): array => [$type => $type === 'admin' ? __('filament-admin.role_aliases.legacy_admin') : $this->label($type)])
            ->all();
    }

    public function assignableUserTypeOptions(?string $currentType = null): array
    {
        return collect($this->userTypeOptions())
            ->reject(fn (string $label, string $type): bool => $type === 'admin' && $currentType !== 'admin')
            ->all();
    }

    public function governanceRoleOptions(): array
    {
        return collect(config('governance.managed_governance_roles', []))
            ->mapWithKeys(fn (string $role): array => [$role => $this->displayRoleLabel($role)])
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

        return collect(config('governance.managed_governance_roles', []))
            ->reject(fn (string $role): bool => $role === $superAdminRole && ! $actorIsSuperAdmin)
            ->mapWithKeys(fn (string $role): array => [$role => $this->displayRoleLabel($role)])
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
            ...config('governance.managed_governance_roles', []),
            ...config('governance.future_section_roles', []),
        ]));
    }

    public function isOperationalRole(string $role): bool
    {
        return in_array($role, config('governance.operational_roles', []), true);
    }

    public function assignableOperationalRoleOptions(): array
    {
        return collect(config('governance.operational_roles', []))
            ->reject(fn (string $role): bool => in_array($role, $this->protectedOperationalRoles(), true))
            ->mapWithKeys(fn (string $role): array => [$role => $this->label($role)])
            ->all();
    }

    public function isManagedGovernanceRole(string $role): bool
    {
        return in_array($role, config('governance.managed_governance_roles', []), true);
    }

    public function isSupplementalOperationalRole(string $role): bool
    {
        return $this->isOperationalRole($role)
            && ! in_array($role, $this->protectedOperationalRoles(), true);
    }

    public function isKnownPermission(string $permission): bool
    {
        return Arr::exists($this->permissionDefinitions(), $permission);
    }

    public function isActivePanelPermission(string $permission): bool
    {
        return Arr::exists($this->panelPermissionDefinitions(), $permission);
    }

    /**
     * @return string[]
     */
    public function activeDatabasePermissionNames(): array
    {
        $known = $this->activePanelPermissionNames();

        if ($known === []) {
            return [];
        }

        $dbNames = Permission::query()
            ->whereIn('name', $known)
            ->pluck('name')
            ->all();

        // During early bootstrap/test setup, permissions table may be empty.
        // Fallback to dictionary keys so setup screens remain usable.
        return $dbNames === [] ? $known : $dbNames;
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

    /**
     * Legacy operational roles that remain in the repository for compatibility
     * but must not be newly assigned from Filament.
     *
     * @return string[]
     */
    protected function protectedOperationalRoles(): array
    {
        return [
            'admin',
        ];
    }
}
