<?php

declare(strict_types=1);

namespace Touta\Ogam\Attribute;

use Attribute;

/**
 * Marks a method as an UPDATE statement.
 *
 * Example:
 * ```php
 * #[Update("UPDATE users SET name = :name WHERE id = :id")]
 * public function update(User $user): int;
 *
 * #[Update(
 *     sql: "UPDATE users SET name = :name WHERE id = :id",
 *     timeout: 45,
 *     flushCache: true
 * )]
 * public function updateWithOptions(User $user): int;
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Update
{
    /**
     * @param string $sql The SQL UPDATE statement to execute
     * @param int $timeout The statement timeout in seconds (0 = no timeout)
     * @param bool $flushCache Whether to flush the cache after execution
     */
    public function __construct(
        public string $sql,
        public int $timeout = 0,
        public bool $flushCache = false,
    ) {}
}
