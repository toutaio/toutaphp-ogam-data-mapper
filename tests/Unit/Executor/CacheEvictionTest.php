<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Executor;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\Executor\SimpleExecutor;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\Hydration;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Transaction\PdoTransaction;

#[CoversClass(SimpleExecutor::class)]
final class CacheEvictionTest extends TestCase
{
    private PDO $pdo;

    private Configuration $configuration;

    private SimpleExecutor $executor;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec("INSERT INTO users (name) VALUES ('John')");
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Jane')");

        $this->configuration = new Configuration();
        // SQLite doesn't support ATTR_AUTOCOMMIT, so we pass autoCommit explicitly
        $transaction = new PdoTransaction($this->pdo, null, true);
        $this->executor = new SimpleExecutor($this->configuration, $transaction);
    }

    #[Test]
    public function localCacheIsClearedOnUpdate(): void
    {
        $selectStatement = $this->createSelectStatement('User.findAll');
        $boundSql = new BoundSql('SELECT * FROM users', []);

        // First query - should hit database
        $result1 = $this->executor->query($selectStatement, null, $boundSql);
        $this->assertCount(2, $result1);

        // Second query - should hit local cache
        $result2 = $this->executor->query($selectStatement, null, $boundSql);
        $this->assertCount(2, $result2);

        // Update operation - should clear local cache
        $updateStatement = $this->createUpdateStatement('User.update');
        $updateBoundSql = new BoundSql("UPDATE users SET name = 'Updated' WHERE id = 1", []);
        $this->executor->update($updateStatement, null, $updateBoundSql);

        // Insert a new row
        $this->pdo->exec("INSERT INTO users (name) VALUES ('New')");

        // Third query - should hit database again (cache was cleared)
        $result3 = $this->executor->query($selectStatement, null, $boundSql);
        $this->assertCount(3, $result3);
    }

    #[Test]
    public function localCacheIsClearedOnCommit(): void
    {
        $selectStatement = $this->createSelectStatement('User.findAll');
        $boundSql = new BoundSql('SELECT * FROM users', []);

        // First query - should cache results
        $result1 = $this->executor->query($selectStatement, null, $boundSql);
        $this->assertCount(2, $result1);

        // Commit - should clear local cache
        $this->pdo->beginTransaction();
        $this->executor->commit(true);

        // Insert a new row
        $this->pdo->exec("INSERT INTO users (name) VALUES ('New')");

        // Second query - should hit database again (cache was cleared)
        $result2 = $this->executor->query($selectStatement, null, $boundSql);
        $this->assertCount(3, $result2);
    }

    #[Test]
    public function localCacheIsClearedOnRollback(): void
    {
        $selectStatement = $this->createSelectStatement('User.findAll');
        $boundSql = new BoundSql('SELECT * FROM users', []);

        // First query - should cache results
        $result1 = $this->executor->query($selectStatement, null, $boundSql);
        $this->assertCount(2, $result1);

        // Rollback - should clear local cache
        $this->pdo->beginTransaction();
        $this->executor->rollback(true);

        // Insert a new row
        $this->pdo->exec("INSERT INTO users (name) VALUES ('New')");

        // Second query - should hit database again (cache was cleared)
        $result2 = $this->executor->query($selectStatement, null, $boundSql);
        $this->assertCount(3, $result2);
    }

    #[Test]
    public function clearLocalCacheManually(): void
    {
        $selectStatement = $this->createSelectStatement('User.findAll');
        $boundSql = new BoundSql('SELECT * FROM users', []);

        // First query
        $result1 = $this->executor->query($selectStatement, null, $boundSql);
        $this->assertCount(2, $result1);

        // Clear local cache
        $this->executor->clearLocalCache();

        // Insert a new row
        $this->pdo->exec("INSERT INTO users (name) VALUES ('New')");

        // Second query - should hit database
        $result2 = $this->executor->query($selectStatement, null, $boundSql);
        $this->assertCount(3, $result2);
    }

    private function createSelectStatement(string $id): MappedStatement
    {
        return new MappedStatement(
            id: $id,
            namespace: 'User',
            type: StatementType::SELECT,
            sql: 'SELECT * FROM users',
            resultMapId: null,
            resultType: null,
            parameterType: null,
            useGeneratedKeys: false,
            keyProperty: null,
            keyColumn: null,
            timeout: 0,
            fetchSize: 0,
            hydration: Hydration::ARRAY,
        );
    }

    private function createUpdateStatement(string $id): MappedStatement
    {
        return new MappedStatement(
            id: $id,
            namespace: 'User',
            type: StatementType::UPDATE,
            sql: "UPDATE users SET name = 'Updated'",
            resultMapId: null,
            resultType: null,
            parameterType: null,
            useGeneratedKeys: false,
            keyProperty: null,
            keyColumn: null,
            timeout: 0,
            fetchSize: 0,
            hydration: null,
        );
    }
}
