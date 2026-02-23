<?php

namespace App\Services\AI;

use App\Models\User;

class CatalogService
{
    private ?array $catalog = null;

    private CapabilityResolver $resolver;

    public function __construct(CapabilityResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function catalog(): array
    {
        if ($this->catalog === null) {
            $this->catalog = config('rakiz_catalog', []);
        }

        return $this->catalog;
    }

    /** All section keys with labels. */
    public function sections(): array
    {
        return $this->catalog()['sections'] ?? [];
    }

    /** All valid section keys. */
    public function sectionKeys(): array
    {
        return array_keys($this->sections());
    }

    /** Sections available for a given role name. */
    public function sectionsForRole(string $role): array
    {
        $roleData = $this->catalog()['roles'][$role] ?? null;
        if (! $roleData) {
            return [];
        }

        $roleSections = $roleData['sections'] ?? [];
        $all = $this->sections();

        return array_intersect_key($all, array_flip($roleSections));
    }

    /** Sections available for a given user (resolved via CapabilityResolver). */
    public function sectionsForUser(User $user): array
    {
        $caps = $this->resolver->resolve($user);
        $allSections = config('ai_sections', []);
        $result = [];

        foreach ($allSections as $key => $sec) {
            $required = $sec['required_capabilities'] ?? [];
            if (empty($required) || empty(array_diff($required, $caps))) {
                $result[$key] = $sec['label'] ?? $key;
            }
        }

        return $result;
    }

    /** Permissions assigned to a role in the catalog. */
    public function permissionsForRole(string $role): array
    {
        return $this->catalog()['roles'][$role]['permissions'] ?? [];
    }

    /** Full role map. */
    public function roleMap(): array
    {
        return $this->catalog()['roles'] ?? [];
    }

    /** All known role names. */
    public function roleNames(): array
    {
        return array_keys($this->roleMap());
    }

    /** Check whether a permission key exists in the catalog. */
    public function isPermissionValid(string $perm): bool
    {
        return array_key_exists($perm, $this->catalog()['permissions'] ?? []);
    }

    /** Check whether a section key exists in the catalog. */
    public function isSectionValid(string $section): bool
    {
        return array_key_exists($section, $this->sections());
    }

    /** Market reference ranges embedded in the catalog. */
    public function marketReferences(): array
    {
        return $this->catalog()['market_references'] ?? [];
    }

    /** Invalidate the in-memory cache (useful in tests). */
    public function flush(): void
    {
        $this->catalog = null;
    }
}
