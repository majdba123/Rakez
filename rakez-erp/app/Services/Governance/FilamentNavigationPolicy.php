<?php

namespace App\Services\Governance;

use App\Models\User;
use BackedEnum;
use UnitEnum;

class FilamentNavigationPolicy
{
    public function __construct(
        protected GovernanceAccessService $access,
    ) {}

    /**
     * @param  string|UnitEnum|null  $group
     */
    public function canAccessNavigationGroup(?User $user, string|UnitEnum|null $group): bool
    {
        if (! $user) {
            return false;
        }

        if (! $this->access->canAccessPanel($user)) {
            return false;
        }

        $label = $this->normalizeGroupLabel($group);

        if ($label === null) {
            return true;
        }

        $enabledSections = config('governance.enabled_sections');

        if (! is_array($enabledSections) || ! in_array($label, $enabledSections, true)) {
            return false;
        }

        $allGroupPermissions = config('governance.filament_navigation_group_permissions', []);

        if (! array_key_exists($label, $allGroupPermissions)) {
            // Group not listed in config at all — ungated (e.g. Overview, Access Governance)
            return true;
        }

        $required = $allGroupPermissions[$label];

        if (! is_array($required) || $required === []) {
            // Misconfigured or empty — fail-closed to prevent accidental open access
            return false;
        }

        foreach ($required as $permission) {
            if ($this->access->allows($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeGroupLabel(string|UnitEnum|null $group): ?string
    {
        if ($group === null) {
            return null;
        }

        if ($group instanceof BackedEnum) {
            return (string) $group->value;
        }

        if ($group instanceof UnitEnum) {
            return $group->name;
        }

        return $group;
    }
}
