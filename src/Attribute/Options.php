<?php

declare(strict_types=1);

namespace Touta\Ogam\Attribute;

use Attribute;
use Touta\Ogam\Mapping\StatementType;

/**
 * Specifies additional options for a mapped statement.
 *
 * This attribute provides fine-grained control over statement execution,
 * including generated key handling, caching behavior, and timeouts.
 *
 * Example:
 * ```php
 * #[Insert("INSERT INTO users (name, email) VALUES (:name, :email)")]
 * #[Options(useGeneratedKeys: true, keyProperty: 'id')]
 * public function insert(User $user): int;
 *
 * #[Select("SELECT * FROM large_table")]
 * #[Options(fetchSize: 100, timeout: 60, useCache: false)]
 * public function findAll(): array;
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Options
{
    /**
     * @param bool $useGeneratedKeys Whether to retrieve auto-generated keys after insert
     * @param string|null $keyProperty The property to set the generated key on
     * @param string|null $keyColumn The column name of the generated key
     * @param string|null $resultSetType The result set type (FORWARD_ONLY, SCROLL_INSENSITIVE, SCROLL_SENSITIVE)
     * @param StatementType|null $statementType Override the statement type
     * @param int|null $fetchSize The fetch size hint for the driver
     * @param int|null $timeout The statement timeout in seconds
     * @param bool $flushCache Whether to flush the cache before execution
     * @param bool $useCache Whether to use the cache for this statement
     */
    public function __construct(
        public bool $useGeneratedKeys = false,
        public ?string $keyProperty = null,
        public ?string $keyColumn = null,
        public ?string $resultSetType = null,
        public ?StatementType $statementType = null,
        public ?int $fetchSize = null,
        public ?int $timeout = null,
        public bool $flushCache = false,
        public bool $useCache = true,
    ) {}
}
