<?php

declare(strict_types=1);

namespace Touta\Ogam\Attribute;

use Attribute;

/**
 * Marks a method as a DELETE statement.
 *
 * Example:
 * ```php
 * #[Delete("DELETE FROM users WHERE id = :id")]
 * public function delete(int $id): int;
 *
 * #[Delete(
 *     sql: "DELETE FROM users WHERE id = :id",
 *     timeout: 15,
 *     flushCache: true
 * )]
 * public function deleteWithOptions(int $id): int;
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Delete
{
    /**
     * @param string $sql The SQL DELETE statement to execute
     * @param int $timeout The statement timeout in seconds (0 = no timeout)
     * @param bool $flushCache Whether to flush the cache after execution
     */
    public function __construct(
        public string $sql,
        public int $timeout = 0,
        public bool $flushCache = false,
    ) {}
}
