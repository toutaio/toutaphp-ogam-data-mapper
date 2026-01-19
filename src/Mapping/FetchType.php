<?php

declare(strict_types=1);

namespace Touta\Ogam\Mapping;

/**
 * Controls when associated data is loaded.
 */
enum FetchType: string
{
    /**
     * Load immediately as part of the main query.
     */
    case EAGER = 'eager';

    /**
     * Load on first access.
     *
     * Note: Ogam does not support lazy loading via proxies.
     * This is included for compatibility but effectively behaves as EAGER.
     */
    case LAZY = 'lazy';
}
