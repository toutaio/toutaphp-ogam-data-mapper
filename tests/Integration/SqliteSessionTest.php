<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\DataSource\Environment;
use Touta\Ogam\DataSource\PooledDataSource;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Session\DefaultSessionFactory;
use Touta\Ogam\Sql\DynamicSqlSource;
use Touta\Ogam\Sql\Node\TextSqlNode;
use Touta\Ogam\Transaction\ManagedTransactionFactory;

final class SqliteSessionTest extends TestCase
{
    private Configuration $configuration;

    private DefaultSessionFactory $sessionFactory;

    private PooledDataSource $dataSource;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();

        $this->dataSource = new PooledDataSource('sqlite::memory:');
        $transactionFactory = new ManagedTransactionFactory();
        $environment = new Environment('default', $this->dataSource, $transactionFactory);

        $this->configuration->addEnvironment($environment);

        // Create test table using pooled connection
        $pdo = $this->dataSource->getConnection();
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                age INTEGER,
                active INTEGER DEFAULT 1,
                created_at TEXT
            )
        ');
        // Return connection to pool so session can reuse it
        $this->dataSource->releaseConnection($pdo);

        // Add mapped statements
        $this->addMappedStatements();

        $this->sessionFactory = new DefaultSessionFactory($this->configuration);
    }

    protected function tearDown(): void
    {
        $this->dataSource->clear();
    }

    public function testInsertAndSelect(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            $affected = $session->insert('UserMapper.insert', [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'age' => 30,
            ]);

            $this->assertSame(1, $affected);

            $users = $session->selectList('UserMapper.findAll');

            $this->assertCount(1, $users);
            $this->assertSame('John Doe', $users[0]['name']);
            $this->assertSame('john@example.com', $users[0]['email']);

            $session->commit();
        } finally {
            $session->close();
        }
    }

    public function testSelectOne(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            $session->insert('UserMapper.insert', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'age' => 25,
            ]);

            $user = $session->selectOne('UserMapper.findByEmail', ['email' => 'jane@example.com']);

            $this->assertNotNull($user);
            $this->assertSame('Jane Doe', $user['name']);

            $notFound = $session->selectOne('UserMapper.findByEmail', ['email' => 'nonexistent@example.com']);
            $this->assertNull($notFound);

            $session->commit();
        } finally {
            $session->close();
        }
    }

    public function testUpdate(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            $session->insert('UserMapper.insert', [
                'name' => 'Bob Smith',
                'email' => 'bob@example.com',
                'age' => 40,
            ]);

            $affected = $session->update('UserMapper.updateAge', [
                'email' => 'bob@example.com',
                'age' => 41,
            ]);

            $this->assertSame(1, $affected);

            $user = $session->selectOne('UserMapper.findByEmail', ['email' => 'bob@example.com']);
            $this->assertSame(41, (int) $user['age']);

            $session->commit();
        } finally {
            $session->close();
        }
    }

    public function testDelete(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            $session->insert('UserMapper.insert', [
                'name' => 'Delete Me',
                'email' => 'delete@example.com',
                'age' => 99,
            ]);

            $affected = $session->delete('UserMapper.deleteByEmail', ['email' => 'delete@example.com']);

            $this->assertSame(1, $affected);

            $user = $session->selectOne('UserMapper.findByEmail', ['email' => 'delete@example.com']);
            $this->assertNull($user);

            $session->commit();
        } finally {
            $session->close();
        }
    }

    public function testSelectMap(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            $session->insert('UserMapper.insert', ['name' => 'User 1', 'email' => 'user1@example.com', 'age' => 20]);
            $session->insert('UserMapper.insert', ['name' => 'User 2', 'email' => 'user2@example.com', 'age' => 30]);
            $session->insert('UserMapper.insert', ['name' => 'User 3', 'email' => 'user3@example.com', 'age' => 40]);

            $usersMap = $session->selectMap('UserMapper.findAll', 'email');

            $this->assertCount(3, $usersMap);
            $this->assertArrayHasKey('user1@example.com', $usersMap);
            $this->assertArrayHasKey('user2@example.com', $usersMap);
            $this->assertSame('User 1', $usersMap['user1@example.com']['name']);

            $session->commit();
        } finally {
            $session->close();
        }
    }

    public function testTransactionRollback(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            // Insert a record
            $session->insert('UserMapper.insert', [
                'name' => 'Rollback Test',
                'email' => 'rollback@example.com',
                'age' => 50,
            ]);

            // Verify record exists before rollback (within same transaction)
            $userBeforeRollback = $session->selectOne('UserMapper.findByEmail', ['email' => 'rollback@example.com']);
            $this->assertNotNull($userBeforeRollback);
            $this->assertSame('Rollback Test', $userBeforeRollback['name']);

            // Rollback the transaction
            $session->rollback();

            // After rollback, query again in the same session (new implicit transaction)
            $userAfterRollback = $session->selectOne('UserMapper.findByEmail', ['email' => 'rollback@example.com']);
            $this->assertNull($userAfterRollback);
        } finally {
            $session->close();
        }
    }

    public function testGetLastQuery(): void
    {
        $session = $this->sessionFactory->openSession();

        try {
            $session->selectList('UserMapper.findAll');

            $lastQuery = $session->getLastQuery();

            $this->assertNotNull($lastQuery);
            $this->assertArrayHasKey('sql', $lastQuery);
            $this->assertArrayHasKey('params', $lastQuery);
            $this->assertArrayHasKey('time', $lastQuery);
            $this->assertStringContainsString('SELECT', $lastQuery['sql']);
        } finally {
            $session->close();
        }
    }

    private function addMappedStatements(): void
    {
        // findAll
        $this->configuration->addMappedStatement(new MappedStatement(
            'findAll',
            'UserMapper',
            StatementType::SELECT,
            null,
            null,
            null,
            null,
            false,
            null,
            null,
            0,
            0,
            null,
            new DynamicSqlSource(
                $this->configuration,
                new TextSqlNode('SELECT * FROM users'),
            ),
        ));

        // findByEmail
        $this->configuration->addMappedStatement(new MappedStatement(
            'findByEmail',
            'UserMapper',
            StatementType::SELECT,
            null,
            null,
            null,
            null,
            false,
            null,
            null,
            0,
            0,
            null,
            new DynamicSqlSource(
                $this->configuration,
                new TextSqlNode('SELECT * FROM users WHERE email = #{email}'),
            ),
        ));

        // insert
        $this->configuration->addMappedStatement(new MappedStatement(
            'insert',
            'UserMapper',
            StatementType::INSERT,
            null,
            null,
            null,
            null,
            true,
            'id',
            null,
            0,
            0,
            null,
            new DynamicSqlSource(
                $this->configuration,
                new TextSqlNode('INSERT INTO users (name, email, age) VALUES (#{name}, #{email}, #{age})'),
            ),
        ));

        // updateAge
        $this->configuration->addMappedStatement(new MappedStatement(
            'updateAge',
            'UserMapper',
            StatementType::UPDATE,
            null,
            null,
            null,
            null,
            false,
            null,
            null,
            0,
            0,
            null,
            new DynamicSqlSource(
                $this->configuration,
                new TextSqlNode('UPDATE users SET age = #{age} WHERE email = #{email}'),
            ),
        ));

        // deleteByEmail
        $this->configuration->addMappedStatement(new MappedStatement(
            'deleteByEmail',
            'UserMapper',
            StatementType::DELETE,
            null,
            null,
            null,
            null,
            false,
            null,
            null,
            0,
            0,
            null,
            new DynamicSqlSource(
                $this->configuration,
                new TextSqlNode('DELETE FROM users WHERE email = #{email}'),
            ),
        ));
    }
}
