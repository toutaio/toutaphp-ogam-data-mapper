<?php

declare(strict_types=1);

namespace Touta\Ogam\Exception;

use PDOException;

/**
 * Exception for SQL execution errors.
 *
 * Wraps PDOException and provides additional context about
 * the SQL that was executed and its parameters.
 */
class SqlException extends OgamException
{
    private ?string $sql = null;

    /** @var array<mixed> */
    private array $parameters = [];

    private ?string $sqlState = null;

    /**
     * Create an SqlException from a PDOException.
     *
     * @param PDOException $e The original PDO exception
     * @param string|null $sql The SQL that was executed
     * @param array<mixed> $parameters The parameters that were bound
     */
    public static function fromPdoException(
        PDOException $e,
        ?string $sql = null,
        array $parameters = [],
    ): self {
        $message = self::formatMessage($e->getMessage(), $sql, $parameters);

        $exception = new self($message, (int) $e->getCode(), $e);
        $exception->sql = $sql;
        $exception->parameters = $parameters;

        // Extract SQLSTATE from PDOException
        if (isset($e->errorInfo[0]) && \is_string($e->errorInfo[0])) {
            $exception->sqlState = $e->errorInfo[0];
        } elseif (preg_match('/SQLSTATE\[([A-Z0-9]+)\]/', $e->getMessage(), $matches)) {
            $exception->sqlState = $matches[1];
        }

        return $exception;
    }

    /**
     * Format the exception message with SQL and parameters.
     *
     * @param array<mixed> $parameters
     */
    private static function formatMessage(
        string $originalMessage,
        ?string $sql,
        array $parameters,
    ): string {
        $parts = [$originalMessage];

        if ($sql !== null) {
            $parts[] = 'SQL: ' . $sql;
        }

        if (!empty($parameters)) {
            $parts[] = 'Parameters: ' . json_encode($parameters);
        }

        return implode("\n", $parts);
    }

    /**
     * Get the SQL that caused the exception.
     */
    public function getSql(): ?string
    {
        return $this->sql;
    }

    /**
     * Get the parameters that were bound to the SQL.
     *
     * @return array<mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get the SQLSTATE error code.
     */
    public function getSqlState(): ?string
    {
        return $this->sqlState;
    }
}
