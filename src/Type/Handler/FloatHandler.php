<?php

declare(strict_types=1);

namespace Touta\Ogam\Type\Handler;

use PDO;
use PDOStatement;
use Touta\Ogam\Type\BaseTypeHandler;

/**
 * Type handler for float/double values.
 */
final class FloatHandler extends BaseTypeHandler
{
    public function getPhpType(): string
    {
        return 'float';
    }

    protected function setNonNullParameter(
        PDOStatement $statement,
        int|string $index,
        mixed $value,
        ?string $sqlType,
    ): void {
        // PDO doesn't have a PARAM_FLOAT, so we use string representation
        $floatValue = is_numeric($value) ? (float) $value : 0.0;
        $statement->bindValue($index, (string) $floatValue, PDO::PARAM_STR);
    }

    protected function getNonNullResult(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
