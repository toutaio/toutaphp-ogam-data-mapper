<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Transaction;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Touta\Ogam\Transaction\JdbcTransaction;
use Touta\Ogam\Transaction\TransactionInterface;

final class JdbcTransactionTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $this->connection->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
    }

    public function testImplementsTransactionInterface(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: true);

        $this->assertInstanceOf(TransactionInterface::class, $transaction);
    }

    public function testGetConnection(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: true);

        $this->assertSame($this->connection, $transaction->getConnection());
    }

    public function testGetIsolationLevelDefault(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: true);

        $this->assertNull($transaction->getIsolationLevel());
    }

    public function testIsAutoCommitExplicitTrue(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: true);

        $this->assertTrue($transaction->isAutoCommit());
    }

    public function testAutoCommitExplicitFalse(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: false);

        $this->assertFalse($transaction->isAutoCommit());
        $this->assertTrue($this->connection->inTransaction());
    }

    public function testAutoCommitExplicitTrue(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: true);

        $this->assertTrue($transaction->isAutoCommit());
        $this->assertFalse($this->connection->inTransaction());
    }

    public function testCommitWithAutoCommitStartsNewTransaction(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: false);

        $this->connection->exec("INSERT INTO test (name) VALUES ('John')");
        $transaction->commit();

        $this->assertTrue($this->connection->inTransaction());

        $stmt = $this->connection->query('SELECT COUNT(*) FROM test');
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function testRollbackWithAutoCommitStartsNewTransaction(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: false);

        $this->connection->exec("INSERT INTO test (name) VALUES ('John')");
        $transaction->rollback();

        $this->assertTrue($this->connection->inTransaction());

        $stmt = $this->connection->query('SELECT COUNT(*) FROM test');
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testCommitWithAutoCommitTrueIsNoop(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: true);
        $transaction->commit();

        $this->assertTrue($transaction->isAutoCommit());
    }

    public function testRollbackWithAutoCommitTrueIsNoop(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: true);
        $transaction->rollback();

        $this->assertTrue($transaction->isAutoCommit());
    }

    public function testClose(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: false);

        $this->connection->exec("INSERT INTO test (name) VALUES ('John')");
        $transaction->close();

        $this->assertFalse($this->connection->inTransaction());

        $stmt = $this->connection->query('SELECT COUNT(*) FROM test');
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testCloseIsIdempotent(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: false);
        $transaction->close();
        $transaction->close();

        $this->assertFalse($this->connection->inTransaction());
    }

    public function testCommitAfterCloseThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction is already closed');

        $transaction = new JdbcTransaction($this->connection, autoCommit: true);
        $transaction->close();
        $transaction->commit();
    }

    public function testRollbackAfterCloseThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction is already closed');

        $transaction = new JdbcTransaction($this->connection, autoCommit: true);
        $transaction->close();
        $transaction->rollback();
    }

    public function testSetAutoCommitFromFalseToTrue(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: false);

        $this->connection->exec("INSERT INTO test (name) VALUES ('John')");
        $transaction->setAutoCommit(true);

        $this->assertTrue($transaction->isAutoCommit());
        $this->assertFalse($this->connection->inTransaction());

        $stmt = $this->connection->query('SELECT COUNT(*) FROM test');
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function testSetAutoCommitFromTrueToFalse(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: true);
        $transaction->setAutoCommit(false);

        $this->assertFalse($transaction->isAutoCommit());
        $this->assertTrue($this->connection->inTransaction());
    }

    public function testSetAutoCommitSameValueIsNoop(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: true);
        $transaction->setAutoCommit(true);

        $this->assertTrue($transaction->isAutoCommit());
    }

    public function testCloseWithAutoCommitTrueIsNoop(): void
    {
        $transaction = new JdbcTransaction($this->connection, autoCommit: true);
        $transaction->close();

        $this->assertFalse($this->connection->inTransaction());
    }

    public function testConstructorWithIsolationLevel(): void
    {
        // SQLite doesn't support SET TRANSACTION ISOLATION LEVEL
        $this->markTestSkipped('SQLite does not support SET TRANSACTION ISOLATION LEVEL');

        $transaction = new JdbcTransaction($this->connection, 2, false);

        $this->assertEquals(2, $transaction->getIsolationLevel());
        $transaction->close();
    }

    public function testCommitWhenNotInTransactionIsNoop(): void
    {
        $transaction = new JdbcTransaction($this->connection, null, false);
        $transaction->commit(); // Commit first transaction

        $transaction->commit(); // Should start and commit new transaction

        $this->assertTrue($this->connection->inTransaction());
        $transaction->close();
    }

    public function testRollbackWhenNotInTransactionIsNoop(): void
    {
        $transaction = new JdbcTransaction($this->connection, null, false);
        $transaction->rollback(); // Rollback first transaction

        $transaction->rollback(); // Should start and rollback new transaction

        $this->assertTrue($this->connection->inTransaction());
        $transaction->close();
    }
}
