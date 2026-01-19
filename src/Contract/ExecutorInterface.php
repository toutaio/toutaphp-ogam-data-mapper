<?php

declare(strict_types=1);

namespace Touta\Ogam\Contract;

use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\MappedStatement;

/**
 * Executes SQL statements against the database.
 *
 * Different implementations provide different execution strategies:
 * - SIMPLE: Creates a new prepared statement for each execution
 * - REUSE: Reuses prepared statements across executions
 * - BATCH: Queues updates for batch execution
 */
interface ExecutorInterface
{
    /**
     * Execute a query statement.
     *
     * @template T
     *
     * @param MappedStatement $statement The mapped statement
     * @param array<string, mixed>|object|null $parameter The parameters
     * @param BoundSql $boundSql The bound SQL with parameters
     *
     * @return list<T> The query results
     */
    public function query(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): array;

    /**
     * Execute an update statement (INSERT, UPDATE, DELETE).
     *
     * @param MappedStatement $statement The mapped statement
     * @param array<string, mixed>|object|null $parameter The parameters
     * @param BoundSql $boundSql The bound SQL with parameters
     *
     * @return int The number of rows affected
     */
    public function update(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): int;

    /**
     * Flush any queued statements (for batch executor).
     *
     * @return list<int> The row counts for each flushed statement
     */
    public function flushStatements(): array;

    /**
     * Commit the transaction.
     *
     * @param bool $required Whether a commit is required
     */
    public function commit(bool $required): void;

    /**
     * Rollback the transaction.
     *
     * @param bool $required Whether a rollback is required
     */
    public function rollback(bool $required): void;

    /**
     * Close the executor and release resources.
     *
     * @param bool $forceRollback Whether to force a rollback
     */
    public function close(bool $forceRollback): void;

    /**
     * Check if the executor is closed.
     */
    public function isClosed(): bool;

    /**
     * Clear the local cache.
     */
    public function clearLocalCache(): void;

    /**
     * Get the last executed query for debugging.
     *
     * @return array{sql: string, params: array<string, mixed>, time: float}|null
     */
    public function getLastQuery(): ?array;
}
