<?php

declare(strict_types=1);

namespace Touta\Ogam\Logging;

/**
 * Interface for logging query executions.
 *
 * Implementations can log to files, PSR-3 loggers, or collect
 * queries for debugging and profiling purposes.
 */
interface QueryLoggerInterface
{
    /**
     * Log a query execution.
     */
    public function log(QueryLogEntry $entry): void;

    /**
     * Get all logged entries.
     *
     * @return list<QueryLogEntry>
     */
    public function getEntries(): array;

    /**
     * Clear all logged entries.
     */
    public function clear(): void;
}
