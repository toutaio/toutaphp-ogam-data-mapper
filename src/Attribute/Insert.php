<?php

declare(strict_types=1);

namespace Touta\Ogam\Attribute;

use Attribute;

/**
 * Marks a method as an INSERT statement.
 *
 * Example:
 * ```php
 * #[Insert("INSERT INTO users (name, email) VALUES (:name, :email)")]
 * public function insert(User $user): int;
 *
 * #[Insert(
 *     sql: "INSERT INTO users (name, email) VALUES (:name, :email)",
 *     timeout: 60,
 *     flushCache: true
 * )]
 * public function insertWithOptions(User $user): int;
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Insert
{
    /**
     * @param string $sql The SQL INSERT statement to execute
     * @param int $timeout The statement timeout in seconds (0 = no timeout)
     * @param bool $flushCache Whether to flush the cache after execution
     */
    public function __construct(
        public string $sql,
        public int $timeout = 0,
        public bool $flushCache = false,
    ) {}
}
