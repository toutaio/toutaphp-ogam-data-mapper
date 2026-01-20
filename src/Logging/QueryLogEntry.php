<?php

declare(strict_types=1);

namespace Touta\Ogam\Logging;

/**
 * Represents a logged query execution.
 *
 * Contains all information about a query that was executed,
 * including the SQL, parameters, timing, and results.
 */
final readonly class QueryLogEntry
{
    /**
     * @param string $sql The SQL that was executed
     * @param array<mixed> $parameters The parameters bound to the query
     * @param float $executionTimeMs The execution time in milliseconds
     * @param int|null $rowCount The number of rows affected/returned (null if unknown)
     * @param string|null $statementId The mapped statement ID (e.g., "UserMapper.findById")
     */
    public function __construct(
        public string $sql,
        public array $parameters,
        public float $executionTimeMs,
        public ?int $rowCount = null,
        public ?string $statementId = null,
    ) {}
}
