<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Transaction;

use PDO;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Transaction\PdoTransaction;
use Touta\Ogam\Transaction\PdoTransactionFactory;
use Touta\Ogam\Transaction\TransactionFactory;

final class PdoTransactionFactoryTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    public function testImplementsTransactionFactory(): void
    {
        $factory = new PdoTransactionFactory(autoCommit: true);

        $this->assertInstanceOf(TransactionFactory::class, $factory);
    }

    public function testNewTransactionReturnsPdoTransaction(): void
    {
        $factory = new PdoTransactionFactory(autoCommit: true);
        $transaction = $factory->newTransaction($this->connection);

        $this->assertInstanceOf(PdoTransaction::class, $transaction);
    }

    public function testNewTransactionWithDefaultIsolationLevel(): void
    {
        $factory = new PdoTransactionFactory(autoCommit: true);
        $transaction = $factory->newTransaction($this->connection);

        $this->assertNull($transaction->getIsolationLevel());
    }

    public function testNewTransactionWithExplicitAutoCommitTrue(): void
    {
        $factory = new PdoTransactionFactory(autoCommit: true);
        $transaction = $factory->newTransaction($this->connection);

        $this->assertTrue($transaction->isAutoCommit());
    }

    public function testNewTransactionWithExplicitAutoCommitFalse(): void
    {
        $factory = new PdoTransactionFactory(autoCommit: false);
        $transaction = $factory->newTransaction($this->connection);

        $this->assertFalse($transaction->isAutoCommit());
    }

    public function testMultipleTransactionsCreated(): void
    {
        $factory = new PdoTransactionFactory(autoCommit: true);

        $transaction1 = $factory->newTransaction($this->connection);
        $transaction2 = $factory->newTransaction($this->connection);

        $this->assertNotSame($transaction1, $transaction2);
    }
}
