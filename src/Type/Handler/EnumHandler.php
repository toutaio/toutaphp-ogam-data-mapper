<?php

declare(strict_types=1);

namespace Touta\Ogam\Type\Handler;

use BackedEnum;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use Touta\Ogam\Type\BaseTypeHandler;
use UnitEnum;
use ValueError;

/**
 * Type handler for enum values (both backed and unit enums).
 *
 * @template T of UnitEnum
 */
final class EnumHandler extends BaseTypeHandler
{
    /**
     * @param class-string<T> $enumClass The enum class name
     */
    public function __construct(
        private readonly string $enumClass,
    ) {
        if (!enum_exists($this->enumClass)) {
            throw new InvalidArgumentException(
                \sprintf('Class "%s" is not an enum', $this->enumClass),
            );
        }
    }

    public function getPhpType(): ?string
    {
        return $this->enumClass;
    }

    protected function setNonNullParameter(
        PDOStatement $statement,
        int|string $index,
        mixed $value,
        ?string $sqlType,
    ): void {
        if ($value instanceof BackedEnum) {
            $dbValue = $value->value;
            $paramType = \is_int($dbValue) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue($index, $dbValue, $paramType);
        } elseif ($value instanceof UnitEnum) {
            // For unit enums, store the name
            $statement->bindValue($index, $value->name, PDO::PARAM_STR);
        } else {
            // Raw value being bound
            $paramType = \is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue($index, $value, $paramType);
        }
    }

    /**
     * @return T
     */
    protected function getNonNullResult(mixed $value): UnitEnum
    {
        $enumClass = $this->enumClass;

        // Already the right type
        if ($value instanceof $enumClass) {
            return $value;
        }

        // For backed enums, use from() or tryFrom()
        if (is_subclass_of($enumClass, BackedEnum::class)) {
            /** @var class-string<BackedEnum&T> $enumClass */
            $enum = $enumClass::tryFrom($value);

            if ($enum === null) {
                throw new ValueError(
                    \sprintf(
                        'Value "%s" is not a valid backing value for enum %s',
                        $value,
                        $enumClass,
                    ),
                );
            }

            return $enum;
        }

        // For unit enums, match by name
        foreach ($enumClass::cases() as $case) {
            if ($case->name === $value) {
                return $case;
            }
        }

        throw new ValueError(
            \sprintf(
                'Value "%s" is not a valid case name for enum %s',
                $value,
                $enumClass,
            ),
        );
    }
}
