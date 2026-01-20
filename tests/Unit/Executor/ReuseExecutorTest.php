<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Executor;

use PDO;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\Executor\ReuseExecutor;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\Hydration;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Transaction\TransactionInterface;

final class ReuseExecutorTest extends TestCase
{
    private Configuration $configuration;

    private TransactionInterface $transaction;

    private PDO $connection;

    private ReuseExecutor $executor;

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

        $this->executor = new ReuseExecutor($this->configuration, $this->transaction);
    }

    public function testQueryReusesStatement(): void
    {
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('John', 'john@example.com')");

        $statement = new MappedStatement(
            id: 'findByName',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'stdClass',
            hydration: Hydration::OBJECT,
        );

        $boundSql = new BoundSql('SELECT * FROM users WHERE name = "John"');

        $results1 = $this->executor->query($statement, null, $boundSql);
        $results2 = $this->executor->query($statement, null, $boundSql);

        $this->assertCount(1, $results1);
        $this->assertCount(1, $results2);
    }

    public function testUpdateExecutesImmediately(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
        );

        $boundSql = new BoundSql("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");

        $rowCount = $this->executor->update($statement, null, $boundSql);

        $this->assertEquals(1, $rowCount);

        $count = $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertEquals(1, $count);
    }

    public function testUpdateReusesStatement(): void
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

        $count = $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertEquals(2, $count);
    }

    public function testFlushStatementsReturnsEmpty(): void
    {
        $results = $this->executor->flushStatements();

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testCloseClearsStatementCache(): void
    {
        $statement = new MappedStatement(
            id: 'findAll',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'stdClass',
            hydration: Hydration::OBJECT,
        );

        $boundSql = new BoundSql('SELECT * FROM users');

        $this->executor->query($statement, null, $boundSql);
        $this->executor->close(false);

        // After close, create a new executor to continue
        $newExecutor = new ReuseExecutor($this->configuration, $this->transaction);
        $newExecutor->query($statement, null, $boundSql);

        $this->assertTrue(true);
    }

    public function testQueryWithDifferentSQL(): void
    {
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('John', 'john@example.com')");
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('Jane', 'jane@example.com')");

        $statement = new MappedStatement(
            id: 'find',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'stdClass',
            hydration: Hydration::OBJECT,
        );

        $boundSql1 = new BoundSql('SELECT * FROM users WHERE name = "John"');
        $boundSql2 = new BoundSql('SELECT * FROM users WHERE email = "jane@example.com"');

        $results1 = $this->executor->query($statement, null, $boundSql1);
        $results2 = $this->executor->query($statement, null, $boundSql2);

        $this->assertIsArray($results1);
        $this->assertIsArray($results2);
    }

    public function testUpdateWithGeneratedKeys(): void
    {
        $parameter = new class {
            public ?string $id = null;

            public function setId(string $id): void
            {
                $this->id = $id;
            }
        };

        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
            useGeneratedKeys: true,
            keyProperty: 'id',
        );

        $boundSql = new BoundSql("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");

        $this->executor->update($statement, $parameter, $boundSql);

        $this->assertNotNull($parameter->id);
        $this->assertEquals('1', $parameter->id);
    }

    public function testUpdateWithGeneratedKeysAndArrayParameter(): void
    {
        $parameter = ['name' => 'Bob', 'email' => 'bob@example.com'];

        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
            useGeneratedKeys: true,
            keyProperty: 'id',
        );

        $boundSql = new BoundSql("INSERT INTO users (name, email) VALUES ('Bob', 'bob@example.com')");

        $rowCount = $this->executor->update($statement, $parameter, $boundSql);

        // Arrays are passed by value in PHP, so generated key won't be set on the original
        // Just verify the insert worked
        $this->assertEquals(1, $rowCount);
        $count = $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertEquals(1, $count);
    }

    public function testQueryReturnsEmptyArrayForNoResults(): void
    {
        $statement = new MappedStatement(
            id: 'findAll',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'stdClass',
            hydration: Hydration::OBJECT,
        );

        $boundSql = new BoundSql('SELECT * FROM users');

        $results = $this->executor->query($statement, null, $boundSql);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testQueryReturnsMultipleResults(): void
    {
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('John', 'john@example.com')");
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('Jane', 'jane@example.com')");

        $statement = new MappedStatement(
            id: 'findAll',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'array',
            hydration: Hydration::ARRAY,
        );

        $boundSql = new BoundSql('SELECT * FROM users ORDER BY id');

        $results = $this->executor->query($statement, null, $boundSql);

        $this->assertCount(2, $results);
        $this->assertEquals('John', $results[0]['name']);
        $this->assertEquals('Jane', $results[1]['name']);
    }

    public function testUpdateReturnsRowCount(): void
    {
        $this->connection->exec("INSERT INTO users (id, name, email) VALUES (1, 'John', 'john@example.com')");
        $this->connection->exec("INSERT INTO users (id, name, email) VALUES (2, 'Jane', 'jane@example.com')");

        $statement = new MappedStatement(
            id: 'updateUsers',
            namespace: 'UserMapper',
            type: StatementType::UPDATE,
        );

        $boundSql = new BoundSql('UPDATE users SET active = 0 WHERE id > 0');

        $rowCount = $this->executor->update($statement, null, $boundSql);

        $this->assertEquals(2, $rowCount);
    }

    public function testMultipleUpdatesWithSameSQL(): void
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

        $count = $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertEquals(3, $count);
    }

    public function testCloseWithForceRollback(): void
    {
        $statement = new MappedStatement(
            id: 'findAll',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'stdClass',
            hydration: Hydration::OBJECT,
        );

        $boundSql = new BoundSql('SELECT * FROM users');

        $this->executor->query($statement, null, $boundSql);
        $this->executor->close(true);

        $this->assertTrue(true);
    }
}
