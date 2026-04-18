<?php

namespace App\Services\Governance;

use App\Models\User;
use Illuminate\Support\Collection;

class EffectiveAccessSnapshotService
{
    public function __construct(
        protected GovernanceAccessService $access,
        protected GovernanceCatalog $catalog,
        protected GovernanceTemporaryPermissionService $temporaryPermissions,
    ) {}

    public function forUser(User $user): array
    {
        $roleNames = $user->roles()->pluck('name')->sort()->values()->all();
        $directPermissions = $user->permissions()->pluck('name')->sort()->values()->all();
        $inheritedPermissions = $user->getPermissionsViaRoles()->pluck('name')->sort()->values()->all();
        $temporaryPermissionNames = $this->temporaryPermissions->activePermissionNamesForUser($user)->sort()->values()->all();
        $effectivePermissions = collect($user->getEffectivePermissions())->unique()->sort()->values()->all();

        return [
            'legacy_roles' => array_values(array_filter($roleNames, fn (string $role): bool => $this->catalog->isOperationalRole($role))),
            'governance_roles' => array_values(array_filter($roleNames, fn (string $role): bool => in_array($role, $this->catalog->allGovernanceRoles(), true))),
            'direct_permissions' => $directPermissions,
            'inherited_permissions' => $inheritedPermissions,
            'temporary_permissions' => $temporaryPermissionNames,
            'dynamic_permissions' => array_values(array_diff($effectivePermissions, [...$directPermissions, ...$inheritedPermissions, ...$temporaryPermissionNames])),
            'panel_eligible' => $this->access->canAccessPanel($user),
        ];
    }

    public function summaryForUser(User $user): string
    {
        $snapshot = $this->forUser($user);

        return collect([
            __('filament-admin.resources.effective_access.summary.legacy_roles') . ': ' . $this->implode($this->catalog->displayRoleLabels($snapshot['legacy_roles'])),
            __('filament-admin.resources.effective_access.summary.governance_roles') . ': ' . $this->implode($this->catalog->displayRoleLabels($snapshot['governance_roles'])),
            __('filament-admin.resources.effective_access.summary.direct_permissions') . ': ' . count($snapshot['direct_permissions']),
            __('filament-admin.resources.effective_access.summary.inherited_permissions') . ': ' . count($snapshot['inherited_permissions']),
            __('filament-admin.resources.effective_access.summary.temporary_permissions') . ': ' . count($snapshot['temporary_permissions']),
            __('filament-admin.resources.effective_access.summary.dynamic_permissions') . ': ' . count($snapshot['dynamic_permissions']),
            __('filament-admin.resources.effective_access.summary.panel_eligible') . ': ' . (
                $snapshot['panel_eligible']
                    ? __('filament-admin.resources.effective_access.summary.yes')
                    : __('filament-admin.resources.effective_access.summary.no')
            ),
        ])->implode(PHP_EOL);
    }

    protected function implode(array $values): string
    {
        return blank($values)
            ? __('filament-admin.resources.effective_access.summary.none')
            : Collection::make($values)->implode(', ');
    }
}
