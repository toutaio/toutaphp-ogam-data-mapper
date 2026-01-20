<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Transaction;

use PDO;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Transaction\JdbcTransaction;
use Touta\Ogam\Transaction\JdbcTransactionFactory;
use Touta\Ogam\Transaction\TransactionFactory;

final class JdbcTransactionFactoryTest extends TestCase
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
        $factory = new JdbcTransactionFactory(autoCommit: true);

        $this->assertInstanceOf(TransactionFactory::class, $factory);
    }

    public function testNewTransactionReturnsJdbcTransaction(): void
    {
        $factory = new JdbcTransactionFactory(autoCommit: true);
        $transaction = $factory->newTransaction($this->connection);

        $this->assertInstanceOf(JdbcTransaction::class, $transaction);
    }

    public function testNewTransactionWithDefaultIsolationLevel(): void
    {
        $factory = new JdbcTransactionFactory(autoCommit: true);
        $transaction = $factory->newTransaction($this->connection);

        $this->assertNull($transaction->getIsolationLevel());
    }

    public function testNewTransactionWithExplicitAutoCommitTrue(): void
    {
        $factory = new JdbcTransactionFactory(autoCommit: true);
        $transaction = $factory->newTransaction($this->connection);

        $this->assertTrue($transaction->isAutoCommit());
    }

    public function testNewTransactionWithExplicitAutoCommitFalse(): void
    {
        $factory = new JdbcTransactionFactory(autoCommit: false);
        $transaction = $factory->newTransaction($this->connection);

        $this->assertFalse($transaction->isAutoCommit());
    }

    public function testMultipleTransactionsCreated(): void
    {
        $factory = new JdbcTransactionFactory(autoCommit: true);

        $transaction1 = $factory->newTransaction($this->connection);
        $transaction2 = $factory->newTransaction($this->connection);

        $this->assertNotSame($transaction1, $transaction2);
    }
}
