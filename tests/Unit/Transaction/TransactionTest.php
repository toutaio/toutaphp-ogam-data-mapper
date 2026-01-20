<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Transaction;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Touta\Ogam\Transaction\Transaction;
use Touta\Ogam\Transaction\TransactionInterface;

final class TransactionTest extends TestCase
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
        $transaction = new Transaction($this->connection);

        $this->assertInstanceOf(TransactionInterface::class, $transaction);
    }

    public function testGetConnection(): void
    {
        $transaction = new Transaction($this->connection);

        $this->assertSame($this->connection, $transaction->getConnection());
    }

    public function testTransactionStartsOnConstruction(): void
    {
        $transaction = new Transaction($this->connection);

        $this->assertTrue($this->connection->inTransaction());
    }

    public function testCommit(): void
    {
        $transaction = new Transaction($this->connection);

        $this->connection->exec("INSERT INTO test (name) VALUES ('John')");
        $transaction->commit();

        $this->assertFalse($this->connection->inTransaction());

        $stmt = $this->connection->query('SELECT COUNT(*) FROM test');
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function testRollback(): void
    {
        $transaction = new Transaction($this->connection);

        $this->connection->exec("INSERT INTO test (name) VALUES ('John')");
        $transaction->rollback();

        $this->assertFalse($this->connection->inTransaction());

        $stmt = $this->connection->query('SELECT COUNT(*) FROM test');
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testClose(): void
    {
        $transaction = new Transaction($this->connection);

        $this->connection->exec("INSERT INTO test (name) VALUES ('John')");
        $transaction->close();

        $this->assertFalse($this->connection->inTransaction());

        $stmt = $this->connection->query('SELECT COUNT(*) FROM test');
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testCloseIsIdempotent(): void
    {
        $transaction = new Transaction($this->connection);
        $transaction->close();
        $transaction->close();

        $this->assertFalse($this->connection->inTransaction());
    }

    public function testCommitAfterCloseThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction is already closed');

        $transaction = new Transaction($this->connection);
        $transaction->close();
        $transaction->commit();
    }

    public function testRollbackAfterCloseThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transaction is already closed');

        $transaction = new Transaction($this->connection);
        $transaction->close();
        $transaction->rollback();
    }

    public function testGetIsolationLevelDefault(): void
    {
        $transaction = new Transaction($this->connection);

        $this->assertNull($transaction->getIsolationLevel());
    }

    public function testCommitWhenNotInTransactionIsNoop(): void
    {
        $transaction = new Transaction($this->connection);
        $transaction->commit();
        $transaction = new Transaction($this->connection);
        $transaction->commit();

        $this->assertFalse($this->connection->inTransaction());
    }

    public function testRollbackWhenNotInTransactionIsNoop(): void
    {
        $transaction = new Transaction($this->connection);
        $transaction->commit();
        $transaction = new Transaction($this->connection);
        $this->connection->commit();
        $transaction->rollback();

        $this->assertFalse($this->connection->inTransaction());
    }

    public function testCloseWhenNotInTransactionIsNoop(): void
    {
        $transaction = new Transaction($this->connection);
        $transaction->commit();
        $transaction = new Transaction($this->connection);
        $this->connection->commit();
        $transaction->close();

        $this->assertFalse($this->connection->inTransaction());
    }

    public function testDoesNotStartTransactionIfAlreadyInOne(): void
    {
        $this->connection->beginTransaction();
        $transaction = new Transaction($this->connection);

        $this->assertTrue($this->connection->inTransaction());
        $transaction->commit();
        $this->assertFalse($this->connection->inTransaction());
    }
}
