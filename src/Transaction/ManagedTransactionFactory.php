<?php

declare(strict_types=1);

namespace Touta\Ogam\Transaction;

use PDO;

/**
 * Factory that creates managed transactions.
 *
 * The transaction controls when commit/rollback happens.
 */
final class ManagedTransactionFactory implements TransactionFactory
{
    public function __construct(
        private readonly ?int $defaultIsolationLevel = null,
    ) {}

    public function newTransaction(PDO $connection): TransactionInterface
    {
        return new Transaction($connection, $this->defaultIsolationLevel);
    }
}
