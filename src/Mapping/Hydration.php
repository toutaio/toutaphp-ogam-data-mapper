<?php

declare(strict_types=1);

namespace Touta\Ogam\Mapping;

/**
 * Controls how query results are hydrated.
 */
enum Hydration: string
{
    /**
     * Hydrate results to objects.
     *
     * Uses the result type's constructor or setters.
     */
    case OBJECT = 'object';

    /**
     * Return results as associative arrays.
     *
     * Faster than object hydration, lower memory usage.
     */
    case ARRAY = 'array';

    /**
     * Return a single scalar value.
     *
     * Use for COUNT, SUM, or single-column queries.
     */
    case SCALAR = 'scalar';
}
