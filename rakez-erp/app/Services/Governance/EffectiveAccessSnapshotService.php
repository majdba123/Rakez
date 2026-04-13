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
            'Legacy roles: ' . $this->implode($snapshot['legacy_roles']),
            'Governance roles: ' . $this->implode($snapshot['governance_roles']),
            'Direct permissions: ' . count($snapshot['direct_permissions']),
            'Inherited permissions: ' . count($snapshot['inherited_permissions']),
            'Dynamic permissions: ' . count($snapshot['dynamic_permissions']),
            'Panel eligible: ' . ($snapshot['panel_eligible'] ? 'yes' : 'no'),
        ])->implode(PHP_EOL);
    }

    protected function implode(array $values): string
    {
        return blank($values) ? 'none' : Collection::make($values)->implode(', ');
    }
}
