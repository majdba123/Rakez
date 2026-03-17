<?php

namespace App\Services\AI;

use App\Models\User;

class CatalogService
{
    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly CapabilityResolver $resolver,
    ) {}

    /**
     * Get sections accessible by the given user.
     *
     * @return array<string, string> [key => label]
     */
    public function sectionsForUser(User $user): array
    {
        $capabilities = $this->resolver->resolve($user);
        $sections = $this->registry->availableFor($capabilities);

        $result = [];
        foreach ($sections as $section) {
            $key = $section['key'] ?? '';
            $label = $section['label'] ?? $key;
            if ($key !== '') {
                $result[$key] = $label;
            }
        }

        return $result;
    }

    /**
     * Get all valid section keys (regardless of user).
     *
     * @return array<string>
     */
    public function sectionKeys(): array
    {
        $all = $this->registry->all();

        return array_map(fn (array $s) => $s['key'] ?? '', $all);
    }
}
