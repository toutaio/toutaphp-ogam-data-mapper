<?php

declare(strict_types=1);

namespace Touta\Ogam\Exception;

/**
 * Thrown when there's an error executing SQL.
 */
final class ExecutorException extends OgamException
{
    public static function queryFailed(string $sql, string $reason): self
    {
        return new self(\sprintf(
            'Query execution failed: %s. SQL: %s',
            $reason,
            $sql,
        ));
    }

    public static function updateFailed(string $sql, string $reason): self
    {
        return new self(\sprintf(
            'Update execution failed: %s. SQL: %s',
            $reason,
            $sql,
        ));
    }

    public static function transactionFailed(string $reason): self
    {
        return new self(\sprintf('Transaction failed: %s', $reason));
    }

    public static function sessionClosed(): self
    {
        return new self('Cannot perform operation: session is closed');
    }
}
