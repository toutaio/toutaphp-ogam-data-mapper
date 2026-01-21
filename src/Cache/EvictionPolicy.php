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
     * Soft references - simulates Java's SoftReference-style behavior in PHP.
     * This is distinct from PHP's built-in WeakReference/WeakMap support and,
     * in this implementation, behaves similarly to LRU.
     */
    case SOFT = 'SOFT';

    /**
     * Weak references - allows garbage collection of cached objects.
     * In PHP, this behaves similarly to LRU with lower priority.
     */
    case WEAK = 'WEAK';
}
