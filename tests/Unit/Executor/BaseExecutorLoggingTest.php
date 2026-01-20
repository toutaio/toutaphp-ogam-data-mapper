<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Executor;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\Executor\SimpleExecutor;
use Touta\Ogam\Logging\InMemoryQueryLogger;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\Hydration;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Transaction\TransactionInterface;

#[CoversClass(SimpleExecutor::class)]
final class BaseExecutorLoggingTest extends TestCase
{
    private Configuration $configuration;

    private SimpleExecutor $executor;

    private PDO $pdo;

    private InMemoryQueryLogger $logger;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Create test table
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Alice'), ('Bob')");

        $transaction = $this->createMock(TransactionInterface::class);
        $transaction->method('getConnection')->willReturn($this->pdo);

        $this->logger = new InMemoryQueryLogger();
        $this->configuration = new Configuration();
        $this->configuration->setDebugMode(true);
        $this->configuration->setQueryLogger($this->logger);

        $this->executor = new SimpleExecutor($this->configuration, $transaction);
    }

    #[Test]
    public function logsQueryWhenDebugModeEnabled(): void
    {
        $statement = new MappedStatement(
            'findAll',
            'UserMapper',
            StatementType::SELECT,
            'SELECT * FROM users',
            null,
            null,
            null,
            false,
            null,
            null,
            0,
            0,
            Hydration::ARRAY,
        );

        $boundSql = new BoundSql('SELECT * FROM users', []);

        $this->executor->query($statement, null, $boundSql);

        $entries = $this->logger->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('SELECT * FROM users', $entries[0]->sql);
        $this->assertSame('UserMapper.findAll', $entries[0]->statementId);
        $this->assertSame(2, $entries[0]->rowCount);
        $this->assertGreaterThan(0, $entries[0]->executionTimeMs);
    }

    #[Test]
    public function logsUpdateWithRowCount(): void
    {
        $statement = new MappedStatement(
            'deleteAll',
            'UserMapper',
            StatementType::DELETE,
            'DELETE FROM users',
            null,
            null,
            null,
            false,
            null,
            null,
            0,
            0,
            null,
        );

        $boundSql = new BoundSql('DELETE FROM users', []);

        $affectedRows = $this->executor->update($statement, null, $boundSql);

        $this->assertSame(2, $affectedRows);

        $entries = $this->logger->getEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('DELETE FROM users', $entries[0]->sql);
        $this->assertSame(2, $entries[0]->rowCount);
    }

    #[Test]
    public function doesNotLogWhenDebugModeDisabled(): void
    {
        $this->configuration->setDebugMode(false);

        $statement = new MappedStatement(
            'findAll',
            'UserMapper',
            StatementType::SELECT,
            'SELECT * FROM users',
            null,
            null,
            null,
            false,
            null,
            null,
            0,
            0,
            Hydration::ARRAY,
        );

        $boundSql = new BoundSql('SELECT * FROM users', []);

        $this->executor->query($statement, null, $boundSql);

        $entries = $this->logger->getEntries();
        $this->assertCount(0, $entries);
    }

    #[Test]
    public function doesNotLogWhenNoLoggerConfigured(): void
    {
        $this->configuration->setQueryLogger(null);

        $statement = new MappedStatement(
            'findAll',
            'UserMapper',
            StatementType::SELECT,
            'SELECT * FROM users',
            null,
            null,
            null,
            false,
            null,
            null,
            0,
            0,
            Hydration::ARRAY,
        );

        $boundSql = new BoundSql('SELECT * FROM users', []);

        $this->executor->query($statement, null, $boundSql);

        // Should not throw, just not log
        $this->assertTrue(true);
    }

    #[Test]
    public function lastQueryIncludesRowCount(): void
    {
        $statement = new MappedStatement(
            'findAll',
            'UserMapper',
            StatementType::SELECT,
            'SELECT * FROM users',
            null,
            null,
            null,
            false,
            null,
            null,
            0,
            0,
            Hydration::ARRAY,
        );

        $boundSql = new BoundSql('SELECT * FROM users', []);

        $this->executor->query($statement, null, $boundSql);

        $lastQuery = $this->executor->getLastQuery();
        $this->assertIsArray($lastQuery);
        $this->assertArrayHasKey('rowCount', $lastQuery);
        $this->assertSame(2, $lastQuery['rowCount']);
    }

    #[Test]
    public function lastQueryIncludesStatementId(): void
    {
        $statement = new MappedStatement(
            'findAll',
            'UserMapper',
            StatementType::SELECT,
            'SELECT * FROM users',
            null,
            null,
            null,
            false,
            null,
            null,
            0,
            0,
            Hydration::ARRAY,
        );

        $boundSql = new BoundSql('SELECT * FROM users', []);

        $this->executor->query($statement, null, $boundSql);

        $lastQuery = $this->executor->getLastQuery();
        $this->assertIsArray($lastQuery);
        $this->assertArrayHasKey('statementId', $lastQuery);
        $this->assertSame('UserMapper.findAll', $lastQuery['statementId']);
    }
}
