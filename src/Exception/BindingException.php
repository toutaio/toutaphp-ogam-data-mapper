<?php

declare(strict_types=1);

namespace Touta\Ogam\Exception;

/**
 * Thrown when there's an error binding parameters or results.
 */
final class BindingException extends OgamException
{
    public static function statementNotFound(string $id): self
    {
        return new self(\sprintf('Mapped statement "%s" not found', $id));
    }

    public static function resultMapNotFound(string $id): self
    {
        return new self(\sprintf('Result map "%s" not found', $id));
    }

    public static function parameterMissing(string $name): self
    {
        return new self(\sprintf('Parameter "%s" is required but was not provided', $name));
    }

    public static function invalidMapKey(string $key): self
    {
        return new self(\sprintf('Map key "%s" not found in result', $key));
    }
}
