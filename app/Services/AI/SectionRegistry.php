<?php

namespace App\Services\AI;

class SectionRegistry
{
    public function all(): array
    {
        $sections = config('ai_sections', []);
        $withKeys = [];

        foreach ($sections as $key => $section) {
            $section['key'] = $section['key'] ?? $key;
            $withKeys[$key] = $section;
        }

        return $withKeys;
    }

    public function find(?string $key): ?array
    {
        if (! $key) {
            return null;
        }

        return $this->all()[$key] ?? null;
    }

    public function availableFor(array $capabilities): array
    {
        return array_values(array_filter($this->all(), function (array $section) use ($capabilities): bool {
            $required = $section['required_capabilities'] ?? [];
            return empty(array_diff($required, $capabilities));
        }));
    }

    public function allowedContextParams(?string $key): array
    {
        $section = $this->find($key);

        if (! $section) {
            return [];
        }

        return $section['allowed_context_params'] ?? [];
    }

    public function suggestions(?string $key): array
    {
        $section = $this->find($key);

        if (! $section) {
            return [];
        }

        $suggestions = $section['suggestions'] ?? [];
        $parent = $section['parent'] ?? null;

        if ($parent) {
            $parentSuggestions = $this->find($parent)['suggestions'] ?? [];
            $suggestions = array_values(array_unique(array_merge($suggestions, $parentSuggestions)));
        }

        return $suggestions;
    }

    public function contextSchema(?string $key): array
    {
        $section = $this->find($key);

        if (! $section) {
            return [];
        }

        return $section['context_schema'] ?? [];
    }

    public function contextPolicy(?string $key): array
    {
        $section = $this->find($key);

        if (! $section) {
            return [];
        }

        return $section['context_policy'] ?? [];
    }

    public function parent(?string $key): ?string
    {
        $section = $this->find($key);

        if (! $section) {
            return null;
        }

        return $section['parent'] ?? null;
    }
}
