<?php

declare(strict_types=1);

namespace Touta\Ogam\Exception;

/**
 * Thrown when there's an error parsing XML or SQL.
 */
final class ParsingException extends OgamException
{
    public static function invalidXml(string $file, string $reason): self
    {
        return new self(\sprintf(
            'Failed to parse XML file "%s": %s',
            $file,
            $reason,
        ));
    }

    public static function missingAttribute(string $element, string $attribute): self
    {
        return new self(\sprintf(
            'Element <%s> is missing required attribute "%s"',
            $element,
            $attribute,
        ));
    }

    public static function invalidExpression(string $expression, string $reason): self
    {
        return new self(\sprintf(
            'Invalid expression "%s": %s',
            $expression,
            $reason,
        ));
    }

    public static function fileNotFound(string $path): self
    {
        return new self(\sprintf('File not found: %s', $path));
    }
}
