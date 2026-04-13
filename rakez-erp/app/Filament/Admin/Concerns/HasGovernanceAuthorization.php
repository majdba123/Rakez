<?php

namespace App\Filament\Admin\Concerns;

use App\Models\User;
use App\Services\Governance\FilamentNavigationPolicy;
use App\Services\Governance\GovernanceAccessService;

trait HasGovernanceAuthorization
{
    protected static function governanceActor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    protected static function currentNavigationGroupLabel(): ?string
    {
        if (! method_exists(static::class, 'getNavigationGroup')) {
            return null;
        }

        $group = static::getNavigationGroup();

        if ($group === null) {
            return null;
        }

        return $group instanceof \UnitEnum
            ? ($group instanceof \BackedEnum ? (string) $group->value : $group->name)
            : (string) $group;
    }

    protected static function canGovernance(string $permission): bool
    {
        $user = static::governanceActor();

        if (! $user) {
            return false;
        }

        return app(GovernanceAccessService::class)->allows($user, $permission);
    }

    protected static function canGovernanceAny(array $permissions): bool
    {
        $user = static::governanceActor();

        if (! $user) {
            return false;
        }

        $service = app(GovernanceAccessService::class);

        foreach ($permissions as $permission) {
            if ($service->allows($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    protected static function canGovernanceMutation(?string $permission): bool
    {
        if (blank($permission)) {
            return false;
        }

        $user = static::governanceActor();

        if (! $user) {
            return false;
        }

        $access = app(GovernanceAccessService::class);

        if ($access->isReadOnly($user)) {
            return false;
        }

        $navigationGroup = static::currentNavigationGroupLabel();

        if ($navigationGroup !== null && ! app(FilamentNavigationPolicy::class)->canAccessNavigationGroup($user, $navigationGroup)) {
            return false;
        }

        return $access->allows($user, $permission);
    }

    public static function canAccessGovernancePage(string $navigationGroup, string $permission): bool
    {
        $user = static::governanceActor();

        if (! $user) {
            return false;
        }

        if (! app(FilamentNavigationPolicy::class)->canAccessNavigationGroup($user, $navigationGroup)) {
            return false;
        }

        return static::canGovernance($permission);
    }

    public static function canAccessGovernancePageAny(string $navigationGroup, array $permissions): bool
    {
        $user = static::governanceActor();

        if (! $user) {
            return false;
        }

        if (! app(FilamentNavigationPolicy::class)->canAccessNavigationGroup($user, $navigationGroup)) {
            return false;
        }

        return static::canGovernanceAny($permissions);
    }
}
