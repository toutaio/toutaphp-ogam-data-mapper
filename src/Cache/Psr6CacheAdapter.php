<?php

declare(strict_types=1);

namespace Touta\Ogam\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Touta\Ogam\Contract\CacheInterface;

/**
 * PSR-6 cache adapter for second-level caching.
 *
 * This adapter wraps a PSR-6 CacheItemPoolInterface to provide
 * a unified caching interface for Ogam's second-level cache.
 *
 * Example usage with Symfony Cache:
 * ```php
 * use Symfony\Component\Cache\Adapter\FilesystemAdapter;
 *
 * $pool = new FilesystemAdapter('ogam', 3600, '/path/to/cache');
 * $cache = new Psr6CacheAdapter($pool);
 * ```
 */
final class Psr6CacheAdapter implements CacheInterface
{
    /**
     * @param CacheItemPoolInterface $pool The PSR-6 cache pool
     * @param int|null $ttl Default TTL in seconds (null = no expiration)
     * @param string $prefix Key prefix for namespace isolation
     */
    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly ?int $ttl = null,
        private readonly string $prefix = '',
    ) {}

    public function get(CacheKey $key): mixed
    {
        $item = $this->pool->getItem($this->normalizeKey($key));

        if (!$item->isHit()) {
            return null;
        }

        return $item->get();
    }

    public function put(CacheKey $key, mixed $value): void
    {
        $item = $this->pool->getItem($this->normalizeKey($key));
        $item->set($value);

        if ($this->ttl !== null) {
            $item->expiresAfter($this->ttl);
        }

        $this->pool->save($item);
    }

    public function has(CacheKey $key): bool
    {
        return $this->pool->hasItem($this->normalizeKey($key));
    }

    public function remove(CacheKey $key): void
    {
        $this->pool->deleteItem($this->normalizeKey($key));
    }

    public function clear(): void
    {
        $this->pool->clear();
    }

    /**
     * PSR-6 does not support counting cached items.
     *
     * @return int Always returns 0
     */
    public function count(): int
    {
        return 0;
    }

    /**
     * Normalize the cache key to be PSR-6 compliant.
     *
     * PSR-6 reserves the following characters: {}()/\@:
     * These must be sanitized from the key.
     */
    private function normalizeKey(CacheKey $key): string
    {
        $keyString = $this->prefix . $key->toString();

        // Replace reserved PSR-6 characters with underscores.
        // preg_replace() should not fail with this static pattern, but fall back to the original key if it does.
        $normalized = preg_replace('/[{}()\/\\\\@:]/', '_', $keyString);

        if ($normalized === null) {
            return $keyString;
        }

        return $normalized;
    }
}
