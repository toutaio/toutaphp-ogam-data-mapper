<?php

declare(strict_types=1);

namespace Touta\Ogam\Type\Handler;

use PDO;
use PDOStatement;
use Touta\Ogam\Type\BaseTypeHandler;

/**
 * Type handler for integer values.
 */
final class IntegerHandler extends BaseTypeHandler
{
    public function getPhpType(): ?string
    {
        return 'int';
    }

    protected function setNonNullParameter(
        PDOStatement $statement,
        int|string $index,
        mixed $value,
        ?string $sqlType,
    ): void {
        $statement->bindValue($index, (int) $value, PDO::PARAM_INT);
    }

    protected function getNonNullResult(mixed $value): int
    {
        return (int) $value;
    }
}
