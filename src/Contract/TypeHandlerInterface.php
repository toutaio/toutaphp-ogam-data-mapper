<?php

declare(strict_types=1);

namespace Touta\Ogam\Contract;

use PDOStatement;

/**
 * Handles conversion between PHP types and SQL types.
 *
 * Type handlers are used for:
 * 1. Setting parameters on prepared statements
 * 2. Reading values from result sets
 *
 * Custom type handlers can be registered for value objects,
 * custom enums, or any PHP type that needs special handling.
 */
interface TypeHandlerInterface
{
    /**
     * Set a parameter value on a prepared statement.
     *
     * @param PDOStatement $statement The prepared statement
     * @param int|string $index The parameter index (1-based) or name
     * @param mixed $value The PHP value to set
     * @param string|null $sqlType Optional SQL type hint (e.g., 'VARCHAR', 'INTEGER')
     */
    public function setParameter(
        PDOStatement $statement,
        int|string $index,
        mixed $value,
        ?string $sqlType,
    ): void;

    /**
     * Get a value from a result row by column name.
     *
     * @param array<string, mixed> $row The result row
     * @param string $columnName The column name
     *
     * @return mixed The converted PHP value
     */
    public function getResult(array $row, string $columnName): mixed;

    /**
     * Get a value from a result row by column index.
     *
     * @param array<string, mixed> $row The result row
     * @param int $columnIndex The column index (0-based)
     *
     * @return mixed The converted PHP value
     */
    public function getResultByIndex(array $row, int $columnIndex): mixed;

    /**
     * Get the PHP type this handler manages.
     *
     * @return string|null The PHP type (class name, 'int', 'string', etc.) or null for any
     */
    public function getPhpType(): ?string;
}
