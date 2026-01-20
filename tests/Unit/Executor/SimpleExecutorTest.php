<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Executor;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Touta\Ogam\Configuration;
use Touta\Ogam\Executor\SimpleExecutor;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\Hydration;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\ParameterMapping;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Transaction\TransactionInterface;
use Touta\Ogam\Type\TypeHandlerRegistry;

final class SimpleExecutorTest extends TestCase
{
    private Configuration $configuration;
    private TransactionInterface $transaction;
    private PDO $connection;
    private SimpleExecutor $executor;

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

        $this->executor = new SimpleExecutor($this->configuration, $this->transaction);
    }

    public function testQueryReturnsEmptyArrayForNoResults(): void
    {
        $statement = new MappedStatement(
            id: 'findAll',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'array',
            hydration: Hydration::ARRAY
        );

        $boundSql = new BoundSql('SELECT * FROM users');

        $results = $this->executor->query($statement, null, $boundSql);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testQueryReturnsResultsAsObjects(): void
    {
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('John Doe', 'john@example.com')");
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('Jane Smith', 'jane@example.com')");

        $statement = new MappedStatement(
            id: 'findAll',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'array',
            hydration: Hydration::ARRAY
        );

        $boundSql = new BoundSql('SELECT id, name, email FROM users ORDER BY id');

        $results = $this->executor->query($statement, null, $boundSql);

        $this->assertCount(2, $results);
        $this->assertIsArray($results[0]);
        $this->assertEquals('John Doe', $results[0]['name']);
        $this->assertEquals('john@example.com', $results[0]['email']);
    }

    public function testQueryWithParameters(): void
    {
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('John Doe', 'john@example.com')");
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('Jane Smith', 'jane@example.com')");

        $statement = new MappedStatement(
            id: 'findByName',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'array',
            hydration: Hydration::ARRAY
        );

        $mappings = [new ParameterMapping('name')];
        $boundSql = new BoundSql('SELECT id, name, email FROM users WHERE name = ?', $mappings);

        $results = $this->executor->query($statement, ['name' => 'John Doe'], $boundSql);

        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
    }

    public function testQueryWithObjectParameter(): void
    {
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('John Doe', 'john@example.com')");

        $parameter = new class {
            public string $name = 'John Doe';
        };

        $statement = new MappedStatement(
            id: 'findByName',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'array',
            hydration: Hydration::ARRAY
        );

        $mappings = [new ParameterMapping('name')];
        $boundSql = new BoundSql('SELECT id, name, email FROM users WHERE name = ?', $mappings);

        $results = $this->executor->query($statement, $parameter, $boundSql);

        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
    }

    public function testQueryRecordsLastQuery(): void
    {
        $statement = new MappedStatement(
            id: 'findAll',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'array',
            hydration: Hydration::ARRAY
        );

        $boundSql = new BoundSql('SELECT * FROM users');

        $this->executor->query($statement, null, $boundSql);

        $lastQuery = $this->executor->getLastQuery();

        $this->assertNotNull($lastQuery);
        $this->assertEquals('SELECT * FROM users', $lastQuery['sql']);
        $this->assertIsArray($lastQuery['params']);
        $this->assertIsFloat($lastQuery['time']);
        $this->assertGreaterThan(0, $lastQuery['time']);
    }

    public function testQueryWithScalarHydration(): void
    {
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('John Doe', 'john@example.com')");
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('Jane Smith', 'jane@example.com')");

        $statement = new MappedStatement(
            id: 'countUsers',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            hydration: Hydration::SCALAR
        );

        $boundSql = new BoundSql('SELECT COUNT(*) FROM users');

        $results = $this->executor->query($statement, null, $boundSql);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals(2, $results[0]);
    }

    public function testUpdateReturnsAffectedRowCount(): void
    {
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('John Doe', 'john@example.com')");

        $statement = new MappedStatement(
            id: 'updateUser',
            namespace: 'UserMapper',
            type: StatementType::UPDATE
        );

        $mappings = [
            new ParameterMapping('email'),
            new ParameterMapping('name'),
        ];
        $boundSql = new BoundSql('UPDATE users SET email = ? WHERE name = ?', $mappings);

        $affectedRows = $this->executor->update(
            $statement,
            ['email' => 'newemail@example.com', 'name' => 'John Doe'],
            $boundSql
        );

        $this->assertEquals(1, $affectedRows);
    }

    public function testUpdateWithNoMatchingRows(): void
    {
        $statement = new MappedStatement(
            id: 'updateUser',
            namespace: 'UserMapper',
            type: StatementType::UPDATE
        );

        $mappings = [
            new ParameterMapping('email'),
            new ParameterMapping('name'),
        ];
        $boundSql = new BoundSql('UPDATE users SET email = ? WHERE name = ?', $mappings);

        $affectedRows = $this->executor->update(
            $statement,
            ['email' => 'newemail@example.com', 'name' => 'NonExistent'],
            $boundSql
        );

        $this->assertEquals(0, $affectedRows);
    }

    public function testInsertWithGeneratedKeys(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
            useGeneratedKeys: true,
            keyProperty: 'id'
        );

        $parameter = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $mappings = [
            new ParameterMapping('name'),
            new ParameterMapping('email'),
        ];
        $boundSql = new BoundSql('INSERT INTO users (name, email) VALUES (?, ?)', $mappings);

        $affectedRows = $this->executor->update($statement, $parameter, $boundSql);

        $this->assertEquals(1, $affectedRows);
        // Arrays are passed by value in PHP, so generated key won't be set on the original
        // Verify the insert worked by checking the database
        $count = $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertEquals(1, $count);
    }

    public function testInsertWithGeneratedKeysOnObject(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
            useGeneratedKeys: true,
            keyProperty: 'id'
        );

        $parameter = new class {
            public ?int $id = null;
            public string $name = 'John Doe';
            public string $email = 'john@example.com';
        };

        $mappings = [
            new ParameterMapping('name'),
            new ParameterMapping('email'),
        ];
        $boundSql = new BoundSql('INSERT INTO users (name, email) VALUES (?, ?)', $mappings);

        $this->executor->update($statement, $parameter, $boundSql);

        $this->assertNotNull($parameter->id);
        $this->assertEquals('1', $parameter->id);
    }

    public function testInsertWithGeneratedKeysUsingSetterMethod(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
            useGeneratedKeys: true,
            keyProperty: 'id'
        );

        $parameter = new class {
            private ?int $id = null;
            public string $name = 'John Doe';
            public string $email = 'john@example.com';

            public function setId(string $id): void
            {
                $this->id = (int) $id;
            }

            public function getId(): ?int
            {
                return $this->id;
            }
        };

        $mappings = [
            new ParameterMapping('name'),
            new ParameterMapping('email'),
        ];
        $boundSql = new BoundSql('INSERT INTO users (name, email) VALUES (?, ?)', $mappings);

        $this->executor->update($statement, $parameter, $boundSql);

        $this->assertEquals(1, $parameter->getId());
    }

    public function testInsertWithGeneratedKeysOnReadonlyProperty(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
            useGeneratedKeys: true,
            keyProperty: 'id'
        );

        $parameter = new class {
            public function __construct(
                public readonly ?int $id = null,
                public string $name = 'John Doe',
                public string $email = 'john@example.com'
            ) {}
        };

        $mappings = [
            new ParameterMapping('name'),
            new ParameterMapping('email'),
        ];
        $boundSql = new BoundSql('INSERT INTO users (name, email) VALUES (?, ?)', $mappings);

        $this->executor->update($statement, $parameter, $boundSql);

        // Readonly property cannot be set, should not throw error
        $this->assertNull($parameter->id);
    }

    public function testInsertWithoutGeneratedKeys(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
            useGeneratedKeys: false
        );

        $parameter = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $mappings = [
            new ParameterMapping('name'),
            new ParameterMapping('email'),
        ];
        $boundSql = new BoundSql('INSERT INTO users (name, email) VALUES (?, ?)', $mappings);

        $affectedRows = $this->executor->update($statement, $parameter, $boundSql);

        $this->assertEquals(1, $affectedRows);
        $this->assertArrayNotHasKey('id', $parameter);
    }

    public function testInsertWithGeneratedKeysButNoKeyProperty(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
            useGeneratedKeys: true,
            keyProperty: null
        );

        $parameter = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $mappings = [
            new ParameterMapping('name'),
            new ParameterMapping('email'),
        ];
        $boundSql = new BoundSql('INSERT INTO users (name, email) VALUES (?, ?)', $mappings);

        $this->executor->update($statement, $parameter, $boundSql);

        $this->assertArrayNotHasKey('id', $parameter);
    }

    public function testInsertWithGeneratedKeysOnObjectWithoutProperty(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT,
            useGeneratedKeys: true,
            keyProperty: 'nonExistentProperty'
        );

        $parameter = new class {
            public string $name = 'John Doe';
        };

        $mappings = [new ParameterMapping('name')];
        $boundSql = new BoundSql('INSERT INTO users (name) VALUES (?)', $mappings);

        // Should not throw, just skip setting the property
        $this->executor->update($statement, $parameter, $boundSql);
        $this->assertTrue(true);
    }

    public function testDeleteStatement(): void
    {
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('John Doe', 'john@example.com')");
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('Jane Smith', 'jane@example.com')");

        $statement = new MappedStatement(
            id: 'deleteUser',
            namespace: 'UserMapper',
            type: StatementType::DELETE
        );

        $mappings = [new ParameterMapping('name')];
        $boundSql = new BoundSql('DELETE FROM users WHERE name = ?', $mappings);

        $affectedRows = $this->executor->update($statement, ['name' => 'John Doe'], $boundSql);

        $this->assertEquals(1, $affectedRows);

        // Verify deletion
        $stmt = $this->connection->query('SELECT COUNT(*) FROM users');
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function testFlushStatementsReturnsEmptyArray(): void
    {
        $results = $this->executor->flushStatements();

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testUpdateRecordsLastQuery(): void
    {
        $statement = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT
        );

        $mappings = [new ParameterMapping('name')];
        $boundSql = new BoundSql('INSERT INTO users (name) VALUES (?)', $mappings);

        $this->executor->update($statement, ['name' => 'John Doe'], $boundSql);

        $lastQuery = $this->executor->getLastQuery();

        $this->assertNotNull($lastQuery);
        $this->assertEquals('INSERT INTO users (name) VALUES (?)', $lastQuery['sql']);
        $this->assertArrayHasKey('name', $lastQuery['params']);
        $this->assertEquals('John Doe', $lastQuery['params']['name']);
    }

    public function testMultipleQueries(): void
    {
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('John Doe', 'john@example.com')");

        $statement = new MappedStatement(
            id: 'findByName',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'array',
            hydration: Hydration::ARRAY
        );

        $mappings = [new ParameterMapping('name')];
        $boundSql = new BoundSql('SELECT * FROM users WHERE name = ?', $mappings);

        $results1 = $this->executor->query($statement, ['name' => 'John Doe'], $boundSql);
        $results2 = $this->executor->query($statement, ['name' => 'Jane Smith'], $boundSql);

        $this->assertCount(1, $results1);
        $this->assertCount(0, $results2);
    }

    public function testQueryWithMultipleParameters(): void
    {
        $this->connection->exec("INSERT INTO users (name, email, active) VALUES ('John Doe', 'john@example.com', 1)");
        $this->connection->exec("INSERT INTO users (name, email, active) VALUES ('Jane Smith', 'jane@example.com', 0)");

        $statement = new MappedStatement(
            id: 'findActiveByEmail',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'array',
            hydration: Hydration::ARRAY
        );

        $mappings = [
            new ParameterMapping('email'),
            new ParameterMapping('active'),
        ];
        $boundSql = new BoundSql('SELECT * FROM users WHERE email LIKE ? AND active = ?', $mappings);

        $results = $this->executor->query(
            $statement,
            ['email' => '%example.com', 'active' => 1],
            $boundSql
        );

        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
    }

    public function testUpdateWithMultipleRows(): void
    {
        $this->connection->exec("INSERT INTO users (name, email, active) VALUES ('John Doe', 'john@example.com', 1)");
        $this->connection->exec("INSERT INTO users (name, email, active) VALUES ('Jane Smith', 'jane@example.com', 1)");

        $statement = new MappedStatement(
            id: 'deactivateAll',
            namespace: 'UserMapper',
            type: StatementType::UPDATE
        );

        $boundSql = new BoundSql('UPDATE users SET active = 0 WHERE active = 1');

        $affectedRows = $this->executor->update($statement, null, $boundSql);

        $this->assertEquals(2, $affectedRows);
    }

    public function testQueryCachingWorks(): void
    {
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('John Doe', 'john@example.com')");

        $statement = new MappedStatement(
            id: 'findAll',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'array',
            hydration: Hydration::ARRAY
        );

        $boundSql = new BoundSql('SELECT * FROM users');

        // First query
        $results1 = $this->executor->query($statement, null, $boundSql);

        // Insert another record
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('Jane Smith', 'jane@example.com')");

        // Second query should return cached results
        $results2 = $this->executor->query($statement, null, $boundSql);

        $this->assertCount(1, $results1);
        $this->assertCount(1, $results2); // Still 1 due to caching
        $this->assertEquals($results1, $results2);
    }

    public function testUpdateClearsCacheAllowingFreshQuery(): void
    {
        $this->connection->exec("INSERT INTO users (name, email) VALUES ('John Doe', 'john@example.com')");

        $selectStmt = new MappedStatement(
            id: 'findAll',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'array',
            hydration: Hydration::ARRAY
        );

        $insertStmt = new MappedStatement(
            id: 'insertUser',
            namespace: 'UserMapper',
            type: StatementType::INSERT
        );

        $selectSql = new BoundSql('SELECT * FROM users');
        $insertSql = new BoundSql(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            [new ParameterMapping('name'), new ParameterMapping('email')]
        );

        // First query
        $results1 = $this->executor->query($selectStmt, null, $selectSql);
        $this->assertCount(1, $results1);

        // Update clears cache
        $this->executor->update($insertStmt, ['name' => 'Jane Smith', 'email' => 'jane@example.com'], $insertSql);

        // Second query should get fresh data
        $results2 = $this->executor->query($selectStmt, null, $selectSql);
        $this->assertCount(2, $results2);
    }
}
