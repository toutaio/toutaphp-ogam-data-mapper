<?php

declare(strict_types=1);

namespace Touta\Ogam\Transaction;

use PDO;

/**
 * Factory for creating transactions.
 */
interface TransactionFactory
{
    /**
     * Create a new transaction.
     *
     * @param PDO $connection The database connection
     *
     * @return TransactionInterface The transaction
     */
    public function newTransaction(PDO $connection): TransactionInterface;
}
