<?php

declare(strict_types=1);

namespace Touta\Ogam\Attribute;

use Attribute;

/**
 * Marks a query or result class as read-optimized.
 *
 * When applied to a mapper method, this attribute hints to the executor that:
 * - The query only reads data and doesn't modify it
 * - Results can be cached more aggressively
 * - Local cache lookup can be skipped for performance (if skipLocalCache is true)
 *
 * When applied to a result class, this attribute hints that:
 * - The class is immutable (readonly)
 * - Constructor-based hydration should be used for optimal performance
 *
 * Example on method:
 * ```php
 * #[Select("SELECT * FROM users WHERE id = :id")]
 * #[ReadOptimized]
 * public function findById(int $id): ?User;
 * ```
 *
 * Example on class:
 * ```php
 * #[ReadOptimized]
 * final readonly class UserDto
 * {
 *     public function __construct(
 *         public int $id,
 *         public string $name,
 *     ) {}
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class ReadOptimized
{
    /**
     * @param bool $skipLocalCache Whether to skip local cache lookup for this query
     *                             (useful for queries that need real-time data)
     * @param bool $useConstructorHydration Whether to force constructor-based hydration
     *                                      for the result objects
     */
    public function __construct(
        public bool $skipLocalCache = true,
        public bool $useConstructorHydration = true,
    ) {}
}
