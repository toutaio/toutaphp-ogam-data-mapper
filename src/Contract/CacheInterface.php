<?php

declare(strict_types=1);

namespace Touta\Ogam\Contract;

use Touta\Ogam\Cache\CacheKey;

/**
 * Cache interface for storing query results.
 *
 * Used for both first-level (session scope) and second-level
 * (mapper scope) caching.
 */
interface CacheInterface
{
    /**
     * Get a cached value.
     *
     * @return mixed The cached value, or null if not found
     */
    public function get(CacheKey $key): mixed;

    /**
     * Store a value in the cache.
     *
     * @param mixed $value The value to cache
     */
    public function put(CacheKey $key, mixed $value): void;

    /**
     * Check if a key exists in the cache.
     */
    public function has(CacheKey $key): bool;

    /**
     * Remove a value from the cache.
     */
    public function remove(CacheKey $key): void;

    /**
     * Clear all cached values.
     */
    public function clear(): void;

    /**
     * Get the number of cached entries.
     */
    public function count(): int;
}
