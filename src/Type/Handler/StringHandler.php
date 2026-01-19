<?php

declare(strict_types=1);

namespace Touta\Ogam\Type\Handler;

use PDO;
use PDOStatement;
use Touta\Ogam\Type\BaseTypeHandler;

/**
 * Type handler for string values.
 */
final class StringHandler extends BaseTypeHandler
{
    public function getPhpType(): ?string
    {
        return 'string';
    }

    protected function setNonNullParameter(
        PDOStatement $statement,
        int|string $index,
        mixed $value,
        ?string $sqlType,
    ): void {
        $statement->bindValue($index, (string) $value, PDO::PARAM_STR);
    }

    protected function getNonNullResult(mixed $value): string
    {
        return (string) $value;
    }
}
