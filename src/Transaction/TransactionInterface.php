<?php

declare(strict_types=1);

namespace Touta\Ogam\Transaction;

use PDO;

/**
 * Represents a database transaction.
 */
interface TransactionInterface
{
    /**
     * Get the underlying database connection.
     */
    public function getConnection(): PDO;

    /**
     * Commit the transaction.
     */
    public function commit(): void;

    /**
     * Rollback the transaction.
     */
    public function rollback(): void;

    /**
     * Close the transaction and release resources.
     */
    public function close(): void;

    /**
     * Get the transaction isolation level.
     */
    public function getIsolationLevel(): ?int;
}
