<?php

declare(strict_types=1);

namespace Touta\Ogam\Exception;

/**
 * Thrown when there's an error in type handling or conversion.
 */
final class TypeException extends OgamException
{
    public static function unsupportedType(string $type): self
    {
        return new self(\sprintf('Unsupported type: %s', $type));
    }

    public static function conversionFailed(string $fromType, string $toType, string $reason): self
    {
        return new self(\sprintf(
            'Failed to convert %s to %s: %s',
            $fromType,
            $toType,
            $reason,
        ));
    }

    public static function invalidEnumValue(string $enumClass, mixed $value): self
    {
        return new self(\sprintf(
            'Value "%s" is not valid for enum %s',
            (string) $value,
            $enumClass,
        ));
    }
}
