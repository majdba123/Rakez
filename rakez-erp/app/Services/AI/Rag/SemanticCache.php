<?php

namespace App\Services\AI\Rag;

use Illuminate\Support\Facades\Cache;

class SemanticCache
{
    private const CACHE_PREFIX = 'ai_rag_cache:';

    /**
     * Get cached search results for a query.
     *
     * @return array|null Cached results or null if not found.
     */
    public function get(string $query): ?array
    {
        $key = $this->buildKey($query);

        return Cache::get($key);
    }

    /**
     * Store search results in cache.
     *
     * @param  int  $ttl  Cache TTL in seconds.
     */
    public function put(string $query, array $results, int $ttl = 3600): void
    {
        $key = $this->buildKey($query);

        Cache::put($key, $results, $ttl);
    }

    /**
     * Check if a query is cached.
     */
    public function has(string $query): bool
    {
        return Cache::has($this->buildKey($query));
    }

    /**
     * Remove a specific query from cache.
     */
    public function forget(string $query): void
    {
        Cache::forget($this->buildKey($query));
    }

    /**
     * Flush all RAG cache entries.
     */
    public function flush(): void
    {
        // Since we can't easily flush by prefix with all cache drivers,
        // we use a version key approach
        $version = (int) Cache::get(self::CACHE_PREFIX . 'version', 0);
        Cache::put(self::CACHE_PREFIX . 'version', $version + 1);
    }

    /**
     * Build a cache key from the query string.
     */
    private function buildKey(string $query): string
    {
        $version = (int) Cache::get(self::CACHE_PREFIX . 'version', 0);

        return self::CACHE_PREFIX . "v{$version}:" . md5(mb_strtolower(trim($query)));
    }
}
