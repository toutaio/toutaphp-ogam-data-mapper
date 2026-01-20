<?php

declare(strict_types=1);

namespace Touta\Ogam\Transaction;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Default transaction implementation using PDO transactions.
 */
final class Transaction implements TransactionInterface
{
    private bool $closed = false;

    public function __construct(
        private readonly PDO $connection,
        private readonly ?int $isolationLevel = null,
    ) {
        if ($this->isolationLevel !== null) {
            $this->setIsolationLevel($this->isolationLevel);
        }

        if (!$this->connection->inTransaction()) {
            $this->connection->beginTransaction();
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function commit(): void
    {
        if ($this->closed) {
            throw new RuntimeException('Transaction is already closed');
        }

        if ($this->connection->inTransaction()) {
            $this->connection->commit();
        }
    }

    public function rollback(): void
    {
        if ($this->closed) {
            throw new RuntimeException('Transaction is already closed');
        }

        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        // Rollback if still in transaction
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }

        $this->closed = true;
    }

    public function getIsolationLevel(): ?int
    {
        return $this->isolationLevel;
    }

    private function setIsolationLevel(int $level): void
    {
        $levelString = match ($level) {
            PDO::ATTR_DEFAULT_FETCH_MODE => 'READ UNCOMMITTED',
            1 => 'READ UNCOMMITTED',
            2 => 'READ COMMITTED',
            4 => 'REPEATABLE READ',
            8 => 'SERIALIZABLE',
            default => throw new InvalidArgumentException(
                \sprintf('Invalid isolation level: %d', $level),
            ),
        };

        $this->connection->exec(\sprintf('SET TRANSACTION ISOLATION LEVEL %s', $levelString));
    }
}
