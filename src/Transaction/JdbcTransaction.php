<?php

declare(strict_types=1);

namespace Touta\Ogam\Transaction;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * JDBC-style transaction that commits on close unless autocommit is disabled.
 *
 * Similar to MyBatis JdbcTransaction behavior.
 */
final class JdbcTransaction implements TransactionInterface
{
    private bool $closed = false;

    private bool $autoCommit;

    /** @var array<string, bool> Active savepoints indexed by name */
    private array $savepoints = [];

    private int $savepointCounter = 0;

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
            throw new RuntimeException('Transaction is already closed');
        }

        if (!$this->autoCommit && $this->connection->inTransaction()) {
            $this->connection->commit();

            // Clear all savepoints on commit
            $this->savepoints = [];

            // Start a new transaction
            $this->connection->beginTransaction();
        }
    }

    public function rollback(): void
    {
        if ($this->closed) {
            throw new RuntimeException('Transaction is already closed');
        }

        if (!$this->autoCommit && $this->connection->inTransaction()) {
            $this->connection->rollBack();

            // Clear all savepoints on rollback
            $this->savepoints = [];

            // Start a new transaction
            $this->connection->beginTransaction();
        }
    }

    /**
     * Create a savepoint with the given name.
     *
     * @param string|null $name The savepoint name (auto-generated if null)
     *
     * @return string The savepoint name
     */
    public function createSavepoint(?string $name = null): string
    {
        if ($this->closed) {
            throw new RuntimeException('Transaction is already closed');
        }

        if ($name === null) {
            $name = 'ogam_savepoint_' . ++$this->savepointCounter;
        }

        $this->connection->exec(\sprintf('SAVEPOINT %s', $name));
        $this->savepoints[$name] = true;

        return $name;
    }

    /**
     * Release a savepoint (making changes permanent within the transaction).
     *
     * @param string $name The savepoint name
     */
    public function releaseSavepoint(string $name): void
    {
        if ($this->closed) {
            throw new RuntimeException('Transaction is already closed');
        }

        if (!isset($this->savepoints[$name])) {
            throw new RuntimeException(\sprintf('Savepoint "%s" does not exist', $name));
        }

        $this->connection->exec(\sprintf('RELEASE SAVEPOINT %s', $name));
        unset($this->savepoints[$name]);
    }

    /**
     * Rollback to a savepoint (undoing changes since the savepoint was created).
     *
     * @param string $name The savepoint name
     */
    public function rollbackToSavepoint(string $name): void
    {
        if ($this->closed) {
            throw new RuntimeException('Transaction is already closed');
        }

        if (!isset($this->savepoints[$name])) {
            throw new RuntimeException(\sprintf('Savepoint "%s" does not exist', $name));
        }

        // When rolling back to a savepoint, all savepoints created after it
        // should be invalidated and removed from the internal tracking.
        $savepointNames = \array_keys($this->savepoints);
        $position = \array_search($name, $savepointNames, true);

        if ($position !== false) {
            $count = \count($savepointNames);
            for ($i = $position + 1; $i < $count; $i++) {
                unset($this->savepoints[$savepointNames[$i]]);
            }
        }
        $this->connection->exec(\sprintf('ROLLBACK TO SAVEPOINT %s', $name));
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
            default => throw new InvalidArgumentException(
                \sprintf('Invalid isolation level: %d', $level),
            ),
        };

        $this->connection->exec(\sprintf('SET TRANSACTION ISOLATION LEVEL %s', $levelString));
    }
}
