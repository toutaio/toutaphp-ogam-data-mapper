<?php

declare(strict_types=1);

namespace Touta\Ogam\Cache;

/**
 * Cache eviction policies.
 */
enum EvictionPolicy: string
{
    /**
     * Least Recently Used - evicts entries that haven't been used recently.
     */
    case LRU = 'LRU';

    /**
     * First In First Out - evicts entries in the order they were added.
     */
    case FIFO = 'FIFO';

    /**
     * Soft references - JVM-style weak references (simulated in PHP).
     * In PHP, this behaves similarly to LRU.
     */
    case SOFT = 'SOFT';

    /**
     * Weak references - allows garbage collection of cached objects.
     * In PHP, this behaves similarly to LRU with lower priority.
     */
    case WEAK = 'WEAK';
}
