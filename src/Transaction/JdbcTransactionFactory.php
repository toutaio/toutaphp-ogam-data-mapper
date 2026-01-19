<?php

declare(strict_types=1);

namespace Touta\Ogam\Transaction;

use PDO;

/**
 * Factory that creates JDBC-style transactions.
 */
final class JdbcTransactionFactory implements TransactionFactory
{
    public function __construct(
        private readonly ?int $defaultIsolationLevel = null,
        private readonly ?bool $autoCommit = null,
    ) {}

    public function newTransaction(PDO $connection): TransactionInterface
    {
        return new JdbcTransaction(
            $connection,
            $this->defaultIsolationLevel,
            $this->autoCommit,
        );
    }
}
