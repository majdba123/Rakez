<?php

namespace App\Filament\Admin\Concerns;

use App\Models\User;
use App\Services\Governance\FilamentNavigationPolicy;

trait ChecksFilamentNavigationGroupGate
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $group = static::getNavigationGroup();

        if ($group !== null) {
            $label = $group instanceof \UnitEnum
                ? ($group instanceof \BackedEnum ? (string) $group->value : $group->name)
                : (string) $group;

            $enabled = config('governance.enabled_sections', []);

            if (! in_array($label, $enabled, true)) {
                return false;
            }

            if (! app(FilamentNavigationPolicy::class)->canAccessNavigationGroup($user, $label)) {
                return false;
            }
        }

        return static::canViewAny();
    }
}
