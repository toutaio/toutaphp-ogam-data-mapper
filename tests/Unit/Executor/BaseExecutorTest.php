<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Executor;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Touta\Ogam\Configuration;
use Touta\Ogam\Contract\ExecutorInterface;
use Touta\Ogam\Executor\BaseExecutor;
use Touta\Ogam\Hydration\HydratorFactory;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\Hydration;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\ParameterMapping;
use Touta\Ogam\Mapping\ResultMap;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Transaction\TransactionInterface;
use Touta\Ogam\Type\TypeHandlerRegistry;
use Touta\Ogam\Type\Handler\StringHandler;

final class BaseExecutorTest extends TestCase
{
    private Configuration $configuration;
    private TransactionInterface $transaction;
    private PDO $connection;
    private TestableExecutor $executor;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $this->configuration = new Configuration();
        $this->transaction = $this->createMock(TransactionInterface::class);

        

        $this->transaction->method('getConnection')
            ->willReturn($this->connection);

        $this->executor = new TestableExecutor($this->configuration, $this->transaction);
    }

    public function testImplementsExecutorInterface(): void
    {
        $this->assertInstanceOf(ExecutorInterface::class, $this->executor);
    }

    public function testInitialStateIsNotClosed(): void
    {
        $this->assertFalse($this->executor->isClosed());
    }

    public function testInitialLocalCacheIsEmpty(): void
    {
        $this->assertEquals([], $this->executor->getLocalCacheForTest());
    }

    public function testInitialLastQueryIsNull(): void
    {
        $this->assertNull($this->executor->getLastQuery());
    }

    public function testQueryCachesResults(): void
    {
        $statement = $this->createMappedStatement();
        $boundSql = new BoundSql('SELECT * FROM users');

        $this->executor->setQueryResults([['id' => 1, 'name' => 'John']]);

        $result1 = $this->executor->query($statement, null, $boundSql);
        $result2 = $this->executor->query($statement, null, $boundSql);

        $this->assertEquals($result1, $result2);
        $this->assertEquals(1, $this->executor->getQueryCallCount());
    }

    public function testQueryWithDifferentParametersDoesNotUseCache(): void
    {
        $statement = $this->createMappedStatement();
        $boundSql = new BoundSql('SELECT * FROM users WHERE id = ?');

        $this->executor->setQueryResults([['id' => 1, 'name' => 'John']]);

        $this->executor->query($statement, ['id' => 1], $boundSql);
        $this->executor->query($statement, ['id' => 2], $boundSql);

        $this->assertEquals(2, $this->executor->getQueryCallCount());
    }

    public function testQueryThrowsWhenClosed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Executor is closed');

        $this->executor->close(false);

        $statement = $this->createMappedStatement();
        $boundSql = new BoundSql('SELECT * FROM users');

        $this->executor->query($statement, null, $boundSql);
    }

    public function testUpdateClearsLocalCache(): void
    {
        $statement = $this->createMappedStatement(StatementType::INSERT);
        $queryStmt = $this->createMappedStatement();
        $boundSql = new BoundSql('SELECT * FROM users');
        $updateBoundSql = new BoundSql('INSERT INTO users (name) VALUES (?)');

        $this->executor->setQueryResults([['id' => 1, 'name' => 'John']]);

        // Populate cache
        $this->executor->query($queryStmt, null, $boundSql);
        $this->assertNotEmpty($this->executor->getLocalCacheForTest());

        // Update should clear cache
        $this->executor->update($statement, ['name' => 'Jane'], $updateBoundSql);

        $this->assertEmpty($this->executor->getLocalCacheForTest());
    }

    public function testUpdateThrowsWhenClosed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Executor is closed');

        $this->executor->close(false);

        $statement = $this->createMappedStatement(StatementType::INSERT);
        $boundSql = new BoundSql('INSERT INTO users (name) VALUES (?)');

        $this->executor->update($statement, null, $boundSql);
    }

    public function testFlushStatementsThrowsWhenClosed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Executor is closed');

        $this->executor->close(false);
        $this->executor->flushStatements();
    }

    public function testCommitClearsLocalCacheAndFlushesStatements(): void
    {
        $statement = $this->createMappedStatement();
        $boundSql = new BoundSql('SELECT * FROM users');

        $this->executor->setQueryResults([['id' => 1, 'name' => 'John']]);
        $this->executor->query($statement, null, $boundSql);

        $this->assertNotEmpty($this->executor->getLocalCacheForTest());

        $this->transaction->expects($this->once())
            ->method('commit');

        $this->executor->commit(true);

        $this->assertEmpty($this->executor->getLocalCacheForTest());
        $this->assertTrue($this->executor->wasFlushStatementsCalled());
    }

    public function testCommitWithRequiredFalseDoesNotCommitTransaction(): void
    {
        $this->transaction->expects($this->never())
            ->method('commit');

        $this->executor->commit(false);
    }

    public function testCommitThrowsWhenClosed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Executor is closed');

        $this->executor->close(false);
        $this->executor->commit(true);
    }

    public function testRollbackClearsLocalCache(): void
    {
        $statement = $this->createMappedStatement();
        $boundSql = new BoundSql('SELECT * FROM users');

        $this->executor->setQueryResults([['id' => 1, 'name' => 'John']]);
        $this->executor->query($statement, null, $boundSql);

        $this->assertNotEmpty($this->executor->getLocalCacheForTest());

        $this->transaction->expects($this->once())
            ->method('rollback');

        $this->executor->rollback(true);

        $this->assertEmpty($this->executor->getLocalCacheForTest());
    }

    public function testRollbackWithRequiredFalseDoesNotRollbackTransaction(): void
    {
        $this->transaction->expects($this->never())
            ->method('rollback');

        $this->executor->rollback(false);
    }

    public function testRollbackWhenClosedIsNoop(): void
    {
        $this->executor->close(false);

        $this->transaction->expects($this->never())
            ->method('rollback');

        $this->executor->rollback(true);
    }

    public function testCloseWithForceRollbackCallsRollback(): void
    {
        $this->transaction->expects($this->once())
            ->method('rollback');

        $this->transaction->expects($this->once())
            ->method('close');

        $this->executor->close(true);

        $this->assertTrue($this->executor->isClosed());
    }

    public function testCloseWithoutForceRollbackFlushesStatements(): void
    {
        $this->transaction->expects($this->never())
            ->method('rollback');

        $this->transaction->expects($this->once())
            ->method('close');

        $this->executor->close(false);

        $this->assertTrue($this->executor->isClosed());
        $this->assertTrue($this->executor->wasFlushStatementsCalled());
    }

    public function testCloseIsIdempotent(): void
    {
        $this->transaction->expects($this->once())
            ->method('close');

        $this->executor->close(false);
        $this->executor->close(false);

        $this->assertTrue($this->executor->isClosed());
    }

    public function testCloseClearsLocalCache(): void
    {
        $statement = $this->createMappedStatement();
        $boundSql = new BoundSql('SELECT * FROM users');

        $this->executor->setQueryResults([['id' => 1, 'name' => 'John']]);
        $this->executor->query($statement, null, $boundSql);

        $this->assertNotEmpty($this->executor->getLocalCacheForTest());

        $this->executor->close(false);

        $this->assertEmpty($this->executor->getLocalCacheForTest());
    }

    public function testClearLocalCache(): void
    {
        $statement = $this->createMappedStatement();
        $boundSql = new BoundSql('SELECT * FROM users');

        $this->executor->setQueryResults([['id' => 1, 'name' => 'John']]);
        $this->executor->query($statement, null, $boundSql);

        $this->assertNotEmpty($this->executor->getLocalCacheForTest());

        $this->executor->clearLocalCache();

        $this->assertEmpty($this->executor->getLocalCacheForTest());
    }

    public function testExtractParameterValuesFromArray(): void
    {
        $params = ['id' => 1, 'name' => 'John'];

        $result = $this->executor->extractParameterValuesPublic($params);

        $this->assertEquals($params, $result);
    }

    public function testExtractParameterValuesFromNull(): void
    {
        $result = $this->executor->extractParameterValuesPublic(null);

        $this->assertEquals([], $result);
    }

    public function testExtractParameterValuesFromObjectWithProperties(): void
    {
        $obj = new class {
            public int $id = 1;
            private string $name = 'John';
        };

        $result = $this->executor->extractParameterValuesPublic($obj);

        $this->assertEquals(['id' => 1, 'name' => 'John'], $result);
    }

    public function testExtractParameterValuesFromObjectWithGetters(): void
    {
        $obj = new class {
            private int $id = 1;

            public function getId(): int
            {
                return $this->id;
            }

            public function getName(): string
            {
                return 'John';
            }
        };

        $result = $this->executor->extractParameterValuesPublic($obj);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('John', $result['name']);
    }

    public function testExtractParameterValuesFromObjectWithIsGetters(): void
    {
        $obj = new class {
            private bool $active = true;

            public function isActive(): bool
            {
                return $this->active;
            }
        };

        $result = $this->executor->extractParameterValuesPublic($obj);

        $this->assertArrayHasKey('active', $result);
        $this->assertTrue($result['active']);
    }

    public function testGetNestedValueFromArray(): void
    {
        $values = [
            'user' => [
                'name' => 'John',
                'address' => [
                    'city' => 'New York',
                ],
            ],
        ];

        $result = $this->executor->getNestedValuePublic($values, 'user.name');
        $this->assertEquals('John', $result);

        $result = $this->executor->getNestedValuePublic($values, 'user.address.city');
        $this->assertEquals('New York', $result);
    }

    public function testGetNestedValueReturnsNullForMissingPath(): void
    {
        $values = ['user' => ['name' => 'John']];

        $result = $this->executor->getNestedValuePublic($values, 'user.email');

        $this->assertNull($result);
    }

    public function testGetNestedValueFromObject(): void
    {
        $address = new class {
            public string $city = 'New York';
        };

        $user = new class($address) {
            public function __construct(public object $address) {}
        };

        $values = ['user' => $user];

        $result = $this->executor->getNestedValuePublic($values, 'user.address.city');

        $this->assertEquals('New York', $result);
    }

    public function testGetObjectPropertyViaGetter(): void
    {
        $obj = new class {
            private string $name = 'John';

            public function getName(): string
            {
                return $this->name;
            }
        };

        $result = $this->executor->getObjectPropertyPublic($obj, 'name');

        $this->assertEquals('John', $result);
    }

    public function testGetObjectPropertyViaIsGetter(): void
    {
        $obj = new class {
            private bool $active = true;

            public function isActive(): bool
            {
                return $this->active;
            }
        };

        $result = $this->executor->getObjectPropertyPublic($obj, 'active');

        $this->assertTrue($result);
    }

    public function testGetObjectPropertyViaDirectAccess(): void
    {
        $obj = new class {
            private string $name = 'John';
        };

        $result = $this->executor->getObjectPropertyPublic($obj, 'name');

        $this->assertEquals('John', $result);
    }

    public function testGetObjectPropertyReturnsNullForMissing(): void
    {
        $obj = new class {};

        $result = $this->executor->getObjectPropertyPublic($obj, 'nonExistent');

        $this->assertNull($result);
    }

    public function testCreateCacheKeyWithDifferentParameters(): void
    {
        $statement = $this->createMappedStatement();
        $boundSql = new BoundSql('SELECT * FROM users WHERE id = ?');

        $key1 = $this->executor->createCacheKeyPublic($statement, ['id' => 1], $boundSql);
        $key2 = $this->executor->createCacheKeyPublic($statement, ['id' => 2], $boundSql);

        $this->assertNotEquals($key1, $key2);
    }

    public function testCreateCacheKeyWithSameParameters(): void
    {
        $statement = $this->createMappedStatement();
        $boundSql = new BoundSql('SELECT * FROM users WHERE id = ?');

        $key1 = $this->executor->createCacheKeyPublic($statement, ['id' => 1], $boundSql);
        $key2 = $this->executor->createCacheKeyPublic($statement, ['id' => 1], $boundSql);

        $this->assertEquals($key1, $key2);
    }

    public function testBindParametersWithSingleParameter(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $mapping = new ParameterMapping('id');
        $boundSql = new BoundSql('SELECT * FROM users WHERE id = ?', [$mapping]);

        $stmt->expects($this->once())
            ->method('bindValue')
            ->with(1, 1);

        $this->executor->bindParametersPublic($stmt, $boundSql, ['id' => 1]);
    }

    public function testBindParametersWithMultipleParameters(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $mappings = [
            new ParameterMapping('name'),
            new ParameterMapping('email'),
        ];
        $boundSql = new BoundSql('INSERT INTO users (name, email) VALUES (?, ?)', $mappings);

        $stmt->expects($this->exactly(2))
            ->method('bindValue');

        $this->executor->bindParametersPublic($stmt, $boundSql, ['name' => 'John', 'email' => 'john@example.com']);
    }

    public function testBindParametersWithAdditionalParameters(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $mapping = new ParameterMapping('item');
        $boundSql = new BoundSql('SELECT * FROM table WHERE id IN (?)', [$mapping]);
        $boundSql->setAdditionalParameter('item', 1);

        $stmt->expects($this->once())
            ->method('bindValue');

        $this->executor->bindParametersPublic($stmt, $boundSql, []);
    }

    public function testBindParametersWithNestedProperty(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $mapping = new ParameterMapping('user.name');
        $boundSql = new BoundSql('SELECT * FROM users WHERE name = ?', [$mapping]);

        $stmt->expects($this->once())
            ->method('bindValue')
            ->with(1, 'John');

        $this->executor->bindParametersPublic($stmt, $boundSql, ['user' => ['name' => 'John']]);
    }

    public function testRecordQueryTracksExecutionTime(): void
    {
        $boundSql = new BoundSql('SELECT * FROM users');
        $startTime = microtime(true) - 0.1;

        $this->executor->recordQueryPublic($boundSql, ['id' => 1], $startTime);

        $lastQuery = $this->executor->getLastQuery();

        $this->assertNotNull($lastQuery);
        $this->assertEquals('SELECT * FROM users', $lastQuery['sql']);
        $this->assertEquals(['id' => 1], $lastQuery['params']);
        $this->assertGreaterThan(0, $lastQuery['time']);
    }

    public function testHydrateResultsWithObjectHydration(): void
    {
        $statement = new MappedStatement(
            id: 'findAll',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultType: 'stdClass',
            hydration: Hydration::OBJECT
        );

        $rows = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $results = $this->executor->hydrateResultsPublic($statement, $rows);

        $this->assertCount(2, $results);
        $this->assertIsArray($results);
        // Object hydration tested in ObjectHydratorTest
    }

    public function testHydrateResultsWithResultMap(): void
    {
        $resultMap = new ResultMap(
            id: 'userMap',
            type: 'stdClass',
            resultMappings: []
        );

        $this->configuration->addResultMap($resultMap);

        $statement = new MappedStatement(
            id: 'findAll',
            namespace: 'UserMapper',
            type: StatementType::SELECT,
            resultMapId: 'userMap',
            resultType: 'stdClass'
        );

        $rows = [['id' => 1, 'name' => 'John']];

        $result = $this->executor->hydrateResultsPublic($statement, $rows);
        
        $this->assertIsArray($result);
    }

    private function createMappedStatement(StatementType $type = StatementType::SELECT): MappedStatement
    {
        return new MappedStatement(
            id: 'test',
            namespace: 'TestMapper',
            type: $type,
            sql: 'SELECT * FROM test',
            resultType: 'stdClass'
        );
    }
}

/**
 * Concrete implementation of BaseExecutor for testing.
 */
class TestableExecutor extends BaseExecutor
{
    private int $queryCallCount = 0;
    private bool $flushStatementsCalled = false;
    private array $queryResults = [];

    public function setQueryResults(array $results): void
    {
        $this->queryResults = $results;
    }

    public function getQueryCallCount(): int
    {
        return $this->queryCallCount;
    }

    public function wasFlushStatementsCalled(): bool
    {
        return $this->flushStatementsCalled;
    }

    public function getLocalCacheForTest(): array
    {
        return $this->localCache;
    }

    protected function doQuery(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): array {
        $this->queryCallCount++;
        return $this->hydrateResults($statement, $this->queryResults);
    }

    protected function doUpdate(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): int {
        return 1;
    }

    protected function doFlushStatements(): array
    {
        $this->flushStatementsCalled = true;
        return [];
    }

    // Public wrappers for testing protected methods
    public function extractParameterValuesPublic(array|object|null $parameter): array
    {
        return $this->extractParameterValues($parameter);
    }

    public function getNestedValuePublic(array $values, string $path): mixed
    {
        return $this->getNestedValue($values, $path);
    }

    public function getObjectPropertyPublic(object $object, string $property): mixed
    {
        return $this->getObjectProperty($object, $property);
    }

    public function createCacheKeyPublic(
        MappedStatement $statement,
        array|object|null $parameter,
        BoundSql $boundSql,
    ): string {
        return $this->createCacheKey($statement, $parameter, $boundSql);
    }

    public function bindParametersPublic(
        PDOStatement $stmt,
        BoundSql $boundSql,
        array|object|null $parameter,
    ): void {
        $this->bindParameters($stmt, $boundSql, $parameter);
    }

    public function recordQueryPublic(BoundSql $boundSql, array|object|null $parameter, float $startTime): void
    {
        $this->recordQuery($boundSql, $parameter, $startTime);
    }

    public function hydrateResultsPublic(MappedStatement $statement, array $rows): array
    {
        return $this->hydrateResults($statement, $rows);
    }
}
