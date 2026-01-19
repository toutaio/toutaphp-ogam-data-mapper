<?php

declare(strict_types=1);

namespace Touta\Ogam\Type\Handler;

use PDO;
use PDOStatement;
use Touta\Ogam\Type\BaseTypeHandler;

/**
 * Type handler for boolean values.
 */
final class BooleanHandler extends BaseTypeHandler
{
    public function getPhpType(): ?string
    {
        return 'bool';
    }

    protected function setNonNullParameter(
        PDOStatement $statement,
        int|string $index,
        mixed $value,
        ?string $sqlType,
    ): void {
        $statement->bindValue($index, (bool) $value, PDO::PARAM_BOOL);
    }

    protected function getNonNullResult(mixed $value): bool
    {
        // Handle various truthy/falsy representations from databases
        if (\is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        if (\is_string($value)) {
            $lower = strtolower($value);

            return \in_array($lower, ['1', 'true', 'yes', 'on', 't', 'y'], true);
        }

        return (bool) $value;
    }
}
