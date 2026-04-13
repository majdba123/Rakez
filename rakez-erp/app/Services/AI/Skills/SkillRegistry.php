<?php

namespace App\Services\AI\Skills;

use App\Models\User;
use App\Services\AI\SectionRegistry;

class SkillRegistry
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $definitions = null;

    public function __construct(
        private readonly SectionRegistry $sectionRegistry,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->definitions());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $skillKey): ?array
    {
        return $this->definitions()[$skillKey] ?? null;
    }

    /**
     * @param  array<int, string>  $capabilities
     * @return array<int, array<string, mixed>>
     */
    public function availableForUser(User $user, array $capabilities): array
    {
        $availableSections = array_values(array_map(
            static fn (array $section) => (string) ($section['key'] ?? ''),
            $this->sectionRegistry->availableFor($capabilities)
        ));

        $out = [];
        foreach ($this->definitions() as $definition) {
            if (! $this->isEnabled($definition)) {
                continue;
            }

            if (! in_array((string) $definition['section_key'], $availableSections, true)) {
                continue;
            }

            if (! $this->hasRequiredPermissions($user, $definition)) {
                continue;
            }

            if (! $this->hasRequiredCapabilities($definition, $capabilities)) {
                continue;
            }

            $out[] = $definition;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function isEnabled(array $definition): bool
    {
        if (! config('ai_skills.enabled', true)) {
            return false;
        }

        $flagPath = (string) ($definition['feature_flag'] ?? '');
        if ($flagPath === '') {
            return true;
        }

        return (bool) config($flagPath, false);
    }

    public function normalizeSectionKey(?string $sectionKey): string
    {
        $sectionKey = (string) ($sectionKey ?? 'general');
        $aliases = config('ai_skills.section_aliases', []);

        return (string) ($aliases[$sectionKey] ?? $sectionKey);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function hasRequiredPermissions(User $user, array $definition): bool
    {
        $required = (array) ($definition['required_permissions'] ?? []);

        foreach ($required as $permission) {
            if (! $user->can((string) $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, string>  $capabilities
     */
    public function hasRequiredCapabilities(array $definition, array $capabilities): bool
    {
        $required = (array) ($definition['required_capabilities'] ?? []);

        return empty(array_diff($required, $capabilities));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $raw = (array) config('ai_skills.definitions', []);
        $normalized = [];

        foreach ($raw as $configKey => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $skillKey = (string) ($definition['skill_key'] ?? $configKey);
            if ($skillKey === '') {
                continue;
            }

            $sectionKey = $this->normalizeSectionKey((string) ($definition['section_key'] ?? 'general'));
            $requiredPermissions = array_values(array_filter((array) ($definition['required_permissions'] ?? []), 'is_string'));
            $requiredCapabilities = array_values(array_filter((array) ($definition['required_capabilities'] ?? []), 'is_string'));

            $normalized[$skillKey] = array_merge(
                [
                    'skill_key' => $skillKey,
                    'section_key' => $sectionKey,
                    'title' => $skillKey,
                    'business_goal' => '',
                    'category' => 'informational',
                    'type' => 'read',
                    'risk_level' => 'low',
                    'required_permissions' => [],
                    'required_capabilities' => [],
                    'input_schema' => [],
                    'output_schema' => [],
                    'row_scope' => ['mode' => 'none'],
                    'backend_dependencies' => [],
                    'redaction' => ['profile' => 'none'],
                    'audit' => ['action' => 'skill_call'],
                ],
                $definition,
                [
                    'skill_key' => $skillKey,
                    'section_key' => $sectionKey,
                    'required_permissions' => $requiredPermissions,
                    'required_capabilities' => $requiredCapabilities,
                ]
            );
        }

        return $this->definitions = $normalized;
    }
}
