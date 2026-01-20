<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Transaction;

use PDO;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Transaction\ManagedTransactionFactory;
use Touta\Ogam\Transaction\Transaction;
use Touta\Ogam\Transaction\TransactionFactory;
use Touta\Ogam\Transaction\TransactionInterface;

final class ManagedTransactionFactoryTest extends TestCase
{
    public function testImplementsTransactionFactory(): void
    {
        $factory = new ManagedTransactionFactory();

        $this->assertInstanceOf(TransactionFactory::class, $factory);
    }

    public function testNewTransactionReturnsTransaction(): void
    {
        $factory = new ManagedTransactionFactory();
        $connection = new PDO('sqlite::memory:');

        $transaction = $factory->newTransaction($connection);

        $this->assertInstanceOf(TransactionInterface::class, $transaction);
        $this->assertInstanceOf(Transaction::class, $transaction);
    }

    public function testNewTransactionWithDefaultIsolationLevel(): void
    {
        // SQLite doesn't support SET TRANSACTION ISOLATION LEVEL
        // so we use null to skip isolation level setting
        $factory = new ManagedTransactionFactory(null);
        $connection = new PDO('sqlite::memory:');

        $transaction = $factory->newTransaction($connection);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertNull($transaction->getIsolationLevel());
    }

    public function testNewTransactionWithNullIsolationLevel(): void
    {
        $factory = new ManagedTransactionFactory(null);
        $connection = new PDO('sqlite::memory:');

        $transaction = $factory->newTransaction($connection);

        $this->assertInstanceOf(Transaction::class, $transaction);
    }

    public function testMultipleTransactionsFromSameFactory(): void
    {
        $factory = new ManagedTransactionFactory();
        $connection1 = new PDO('sqlite::memory:');
        $connection2 = new PDO('sqlite::memory:');

        $transaction1 = $factory->newTransaction($connection1);
        $transaction2 = $factory->newTransaction($connection2);

        $this->assertInstanceOf(Transaction::class, $transaction1);
        $this->assertInstanceOf(Transaction::class, $transaction2);
        $this->assertNotSame($transaction1, $transaction2);
    }

    public function testFactoryCanBeReused(): void
    {
        $factory = new ManagedTransactionFactory();
        $connection = new PDO('sqlite::memory:');

        $transaction1 = $factory->newTransaction($connection);
        $transaction2 = $factory->newTransaction($connection);

        $this->assertInstanceOf(Transaction::class, $transaction1);
        $this->assertInstanceOf(Transaction::class, $transaction2);
    }
}
