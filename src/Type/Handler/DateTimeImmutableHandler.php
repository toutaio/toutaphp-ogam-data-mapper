<?php

declare(strict_types=1);

namespace Touta\Ogam\Type\Handler;

use DateTimeImmutable;
use DateTimeInterface;
use PDO;
use PDOStatement;
use Stringable;
use Touta\Ogam\Type\BaseTypeHandler;

/**
 * Type handler for DateTimeImmutable values.
 */
final class DateTimeImmutableHandler extends BaseTypeHandler
{
    private const DEFAULT_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly string $format = self::DEFAULT_FORMAT,
    ) {}

    public function getPhpType(): string
    {
        return DateTimeImmutable::class;
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
            $formatted = \is_scalar($value) || $value instanceof Stringable ? (string) $value : '';
        }

        $statement->bindValue($index, $formatted, PDO::PARAM_STR);
    }

    protected function getNonNullResult(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        // Try parsing as string
        $stringValue = \is_scalar($value) || $value instanceof Stringable ? (string) $value : '';
        $dateTime = DateTimeImmutable::createFromFormat($this->format, $stringValue);

        if ($dateTime === false) {
            // Fall back to natural parsing
            $dateTime = new DateTimeImmutable($stringValue);
        }

        return $dateTime;
    }
}
