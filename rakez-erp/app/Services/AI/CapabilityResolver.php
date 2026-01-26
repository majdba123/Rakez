<?php

namespace App\Services\AI;

use App\Models\User;

class CapabilityResolver
{
    /** @var array<int, array<string>> */
    private array $cache = [];

    public function resolve(User $user): array
    {
        if (isset($this->cache[$user->id])) {
            return $this->cache[$user->id];
        }

        // 1. Prefer explicit attribute override (testing/dev override)
        if ($user->getAttribute('capabilities') && is_array($user->getAttribute('capabilities'))) {
            $caps = $user->getAttribute('capabilities');
        }
        // 2. Use Spatie permissions as the primary source of truth
        elseif (method_exists($user, 'getAllPermissions')) {
            $caps = $user->getAllPermissions()->pluck('name')->toArray();
        }

        // 3. Fallback to bootstrap defaults if no capabilities found (legacy/dev support)
        if (empty($caps)) {
            $map = config('ai_capabilities.bootstrap_role_map', []);
            $userRole = $user->type ?? 'default';
            $caps = $map[$userRole] ?? $map['default'] ?? [];
        }

        $caps = array_values(array_unique(array_filter($caps, fn ($c) => is_string($c) && $c !== '')));

        return $this->cache[$user->id] = $caps;
    }

    public function has(User $user, string $capability): bool
    {
        return in_array($capability, $this->resolve($user), true);
    }
}
