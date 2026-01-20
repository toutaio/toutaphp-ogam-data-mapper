<?php

declare(strict_types=1);

// Define test mapper interfaces in their own namespace

namespace App\Mapper
{
    interface UserMapper {}
    interface PostMapper {}
}

namespace Touta\Ogam\Tests\Unit\Session
{
    use Generator;
    use InvalidArgumentException;
    use PHPUnit\Framework\TestCase;
    use RuntimeException;
    use stdClass;
    use Touta\Ogam\Configuration;
    use Touta\Ogam\Contract\ExecutorInterface;
    use Touta\Ogam\Mapping\BoundSql;
    use Touta\Ogam\Mapping\Hydration;
    use Touta\Ogam\Mapping\MappedStatement;
    use Touta\Ogam\Mapping\StatementType;
    use Touta\Ogam\Session\DefaultSession;
    use Touta\Ogam\Session\MapperProxy;
    use Touta\Ogam\Sql\SqlSource;

    final class DefaultSessionTest extends TestCase
    {
        private Configuration $configuration;

        private ExecutorInterface $executor;

        private DefaultSession $session;

        protected function setUp(): void
        {
            $this->configuration = new Configuration();
            $this->executor = $this->createMock(ExecutorInterface::class);
            $this->session = new DefaultSession($this->configuration, $this->executor);
        }

        public function testSelectOneReturnsNullWhenNoResults(): void
        {
            $statement = new MappedStatement('findById', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users WHERE id = ?');

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn([]);

            $result = $this->session->selectOne('UserMapper.findById', ['id' => 1]);

            $this->assertNull($result);
        }

        public function testSelectOneReturnsSingleResult(): void
        {
            $statement = new MappedStatement('findById', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users WHERE id = ?');
            $expectedResult = ['id' => 1, 'name' => 'John'];

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn([$expectedResult]);

            $result = $this->session->selectOne('UserMapper.findById', ['id' => 1]);

            $this->assertSame($expectedResult, $result);
        }

        public function testSelectOneThrowsExceptionWhenMultipleResults(): void
        {
            $statement = new MappedStatement('findAll', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users');

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn([
                    ['id' => 1, 'name' => 'John'],
                    ['id' => 2, 'name' => 'Jane'],
                ]);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Expected one result (or null) to be returned by selectOne(), but found: 2');

            $this->session->selectOne('UserMapper.findAll');
        }

        public function testSelectListReturnsEmptyArray(): void
        {
            $statement = new MappedStatement('findAll', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users');

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn([]);

            $result = $this->session->selectList('UserMapper.findAll');

            $this->assertIsArray($result);
            $this->assertEmpty($result);
        }

        public function testSelectListReturnsMultipleResults(): void
        {
            $statement = new MappedStatement('findAll', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users');
            $expectedResults = [
                ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Jane'],
                ['id' => 3, 'name' => 'Bob'],
            ];

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn($expectedResults);

            $result = $this->session->selectList('UserMapper.findAll');

            $this->assertSame($expectedResults, $result);
        }

        public function testSelectListWithCustomHydration(): void
        {
            $statement = new MappedStatement(
                'findAll',
                'UserMapper',
                StatementType::SELECT,
                'SELECT * FROM users',
                hydration: Hydration::OBJECT,
            );

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn([]);

            $result = $this->session->selectList('UserMapper.findAll', null, Hydration::ARRAY);

            $this->assertIsArray($result);
        }

        public function testSelectListThrowsExceptionWhenStatementNotFound(): void
        {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Mapped statement "Unknown.statement" not found');

            $this->session->selectList('Unknown.statement');
        }

        public function testSelectListThrowsExceptionWhenSessionClosed(): void
        {
            $this->session->close();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Session is closed');

            $this->session->selectList('UserMapper.findAll');
        }

        public function testSelectMapReturnsEmptyArray(): void
        {
            $statement = new MappedStatement('findAll', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users');

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn([]);

            $result = $this->session->selectMap('UserMapper.findAll', 'id');

            $this->assertIsArray($result);
            $this->assertEmpty($result);
        }

        public function testSelectMapWithArrayResults(): void
        {
            $statement = new MappedStatement('findAll', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users');
            $results = [
                ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Jane'],
                ['id' => 3, 'name' => 'Bob'],
            ];

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn($results);

            $result = $this->session->selectMap('UserMapper.findAll', 'id');

            $this->assertCount(3, $result);
            $this->assertSame($results[0], $result[1]);
            $this->assertSame($results[1], $result[2]);
            $this->assertSame($results[2], $result[3]);
        }

        public function testSelectMapWithObjectResultsUsingGetter(): void
        {
            $statement = new MappedStatement('findAll', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users');

            $user1 = new class {
                public function getId(): int
                {
                    return 1;
                }

                public function getName(): string
                {
                    return 'John';
                }
            };

            $user2 = new class {
                public function getId(): int
                {
                    return 2;
                }

                public function getName(): string
                {
                    return 'Jane';
                }
            };

            $results = [$user1, $user2];

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn($results);

            $result = $this->session->selectMap('UserMapper.findAll', 'id');

            $this->assertCount(2, $result);
            $this->assertSame($user1, $result[1]);
            $this->assertSame($user2, $result[2]);
        }

        public function testSelectMapWithObjectResultsUsingPublicProperty(): void
        {
            $statement = new MappedStatement('findAll', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users');

            $user1 = new stdClass();
            $user1->id = 1;
            $user1->name = 'John';

            $user2 = new stdClass();
            $user2->id = 2;
            $user2->name = 'Jane';

            $results = [$user1, $user2];

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn($results);

            $result = $this->session->selectMap('UserMapper.findAll', 'id');

            $this->assertCount(2, $result);
            $this->assertSame($user1, $result[1]);
            $this->assertSame($user2, $result[2]);
        }

        public function testSelectMapThrowsExceptionWhenKeyNotFoundInArray(): void
        {
            $statement = new MappedStatement('findAll', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users');
            $results = [
                ['name' => 'John'],
            ];

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn($results);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Map key "id" not found in result array');

            $this->session->selectMap('UserMapper.findAll', 'id');
        }

        public function testSelectMapThrowsExceptionWhenKeyNotFoundInObject(): void
        {
            $statement = new MappedStatement('findAll', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users');

            $user = new stdClass();
            $user->name = 'John';

            $results = [$user];

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn($results);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot extract map key "id" from result');

            $this->session->selectMap('UserMapper.findAll', 'id');
        }

        public function testSelectMapSkipsNonScalarKeys(): void
        {
            $statement = new MappedStatement('findAll', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users');
            $results = [
                ['id' => 1, 'name' => 'John'],
                ['id' => ['invalid'], 'name' => 'Jane'],  // Non-scalar key
                ['id' => 3, 'name' => 'Bob'],
            ];

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn($results);

            $result = $this->session->selectMap('UserMapper.findAll', 'id');

            $this->assertCount(2, $result);
            $this->assertArrayHasKey(1, $result);
            $this->assertArrayHasKey(3, $result);
            $this->assertArrayNotHasKey(2, $result);
        }

        public function testSelectCursorReturnsGenerator(): void
        {
            $statement = new MappedStatement('findAll', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users');
            $results = [
                ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Jane'],
            ];

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn($results);

            $cursor = $this->session->selectCursor('UserMapper.findAll');

            $this->assertInstanceOf(Generator::class, $cursor);

            $items = iterator_to_array($cursor);
            $this->assertSame($results, $items);
        }

        public function testSelectCursorThrowsExceptionWhenSessionClosed(): void
        {
            $this->session->close();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Session is closed');

            $this->session->selectCursor('UserMapper.findAll');
        }

        public function testInsertExecutesSuccessfully(): void
        {
            $statement = new MappedStatement('insertUser', 'UserMapper', StatementType::INSERT, 'INSERT INTO users (name) VALUES (?)');

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->expects($this->once())
                ->method('update')
                ->willReturn(1);

            $result = $this->session->insert('UserMapper.insertUser', ['name' => 'John']);

            $this->assertSame(1, $result);
        }

        public function testInsertMarksDirtyFlag(): void
        {
            $statement = new MappedStatement('insertUser', 'UserMapper', StatementType::INSERT, 'INSERT INTO users (name) VALUES (?)');

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('update')
                ->willReturn(1);

            // Execute insert to mark dirty
            $this->session->insert('UserMapper.insertUser', ['name' => 'John']);

            // Close will trigger rollback if dirty
            $this->executor
                ->expects($this->once())
                ->method('close')
                ->with(true); // dirty && !autoCommit = true

            $this->session->close();
        }

        public function testUpdateExecutesSuccessfully(): void
        {
            $statement = new MappedStatement('updateUser', 'UserMapper', StatementType::UPDATE, 'UPDATE users SET name = ? WHERE id = ?');

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->expects($this->once())
                ->method('update')
                ->willReturn(1);

            $result = $this->session->update('UserMapper.updateUser', ['name' => 'Jane', 'id' => 1]);

            $this->assertSame(1, $result);
        }

        public function testDeleteExecutesSuccessfully(): void
        {
            $statement = new MappedStatement('deleteUser', 'UserMapper', StatementType::DELETE, 'DELETE FROM users WHERE id = ?');

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->expects($this->once())
                ->method('update')
                ->willReturn(1);

            $result = $this->session->delete('UserMapper.deleteUser', ['id' => 1]);

            $this->assertSame(1, $result);
        }

        public function testInsertThrowsExceptionWhenWrongStatementType(): void
        {
            $statement = new MappedStatement('findById', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users WHERE id = ?');

            $this->configuration->addMappedStatement($statement);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Statement "UserMapper.findById" is a select statement, but was used as insert');

            $this->session->insert('UserMapper.findById', ['id' => 1]);
        }

        public function testUpdateAllowsInsertStatementType(): void
        {
            // UPDATE/INSERT/DELETE types are interchangeable for update operations
            $statement = new MappedStatement('insertOrUpdate', 'UserMapper', StatementType::INSERT, 'INSERT INTO users (id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = ?');

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('update')
                ->willReturn(1);

            $result = $this->session->update('UserMapper.insertOrUpdate', ['id' => 1, 'name' => 'John']);

            $this->assertSame(1, $result);
        }

        public function testDeleteAllowsUpdateStatementType(): void
        {
            // UPDATE/INSERT/DELETE types are interchangeable for update operations
            $statement = new MappedStatement('softDelete', 'UserMapper', StatementType::UPDATE, 'UPDATE users SET deleted = 1 WHERE id = ?');

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('update')
                ->willReturn(1);

            $result = $this->session->delete('UserMapper.softDelete', ['id' => 1]);

            $this->assertSame(1, $result);
        }

        public function testCommitCallsExecutorCommit(): void
        {
            $this->executor
                ->expects($this->once())
                ->method('commit')
                ->with(true); // !autoCommit = true

            $this->session->commit();
        }

        public function testCommitClearsDirtyFlag(): void
        {
            $statement = new MappedStatement('insertUser', 'UserMapper', StatementType::INSERT, 'INSERT INTO users (name) VALUES (?)');

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('update')
                ->willReturn(1);

            // Make session dirty
            $this->session->insert('UserMapper.insertUser', ['name' => 'John']);

            // Commit should clear dirty flag
            $this->session->commit();

            // Close should not force rollback since dirty was cleared
            $this->executor
                ->expects($this->once())
                ->method('close')
                ->with(false);

            $this->session->close();
        }

        public function testCommitThrowsExceptionWhenSessionClosed(): void
        {
            $this->session->close();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Session is closed');

            $this->session->commit();
        }

        public function testRollbackCallsExecutorRollback(): void
        {
            $this->executor
                ->expects($this->once())
                ->method('rollback')
                ->with(true); // !autoCommit = true

            $this->session->rollback();
        }

        public function testRollbackClearsDirtyFlag(): void
        {
            $statement = new MappedStatement('insertUser', 'UserMapper', StatementType::INSERT, 'INSERT INTO users (name) VALUES (?)');

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('update')
                ->willReturn(1);

            // Make session dirty
            $this->session->insert('UserMapper.insertUser', ['name' => 'John']);

            // Rollback should clear dirty flag
            $this->session->rollback();

            // Close should not force rollback since dirty was cleared
            $this->executor
                ->expects($this->once())
                ->method('close')
                ->with(false);

            $this->session->close();
        }

        public function testRollbackThrowsExceptionWhenSessionClosed(): void
        {
            $this->session->close();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Session is closed');

            $this->session->rollback();
        }

        public function testGetMapperReturnsMapperProxy(): void
        {
            $mapper = $this->session->getMapper('App\\Mapper\\UserMapper');

            $this->assertInstanceOf(MapperProxy::class, $mapper);
        }

        public function testGetMapperCachesInstances(): void
        {
            $mapper1 = $this->session->getMapper('App\\Mapper\\UserMapper');
            $mapper2 = $this->session->getMapper('App\\Mapper\\UserMapper');

            $this->assertSame($mapper1, $mapper2);
        }

        public function testGetMapperReturnsDifferentInstancesForDifferentInterfaces(): void
        {
            $userMapper = $this->session->getMapper('App\\Mapper\\UserMapper');
            $postMapper = $this->session->getMapper('App\\Mapper\\PostMapper');

            $this->assertNotSame($userMapper, $postMapper);
        }

        public function testGetMapperThrowsExceptionWhenSessionClosed(): void
        {
            $this->session->close();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Session is closed');

            $this->session->getMapper('App\\Mapper\\UserMapper');
        }

        public function testCloseMarksSessionAsClosed(): void
        {
            $this->assertFalse($this->session->isClosed());

            $this->session->close();

            $this->assertTrue($this->session->isClosed());
        }

        public function testCloseCallsExecutorClose(): void
        {
            $this->executor
                ->expects($this->once())
                ->method('close')
                ->with(false); // !dirty = false

            $this->session->close();
        }

        public function testCloseWithAutoCommitDoesNotForceRollback(): void
        {
            $sessionWithAutoCommit = new DefaultSession($this->configuration, $this->executor, true);

            $statement = new MappedStatement('insertUser', 'UserMapper', StatementType::INSERT, 'INSERT INTO users (name) VALUES (?)');

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('update')
                ->willReturn(1);

            // Make session dirty
            $sessionWithAutoCommit->insert('UserMapper.insertUser', ['name' => 'John']);

            // Close with autoCommit should not force rollback
            $this->executor
                ->expects($this->once())
                ->method('close')
                ->with(false); // dirty && !autoCommit = false when autoCommit = true

            $sessionWithAutoCommit->close();
        }

        public function testCloseClearsMapperCache(): void
        {
            $mapper1 = $this->session->getMapper('App\\Mapper\\UserMapper');

            $this->session->close();
            $this->session = new DefaultSession($this->configuration, $this->executor);

            $mapper2 = $this->session->getMapper('App\\Mapper\\UserMapper');

            // Should be different instances since cache was cleared
            $this->assertNotSame($mapper1, $mapper2);
        }

        public function testCloseIsIdempotent(): void
        {
            $this->executor
                ->expects($this->once())
                ->method('close');

            $this->session->close();
            $this->session->close(); // Second close should do nothing

            $this->assertTrue($this->session->isClosed());
        }

        public function testIsClosedReturnsFalseInitially(): void
        {
            $this->assertFalse($this->session->isClosed());
        }

        public function testIsClosedReturnsTrueAfterClose(): void
        {
            $this->session->close();

            $this->assertTrue($this->session->isClosed());
        }

        public function testClearCacheCallsExecutorClearLocalCache(): void
        {
            $this->executor
                ->expects($this->once())
                ->method('clearLocalCache');

            $this->session->clearCache();
        }

        public function testClearCacheThrowsExceptionWhenSessionClosed(): void
        {
            $this->session->close();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Session is closed');

            $this->session->clearCache();
        }

        public function testGetLastQueryReturnsExecutorLastQuery(): void
        {
            $expectedQuery = [
                'sql' => 'SELECT * FROM users WHERE id = ?',
                'params' => ['id' => 1],
                'time' => 0.05,
            ];

            $this->executor
                ->method('getLastQuery')
                ->willReturn($expectedQuery);

            $result = $this->session->getLastQuery();

            $this->assertSame($expectedQuery, $result);
        }

        public function testGetLastQueryReturnsNullWhenNoQuery(): void
        {
            $this->executor
                ->method('getLastQuery')
                ->willReturn(null);

            $result = $this->session->getLastQuery();

            $this->assertNull($result);
        }

        public function testSelectListWithSqlSource(): void
        {
            $sqlSource = $this->createMock(SqlSource::class);
            $boundSql = new BoundSql('SELECT * FROM users WHERE id = ?', []);

            $sqlSource
                ->method('getBoundSql')
                ->willReturn($boundSql);

            $statement = new MappedStatement(
                'findById',
                'UserMapper',
                StatementType::SELECT,
                null,
                sqlSource: $sqlSource,
            );

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn([['id' => 1, 'name' => 'John']]);

            $result = $this->session->selectList('UserMapper.findById', ['id' => 1]);

            $this->assertNotEmpty($result);
        }

        public function testSelectListWithObjectParameter(): void
        {
            $statement = new MappedStatement('findById', 'UserMapper', StatementType::SELECT, 'SELECT * FROM users WHERE id = ?');

            $parameter = new stdClass();
            $parameter->id = 1;

            $this->configuration->addMappedStatement($statement);

            $this->executor
                ->method('query')
                ->willReturn([['id' => 1, 'name' => 'John']]);

            $result = $this->session->selectList('UserMapper.findById', $parameter);

            $this->assertNotEmpty($result);
        }

        public function testSelectCursorThrowsExceptionWhenWrongStatementType(): void
        {
            $statement = new MappedStatement('insertUser', 'UserMapper', StatementType::INSERT, 'INSERT INTO users (name) VALUES (?)');

            $this->configuration->addMappedStatement($statement);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Statement "UserMapper.insertUser" is a insert statement, but was used as select');

            $this->session->selectCursor('UserMapper.insertUser');
        }
    }
}
