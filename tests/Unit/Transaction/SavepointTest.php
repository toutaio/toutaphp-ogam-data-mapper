<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Transaction;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Touta\Ogam\Transaction\PdoTransaction;

#[CoversClass(PdoTransaction::class)]
final class SavepointTest extends TestCase
{
    private PDO $pdo;

    private PdoTransaction $transaction;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

        $this->transaction = new PdoTransaction($this->pdo, null, false);
    }

    #[Test]
    public function canCreateSavepoint(): void
    {
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Alice')");

        $savepointName = $this->transaction->createSavepoint('sp1');

        $this->assertSame('sp1', $savepointName);
    }

    #[Test]
    public function canReleaseSavepoint(): void
    {
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->transaction->createSavepoint('sp1');
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Bob')");

        $this->transaction->releaseSavepoint('sp1');

        // Both inserts should be present
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertSame(2, $count);
    }

    #[Test]
    public function canRollbackToSavepoint(): void
    {
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->transaction->createSavepoint('sp1');
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Bob')");

        $this->transaction->rollbackToSavepoint('sp1');

        // Only Alice should be present
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertSame(1, $count);

        $name = $this->pdo->query('SELECT name FROM users')->fetchColumn();
        $this->assertSame('Alice', $name);
    }

    #[Test]
    public function canCreateNestedSavepoints(): void
    {
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->transaction->createSavepoint('sp1');

        $this->pdo->exec("INSERT INTO users (name) VALUES ('Bob')");
        $this->transaction->createSavepoint('sp2');

        $this->pdo->exec("INSERT INTO users (name) VALUES ('Charlie')");

        // Rollback to sp2 - only Charlie should be rolled back
        $this->transaction->rollbackToSavepoint('sp2');

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertSame(2, $count);

        // Rollback to sp1 - Bob should be rolled back too
        $this->transaction->rollbackToSavepoint('sp1');

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function createSavepointGeneratesUniqueName(): void
    {
        $sp1 = $this->transaction->createSavepoint();
        $sp2 = $this->transaction->createSavepoint();

        $this->assertNotSame($sp1, $sp2);
        $this->assertStringStartsWith('ogam_savepoint_', $sp1);
        $this->assertStringStartsWith('ogam_savepoint_', $sp2);
    }

    #[Test]
    public function rollbackToSavepointThrowsForInvalidSavepoint(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Savepoint "nonexistent" does not exist');

        $this->transaction->rollbackToSavepoint('nonexistent');
    }

    #[Test]
    public function releaseSavepointThrowsForInvalidSavepoint(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Savepoint "nonexistent" does not exist');

        $this->transaction->releaseSavepoint('nonexistent');
    }

    #[Test]
    public function savepointWorksAfterRollback(): void
    {
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Alice')");
        $this->transaction->createSavepoint('sp1');
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Bob')");

        $this->transaction->rollbackToSavepoint('sp1');

        // Create a new savepoint after rollback
        $this->transaction->createSavepoint('sp2');
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Charlie')");

        // Commit should work
        $this->transaction->commit();

        // Query in the new transaction (already started by commit)
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertSame(2, $count); // Alice and Charlie
    }

    #[Test]
    public function savepointsAreClearedOnCommit(): void
    {
        $this->transaction->createSavepoint('sp1');
        $this->transaction->commit();

        $this->expectException(RuntimeException::class);
        $this->transaction->rollbackToSavepoint('sp1');
    }

    #[Test]
    public function savepointsAreClearedOnRollback(): void
    {
        $this->transaction->createSavepoint('sp1');
        $this->transaction->rollback();

        $this->expectException(RuntimeException::class);
        $this->transaction->rollbackToSavepoint('sp1');
    }
}
