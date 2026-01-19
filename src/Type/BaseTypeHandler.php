<?php

declare(strict_types=1);

namespace Touta\Ogam\Type;

use PDO;
use PDOStatement;
use Touta\Ogam\Contract\TypeHandlerInterface;

/**
 * Base class for type handlers.
 *
 * Provides common functionality for parameter binding and result extraction.
 */
abstract class BaseTypeHandler implements TypeHandlerInterface
{
    public function setParameter(
        PDOStatement $statement,
        int|string $index,
        mixed $value,
        ?string $sqlType,
    ): void {
        if ($value === null) {
            $statement->bindValue($index, null, PDO::PARAM_NULL);

            return;
        }

        $this->setNonNullParameter($statement, $index, $value, $sqlType);
    }

    public function getResult(array $row, string $columnName): mixed
    {
        if (!\array_key_exists($columnName, $row)) {
            return null;
        }

        $value = $row[$columnName];

        if ($value === null) {
            return null;
        }

        return $this->getNonNullResult($value);
    }

    public function getResultByIndex(array $row, int $columnIndex): mixed
    {
        $values = \array_values($row);

        if (!isset($values[$columnIndex])) {
            return null;
        }

        $value = $values[$columnIndex];

        if ($value === null) {
            return null;
        }

        return $this->getNonNullResult($value);
    }

    /**
     * Set a non-null parameter value.
     *
     * @param PDOStatement $statement The prepared statement
     * @param int|string $index The parameter index or name
     * @param mixed $value The non-null PHP value
     * @param string|null $sqlType Optional SQL type hint
     */
    abstract protected function setNonNullParameter(
        PDOStatement $statement,
        int|string $index,
        mixed $value,
        ?string $sqlType,
    ): void;

    /**
     * Convert a non-null database value to a PHP value.
     *
     * @param mixed $value The non-null database value
     *
     * @return mixed The converted PHP value
     */
    abstract protected function getNonNullResult(mixed $value): mixed;
}
