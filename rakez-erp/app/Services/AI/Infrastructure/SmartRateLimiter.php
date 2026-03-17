<?php

namespace App\Services\AI\Infrastructure;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SmartRateLimiter
{
    private const PREFIX = 'ai_rate_limit:';

    /**
     * Check if the user is allowed to make a request.
     */
    public function check(User $user, ?string $section = null): bool
    {
        $limit = $this->getLimit($user);
        $key = $this->buildKey($user);

        $current = (int) Cache::get($key, 0);

        if ($current >= $limit) {
            return false;
        }

        Cache::increment($key);

        // Set expiry to 1 minute from first request
        if ($current === 0) {
            Cache::put($key, 1, 60);
        }

        return true;
    }

    /**
     * Get remaining requests for the user this minute.
     */
    public function remaining(User $user): int
    {
        $limit = $this->getLimit($user);
        $key = $this->buildKey($user);
        $current = (int) Cache::get($key, 0);

        return max(0, $limit - $current);
    }

    /**
     * Get the per-minute limit for a user based on their role.
     */
    public function getLimit(User $user): int
    {
        $limits = config('ai_assistant.smart_rate_limits', []);
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [];

        foreach ($roles as $role) {
            if (isset($limits[$role])) {
                return $limits[$role];
            }
        }

        return $limits['default'] ?? 15;
    }

    private function buildKey(User $user): string
    {
        return self::PREFIX . $user->id . ':' . now()->format('Y-m-d-H-i');
    }
}
