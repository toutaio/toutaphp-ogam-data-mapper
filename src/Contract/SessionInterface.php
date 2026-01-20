<?php

declare(strict_types=1);

namespace Touta\Ogam\Contract;

use Touta\Ogam\Mapping\Hydration;

/**
 * The primary interface for working with the data mapper.
 *
 * A Session represents a unit of work with the database. It manages
 * database connections, transactions, and provides methods for executing
 * mapped SQL statements.
 *
 * Sessions are NOT thread-safe and should not be shared between requests.
 * Always close sessions when done to release resources.
 */
interface SessionInterface
{
    /**
     * Execute a SELECT statement returning a single result.
     *
     * @param string $statement The statement ID (e.g., 'UserMapper.findById')
     * @param array<string, mixed>|object|null $parameter Parameters for the query
     * @param Hydration $hydration The hydration mode
     *
     * @return mixed The result object, or null if not found
     */
    public function selectOne(
        string $statement,
        array|object|null $parameter = null,
        Hydration $hydration = Hydration::OBJECT,
    ): mixed;

    /**
     * Execute a SELECT statement returning multiple results.
     *
     * @param string $statement The statement ID
     * @param array<string, mixed>|object|null $parameter Parameters for the query
     * @param Hydration $hydration The hydration mode
     *
     * @return list<mixed> The list of results
     */
    public function selectList(
        string $statement,
        array|object|null $parameter = null,
        Hydration $hydration = Hydration::OBJECT,
    ): array;

    /**
     * Execute a SELECT statement returning results as a map.
     *
     * @param string $statement The statement ID
     * @param string $mapKey The property to use as the map key
     * @param array<string, mixed>|object|null $parameter Parameters for the query
     *
     * @return array<array-key, mixed> The results keyed by the specified property
     */
    public function selectMap(
        string $statement,
        string $mapKey,
        array|object|null $parameter = null,
    ): array;

    /**
     * Execute a SELECT statement returning a cursor for large result sets.
     *
     * The cursor fetches rows lazily, one at a time, reducing memory usage.
     *
     * @param string $statement The statement ID
     * @param array<string, mixed>|object|null $parameter Parameters for the query
     *
     * @return iterable<mixed> An iterable cursor
     */
    public function selectCursor(
        string $statement,
        array|object|null $parameter = null,
    ): iterable;

    /**
     * Execute an INSERT statement.
     *
     * @param string $statement The statement ID
     * @param array<string, mixed>|object|null $parameter Parameters for the insert
     *
     * @return int The number of rows affected
     */
    public function insert(string $statement, array|object|null $parameter = null): int;

    /**
     * Execute an UPDATE statement.
     *
     * @param string $statement The statement ID
     * @param array<string, mixed>|object|null $parameter Parameters for the update
     *
     * @return int The number of rows affected
     */
    public function update(string $statement, array|object|null $parameter = null): int;

    /**
     * Execute a DELETE statement.
     *
     * @param string $statement The statement ID
     * @param array<string, mixed>|object|null $parameter Parameters for the delete
     *
     * @return int The number of rows affected
     */
    public function delete(string $statement, array|object|null $parameter = null): int;

    /**
     * Commit the current transaction.
     *
     * This commits all pending changes and clears the local cache.
     */
    public function commit(): void;

    /**
     * Rollback the current transaction.
     *
     * This discards all pending changes and clears the local cache.
     */
    public function rollback(): void;

    /**
     * Get a mapper interface proxy.
     *
     * @template T of object
     *
     * @param class-string<T> $mapperInterface The mapper interface class
     *
     * @return T A proxy implementing the mapper interface
     */
    public function getMapper(string $mapperInterface): object;

    /**
     * Close the session and release resources.
     *
     * If there are uncommitted changes, they will be rolled back
     * and a warning will be logged.
     */
    public function close(): void;

    /**
     * Check if the session is closed.
     */
    public function isClosed(): bool;

    /**
     * Clear the local (first-level) cache.
     */
    public function clearCache(): void;

    /**
     * Get the last executed query for debugging.
     *
     * @return array{sql: string, params: array<string, mixed>, time: float}|null
     */
    public function getLastQuery(): ?array;
}
