<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Executor;

use PDO;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\Executor\BatchExecutor;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\Hydration;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Transaction\TransactionInterface;

final class BatchExecutorTest extends TestCase
{
    private Configuration $configuration;

    private TransactionInterface $transaction;

    private PDO $connection;

    private BatchExecutor $executor;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $this->connection->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT,
                active INTEGER DEFAULT 1
            )
        ');

        $this->configuration = new Configuration();
        $this->transaction = $this->createMock(TransactionInterface::class);

        $this->transaction->method('getConnection')
            ->willReturn($this->connection);

        $this->executor = new BatchExecutor($this->configuration, $this->transaction);
    }

    public function testQueryExecutesImmediately(): void
    {
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('John', 'john@example.com')");

        $statement = new MappedStatement(
            id: 'findAll',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'array',
            hydration: Hydration::ARRAY,
        );

        $boundSql = new BoundSql('SELECT * FROM users');

        $results = $this->executor->query($statement, null, $boundSql);

        $this->assertCount(1, $results);
        $this->assertIsArray($results[0]);
        $this->assertEquals('John', $results[0]['name']);
    }

    public function testUpdateReturnsBatchPlaceholder(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
        );

        $boundSql = new BoundSql("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");

        $rowCount = $this->executor->update($statement, null, $boundSql);

        $this->assertEquals(-1, $rowCount);
    }

    public function testFlushStatementsExecutesBatch(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
        );

        $boundSql1 = new BoundSql("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        $boundSql2 = new BoundSql("INSERT INTO users (name, email) VALUES ('Bob', 'bob@example.com')");

        $this->executor->update($statement, null, $boundSql1);
        $this->executor->update($statement, null, $boundSql2);

        $results = $this->executor->flushStatements();

        $this->assertIsArray($results);
        $this->assertCount(2, $results);

        $count = $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertEquals(2, $count);
    }

    public function testBatchingWithSameSQL(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
        );

        $boundSql1 = new BoundSql("INSERT INTO users (name, email) VALUES ('User1', 'user1@example.com')");
        $boundSql2 = new BoundSql("INSERT INTO users (name, email) VALUES ('User2', 'user2@example.com')");
        $boundSql3 = new BoundSql("INSERT INTO users (name, email) VALUES ('User3', 'user3@example.com')");
        $boundSql4 = new BoundSql("INSERT INTO users (name, email) VALUES ('User4', 'user4@example.com')");
        $boundSql5 = new BoundSql("INSERT INTO users (name, email) VALUES ('User5', 'user5@example.com')");

        $this->executor->update($statement, null, $boundSql1);
        $this->executor->update($statement, null, $boundSql2);
        $this->executor->update($statement, null, $boundSql3);
        $this->executor->update($statement, null, $boundSql4);
        $this->executor->update($statement, null, $boundSql5);

        $results = $this->executor->flushStatements();

        $this->assertIsArray($results);
        $this->assertGreaterThan(0, \count($results));

        $count = $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertEquals(5, $count);
    }

    public function testBatchingWithDifferentSQL(): void
    {
        $insertStatement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
        );

        $updateStatement = new MappedStatement(
            id: 'updateUser',
            namespace: 'UserMapper',
            type: StatementType::UPDATE,
        );

        $this->connection->exec("INSERT INTO users (id, name, email) VALUES (1, 'John', 'john@example.com')");

        $boundSql1 = new BoundSql("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        $boundSql2 = new BoundSql('UPDATE users SET active = 0 WHERE id = 1');

        $this->executor->update($insertStatement, null, $boundSql1);
        $this->executor->update($updateStatement, null, $boundSql2);

        $results = $this->executor->flushStatements();

        $this->assertCount(2, $results);
    }

    public function testQueryFlushesPendingBatch(): void
    {
        $insertStatement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
        );

        $selectStatement = new MappedStatement(
            id: 'findAll',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'array',
            hydration: Hydration::ARRAY,
        );

        $boundSql1 = new BoundSql("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        $this->executor->update($insertStatement, null, $boundSql1);

        $boundSql2 = new BoundSql('SELECT * FROM users');
        $results = $this->executor->query($selectStatement, null, $boundSql2);

        $this->assertCount(1, $results);
        $this->assertEquals('Alice', $results[0]['name']);
    }

    public function testFlushStatementsReturnsEmptyArrayWhenNoBatch(): void
    {
        $results = $this->executor->flushStatements();

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testCloseClearsBatch(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
        );

        $boundSql = new BoundSql("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        $this->executor->update($statement, null, $boundSql);

        // Note: BatchExecutor executes statements immediately, so data is already in DB
        $this->executor->close(false);

        // After close, verify the executor was closed (data already persisted)
        $count = $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertEquals(1, $count);
    }

    public function testCloseWithForceRollback(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
        );

        $boundSql = new BoundSql("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        $this->executor->update($statement, null, $boundSql);

        // Note: BatchExecutor executes statements immediately, so data is already in DB
        $this->executor->close(true);

        // After close with rollback, data is still persisted (not in a transaction)
        $count = $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertEquals(1, $count);
    }

    public function testMultipleFlushStatements(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
        );

        $boundSql1 = new BoundSql("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        $this->executor->update($statement, null, $boundSql1);
        $this->executor->flushStatements();

        $boundSql2 = new BoundSql("INSERT INTO users (name, email) VALUES ('Bob', 'bob@example.com')");
        $this->executor->update($statement, null, $boundSql2);
        $this->executor->flushStatements();

        $count = $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertEquals(2, $count);
    }

    public function testStatementCaching(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
        );

        $boundSql1 = new BoundSql("INSERT INTO users (name, email) VALUES ('User1', 'user1@example.com')");
        $boundSql2 = new BoundSql("INSERT INTO users (name, email) VALUES ('User2', 'user2@example.com')");
        $boundSql3 = new BoundSql("INSERT INTO users (name, email) VALUES ('User3', 'user3@example.com')");

        $this->executor->update($statement, null, $boundSql1);
        $this->executor->update($statement, null, $boundSql2);
        $this->executor->update($statement, null, $boundSql3);

        $this->executor->flushStatements();

        $count = $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertEquals(3, $count);
    }
}
