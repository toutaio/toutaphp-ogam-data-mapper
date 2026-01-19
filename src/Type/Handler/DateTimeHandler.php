<?php

declare(strict_types=1);

namespace Touta\Ogam\Type\Handler;

use DateTimeInterface;
use PDO;
use PDOStatement;
use Touta\Ogam\Type\BaseTypeHandler;

/**
 * Type handler for DateTime values.
 *
 * Returns mutable DateTime objects.
 */
final class DateTimeHandler extends BaseTypeHandler
{
    private const DEFAULT_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly string $format = self::DEFAULT_FORMAT,
    ) {}

    public function getPhpType(): ?string
    {
        return \DateTime::class;
    }

    protected function setNonNullParameter(
        PDOStatement $statement,
        int|string $index,
        mixed $value,
        ?string $sqlType,
    ): void {
        if ($value instanceof DateTimeInterface) {
            $formatted = $value->format($this->format);
        } else {
            $formatted = (string) $value;
        }

        $statement->bindValue($index, $formatted, PDO::PARAM_STR);
    }

    protected function getNonNullResult(mixed $value): \DateTime
    {
        if ($value instanceof \DateTime) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return \DateTime::createFromInterface($value);
        }

        // Try parsing as string
        $dateTime = \DateTime::createFromFormat($this->format, (string) $value);

        if ($dateTime === false) {
            // Fall back to natural parsing
            $dateTime = new \DateTime((string) $value);
        }

        return $dateTime;
    }
}
