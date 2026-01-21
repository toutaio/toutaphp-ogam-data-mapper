<?php

declare(strict_types=1);

namespace Touta\Ogam\Sql\Expression;

use Touta\Ogam\Exception\OgamException;

/**
 * Exception thrown when an expression contains dangerous constructs.
 */
final class ExpressionSecurityException extends OgamException
{
    public static function functionCallsNotAllowed(string $expression): self
    {
        return new self(\sprintf(
            'Function calls are not allowed in expressions: %s',
            $expression,
        ));
    }

    public static function methodCallsNotAllowed(string $expression): self
    {
        return new self(\sprintf(
            'Method calls are not allowed in expressions: %s',
            $expression,
        ));
    }

    public static function assignmentsNotAllowed(string $expression): self
    {
        return new self(\sprintf(
            'Assignments are not allowed in expressions: %s',
            $expression,
        ));
    }

    public static function incrementDecrementNotAllowed(string $expression): self
    {
        return new self(\sprintf(
            'Increment/decrement operators are not allowed in expressions: %s',
            $expression,
        ));
    }

    public static function shellExecutionNotAllowed(string $expression): self
    {
        return new self(\sprintf(
            'Shell execution is not allowed in expressions: %s',
            $expression,
        ));
    }

    public static function includeRequireNotAllowed(string $expression): self
    {
        return new self(\sprintf(
            'Include/require constructs are not allowed in expressions: %s',
            $expression,
        ));
    }

    public static function objectInstantiationNotAllowed(string $expression): self
    {
        return new self(\sprintf(
            'Object instantiation is not allowed in expressions: %s',
            $expression,
        ));
    }

    public static function staticAccessNotAllowed(string $expression): self
    {
        return new self(\sprintf(
            'Static access is not allowed in expressions: %s',
            $expression,
        ));
    }

    public static function arrayAccessNotAllowed(string $expression): self
    {
        return new self(\sprintf(
            'Array access syntax is not allowed in expressions: %s',
            $expression,
        ));
    }

    public static function globalVariablesNotAllowed(string $expression): self
    {
        return new self(\sprintf(
            'Global variables are not allowed in expressions: %s',
            $expression,
        ));
    }

    public static function variableVariablesNotAllowed(string $expression): self
    {
        return new self(\sprintf(
            'Variable variables are not allowed in expressions: %s',
            $expression,
        ));
    }

    public static function anonymousFunctionsNotAllowed(string $expression): self
    {
        return new self(\sprintf(
            'Anonymous functions are not allowed in expressions: %s',
            $expression,
        ));
    }
}
