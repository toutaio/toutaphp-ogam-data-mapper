<?php

declare(strict_types=1);

namespace Touta\Ogam\Transaction;

use PDO;

/**
 * Factory that creates PDO-style transactions.
 */
final class PdoTransactionFactory implements TransactionFactory
{
    public function __construct(
        private readonly ?int $defaultIsolationLevel = null,
        private readonly ?bool $autoCommit = null,
    ) {}

    public function newTransaction(PDO $connection): TransactionInterface
    {
        return new PdoTransaction(
            $connection,
            $this->defaultIsolationLevel,
            $this->autoCommit,
        );
    }
}
