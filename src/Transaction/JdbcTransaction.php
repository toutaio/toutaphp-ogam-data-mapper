<?php

declare(strict_types=1);

namespace Touta\Ogam\Transaction;

use PDO;

/**
 * JDBC-style transaction that commits on close unless autocommit is disabled.
 *
 * Similar to MyBatis JdbcTransaction behavior.
 */
final class JdbcTransaction implements TransactionInterface
{
    private bool $closed = false;

    private bool $autoCommit;

    public function __construct(
        private readonly PDO $connection,
        private readonly ?int $isolationLevel = null,
        ?bool $autoCommit = null,
    ) {
        // Get current autocommit setting or use provided value
        $this->autoCommit = $autoCommit ?? (bool) $this->connection->getAttribute(PDO::ATTR_AUTOCOMMIT);

        if ($this->isolationLevel !== null) {
            $this->setIsolationLevel($this->isolationLevel);
        }

        if (!$this->autoCommit && !$this->connection->inTransaction()) {
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
            throw new \RuntimeException('Transaction is already closed');
        }

        if (!$this->autoCommit && $this->connection->inTransaction()) {
            $this->connection->commit();

            // Start a new transaction
            $this->connection->beginTransaction();
        }
    }

    public function rollback(): void
    {
        if ($this->closed) {
            throw new \RuntimeException('Transaction is already closed');
        }

        if (!$this->autoCommit && $this->connection->inTransaction()) {
            $this->connection->rollBack();

            // Start a new transaction
            $this->connection->beginTransaction();
        }
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        if (!$this->autoCommit && $this->connection->inTransaction()) {
            $this->connection->rollBack();
        }

        $this->closed = true;
    }

    public function getIsolationLevel(): ?int
    {
        return $this->isolationLevel;
    }

    public function isAutoCommit(): bool
    {
        return $this->autoCommit;
    }

    public function setAutoCommit(bool $autoCommit): void
    {
        if ($autoCommit === $this->autoCommit) {
            return;
        }

        if ($autoCommit) {
            // Switching from manual to auto - commit current transaction
            if ($this->connection->inTransaction()) {
                $this->connection->commit();
            }
        } else {
            // Switching from auto to manual - start transaction
            if (!$this->connection->inTransaction()) {
                $this->connection->beginTransaction();
            }
        }

        $this->autoCommit = $autoCommit;
    }

    private function setIsolationLevel(int $level): void
    {
        $levelString = match ($level) {
            1 => 'READ UNCOMMITTED',
            2 => 'READ COMMITTED',
            4 => 'REPEATABLE READ',
            8 => 'SERIALIZABLE',
            default => throw new \InvalidArgumentException(
                \sprintf('Invalid isolation level: %d', $level),
            ),
        };

        $this->connection->exec(\sprintf('SET TRANSACTION ISOLATION LEVEL %s', $levelString));
    }
}
