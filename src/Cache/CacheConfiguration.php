<?php

declare(strict_types=1);

namespace Touta\Ogam\Cache;

/**
 * Configuration for second-level cache on a mapper namespace.
 *
 * The cache element in mapper XML can have the following attributes:
 * - type: Custom cache implementation class (optional)
 * - eviction: Eviction policy (LRU, FIFO, SOFT, WEAK) - default LRU
 * - flushInterval: Auto-flush interval in milliseconds (optional)
 * - size: Maximum number of entries (default 1024)
 * - readOnly: Whether cached objects are read-only (default true)
 *
 * Example XML:
 * ```xml
 * <mapper namespace="UserMapper">
 *   <cache eviction="LRU" size="512" readOnly="true"/>
 *   ...
 * </mapper>
 * ```
 */
final readonly class CacheConfiguration
{
    /**
     * @param string $namespace The mapper namespace this cache is for
     * @param string|null $implementation Custom cache implementation class
     * @param EvictionPolicy $eviction Eviction policy
     * @param int|null $flushInterval Auto-flush interval in milliseconds
     * @param int $size Maximum cache entries
     * @param bool $readOnly Whether cached objects are immutable
     * @param bool $enabled Whether caching is enabled
     */
    public function __construct(
        public string $namespace,
        public ?string $implementation = null,
        public EvictionPolicy $eviction = EvictionPolicy::LRU,
        public ?int $flushInterval = null,
        public int $size = 1024,
        public bool $readOnly = true,
        public bool $enabled = true,
    ) {}
}
