<?php

namespace App\Services\Access;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class FrontendAccessProfileService
{
    public function build(User $user): array
    {
        $sections = [];
        $definitions = config('frontend_access.sections', []);

        foreach ($definitions as $sectionKey => $definition) {
            $tabs = $this->deriveTabs($user, Arr::get($definition, 'tabs', []));
            $actions = $this->deriveActions($user, Arr::get($definition, 'actions', []));

            $sectionVisible = $this->isSectionVisible(
                $user,
                Arr::get($definition, 'permissions_any', []),
                $tabs,
                $actions
            );

            $sections[$sectionKey] = [
                'label' => (string) Arr::get($definition, 'label', Str::of($sectionKey)->replace('_', ' ')->title()),
                'visible' => $sectionVisible,
                'tabs' => $tabs,
                'actions' => $actions,
            ];
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type,
            ],
            'frontend' => [
                'sections' => $sections,
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $tabDefinitions
     * @return array<string, array<string, mixed>>
     */
    protected function deriveTabs(User $user, array $tabDefinitions): array
    {
        $tabs = [];

        foreach ($tabDefinitions as $tabKey => $definition) {
            $tabs[$tabKey] = [
                'label' => (string) Arr::get($definition, 'label', Str::of($tabKey)->replace('_', ' ')->title()),
                'route' => (string) Arr::get($definition, 'route', ''),
                'visible' => $this->hasAnyPermission($user, Arr::get($definition, 'permissions_any', [])),
            ];
        }

        return $tabs;
    }

    /**
     * @param  array<string, array<string, mixed>>  $actionDefinitions
     * @return array<string, bool>
     */
    protected function deriveActions(User $user, array $actionDefinitions): array
    {
        $actions = [];

        foreach ($actionDefinitions as $actionKey => $definition) {
            $actions[$actionKey] = $this->hasAnyPermission($user, Arr::get($definition, 'permissions_any', []));
        }

        return $actions;
    }

    /**
     * @param  array<int, string>  $sectionPermissions
     * @param  array<string, array<string, mixed>>  $tabs
     * @param  array<string, bool>  $actions
     */
    protected function isSectionVisible(User $user, array $sectionPermissions, array $tabs, array $actions): bool
    {
        if ($this->hasAnyPermission($user, $sectionPermissions)) {
            return true;
        }

        foreach ($tabs as $tab) {
            if (($tab['visible'] ?? false) === true) {
                return true;
            }
        }

        foreach ($actions as $allowed) {
            if ($allowed === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function hasAnyPermission(User $user, array $permissions): bool
    {
        if ($permissions === []) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (! is_string($permission) || $permission === '') {
                continue;
            }

            if ($this->hasPermission($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    protected function hasPermission(User $user, string $permission): bool
    {
        // Check dynamic permissions first (includes temporary grants and manager overlays).
        if (method_exists($user, 'hasEffectivePermission') && $user->hasEffectivePermission($permission)) {
            return true;
        }

        // Fall through to Spatie's direct permission check.
        // Intentionally not using $user->can() to avoid triggering Laravel Policies.
        try {
            return $user->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            // Ignore unknown permission names from legacy config mappings.
            return false;
        }
    }
}
