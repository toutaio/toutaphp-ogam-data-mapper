<?php

declare(strict_types=1);

namespace Touta\Ogam\Attribute;

use Attribute;

/**
 * Marks an interface as a mapper for SQL statements.
 *
 * The mapper interface defines methods that map to SQL statements.
 * Each method can be annotated with #[Select], #[Insert], #[Update], or #[Delete]
 * to define the SQL statement to execute.
 *
 * Example:
 * ```php
 * #[Mapper(namespace: 'App\Mapper\UserMapper')]
 * interface UserMapper
 * {
 *     #[Select("SELECT * FROM users WHERE id = :id")]
 *     public function findById(int $id): ?User;
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Mapper
{
    /**
     * @param string|null $namespace The namespace for this mapper. If not specified,
     *                               the fully qualified interface name will be used.
     */
    public function __construct(
        public ?string $namespace = null,
    ) {}
}
