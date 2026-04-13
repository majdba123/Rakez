<?php

namespace App\Services\AI\Skills;

use App\Models\User;
use App\Services\AI\CapabilityResolver;

class SkillCatalogService
{
    public function __construct(
        private readonly SkillRegistry $registry,
        private readonly CapabilityResolver $capabilityResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function catalogForUser(User $user, ?string $section = null): array
    {
        $capabilities = $this->capabilityResolver->resolve($user);
        $skills = $this->registry->availableForUser($user, $capabilities);
        $section = $section ? $this->registry->normalizeSectionKey($section) : null;

        if ($section !== null) {
            $skills = array_values(array_filter(
                $skills,
                static fn (array $skill): bool => (string) ($skill['section_key'] ?? '') === $section
            ));
        }

        $items = array_values(array_map(
            fn (array $definition): array => $this->publicSkillView($definition),
            $skills
        ));

        usort($items, static fn (array $a, array $b): int => strcmp($a['skill_key'], $b['skill_key']));

        return [
            'section_filter' => $section,
            'count' => count($items),
            'skills' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function publicSkillView(array $definition): array
    {
        return [
            'skill_key' => (string) ($definition['skill_key'] ?? ''),
            'section_key' => (string) ($definition['section_key'] ?? 'general'),
            'title' => (string) ($definition['title'] ?? ''),
            'business_goal' => (string) ($definition['business_goal'] ?? ''),
            'category' => (string) ($definition['category'] ?? 'informational'),
            'type' => (string) ($definition['type'] ?? 'read'),
            'risk_level' => (string) ($definition['risk_level'] ?? 'low'),
            'required_inputs' => array_values(array_keys((array) ($definition['input_schema'] ?? []))),
            'required_permissions' => array_values((array) ($definition['required_permissions'] ?? [])),
            'rollout_status' => $this->registry->isEnabled($definition) ? 'enabled' : 'disabled',
        ];
    }
}
