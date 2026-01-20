<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Session;

use BadMethodCallException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Touta\Ogam\Configuration;
use Touta\Ogam\Contract\SessionInterface;
use Touta\Ogam\Mapping\Hydration;
use Touta\Ogam\Mapping\MappedStatement;
use Touta\Ogam\Mapping\StatementType;
use Touta\Ogam\Session\MapperProxy;

interface TestMapper
{
    public function findById(int $id): ?object;

    public function findAll(): array;

    public function findByName(string $name): ?object;

    public function insert(array $data): int;

    public function update(int $id, string $name): int;

    public function delete(int $id): int;

    public function getCount(): int;

    public function findWithDefault(int $id, string $default = 'none'): ?object;
}

final class MapperProxyTest extends TestCase
{
    private SessionInterface $session;

    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->session = $this->createMock(SessionInterface::class);
        $this->configuration = new Configuration();
    }

    public function testConstructorThrowsExceptionForNonExistentInterface(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mapper interface "NonExistentInterface" does not exist');

        new MapperProxy($this->session, 'NonExistentInterface', $this->configuration);
    }

    public function testCallThrowsExceptionForNonExistentMethod(): void
    {
        $proxy = new MapperProxy($this->session, TestMapper::class, $this->configuration);

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Method "nonExistent" does not exist in mapper interface');

        $proxy->__call('nonExistent', []);
    }

    public function testCallThrowsExceptionWhenStatementNotFound(): void
    {
        $proxy = new MapperProxy($this->session, TestMapper::class, $this->configuration);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No mapped statement found for');

        $proxy->__call('findById', [1]);
    }

    public function testSelectOneWithNullableReturnType(): void
    {
        $statement = new MappedStatement(
            id: 'findById',
            namespace: 'Touta\Ogam\Tests\Unit\Session\TestMapper',
            type: StatementType::SELECT,
        );

        $this->configuration->addMappedStatement($statement);

        $expectedResult = (object) ['id' => 1, 'name' => 'Test'];

        $this->session->expects($this->once())
            ->method('selectOne')
            ->with('Touta\Ogam\Tests\Unit\Session\TestMapper.findById', ['id' => 1])
            ->willReturn($expectedResult);

        $proxy = new MapperProxy($this->session, TestMapper::class, $this->configuration);

        $result = $proxy->__call('findById', [1]);

        $this->assertSame($expectedResult, $result);
    }

    public function testSelectListWithArrayReturnType(): void
    {
        $statement = new MappedStatement(
            id: 'findAll',
            namespace: 'Touta\Ogam\Tests\Unit\Session\TestMapper',
            type: StatementType::SELECT,
        );

        $this->configuration->addMappedStatement($statement);

        $expectedResults = [
            (object) ['id' => 1, 'name' => 'Test1'],
            (object) ['id' => 2, 'name' => 'Test2'],
        ];

        $this->session->expects($this->once())
            ->method('selectList')
            ->with('Touta\Ogam\Tests\Unit\Session\TestMapper.findAll', null)
            ->willReturn($expectedResults);

        $proxy = new MapperProxy($this->session, TestMapper::class, $this->configuration);

        $result = $proxy->__call('findAll', []);

        $this->assertSame($expectedResults, $result);
    }

    public function testInsertStatement(): void
    {
        $statement = new MappedStatement(
            id: 'insert',
            namespace: 'Touta\Ogam\Tests\Unit\Session\TestMapper',
            type: StatementType::INSERT,
        );

        $this->configuration->addMappedStatement($statement);

        $this->session->expects($this->once())
            ->method('insert')
            ->with('Touta\Ogam\Tests\Unit\Session\TestMapper.insert', ['name' => 'New User'])
            ->willReturn(1);

        $proxy = new MapperProxy($this->session, TestMapper::class, $this->configuration);

        $result = $proxy->__call('insert', [['name' => 'New User']]);

        $this->assertEquals(1, $result);
    }

    public function testUpdateStatement(): void
    {
        $statement = new MappedStatement(
            id: 'update',
            namespace: 'Touta\Ogam\Tests\Unit\Session\TestMapper',
            type: StatementType::UPDATE,
        );

        $this->configuration->addMappedStatement($statement);

        $this->session->expects($this->once())
            ->method('update')
            ->with('Touta\Ogam\Tests\Unit\Session\TestMapper.update', ['id' => 1, 'name' => 'Updated'])
            ->willReturn(1);

        $proxy = new MapperProxy($this->session, TestMapper::class, $this->configuration);

        $result = $proxy->__call('update', [1, 'Updated']);

        $this->assertEquals(1, $result);
    }

    public function testDeleteStatement(): void
    {
        $statement = new MappedStatement(
            id: 'delete',
            namespace: 'Touta\Ogam\Tests\Unit\Session\TestMapper',
            type: StatementType::DELETE,
        );

        $this->configuration->addMappedStatement($statement);

        $this->session->expects($this->once())
            ->method('delete')
            ->with('Touta\Ogam\Tests\Unit\Session\TestMapper.delete', ['id' => 1])
            ->willReturn(1);

        $proxy = new MapperProxy($this->session, TestMapper::class, $this->configuration);

        $result = $proxy->__call('delete', [1]);

        $this->assertEquals(1, $result);
    }

    public function testSelectWithScalarReturnType(): void
    {
        $statement = new MappedStatement(
            id: 'getCount',
            namespace: 'Touta\Ogam\Tests\Unit\Session\TestMapper',
            type: StatementType::SELECT,
        );

        $this->configuration->addMappedStatement($statement);

        $this->session->expects($this->once())
            ->method('selectOne')
            ->with('Touta\Ogam\Tests\Unit\Session\TestMapper.getCount', null, Hydration::SCALAR)
            ->willReturn(42);

        $proxy = new MapperProxy($this->session, TestMapper::class, $this->configuration);

        $result = $proxy->__call('getCount', []);

        $this->assertEquals(42, $result);
    }

    public function testSelectWithSingleObjectParameter(): void
    {
        $statement = new MappedStatement(
            id: 'findById',
            namespace: 'Touta\Ogam\Tests\Unit\Session\TestMapper',
            type: StatementType::SELECT,
        );

        $this->configuration->addMappedStatement($statement);

        $parameter = (object) ['id' => 1];

        $this->session->expects($this->once())
            ->method('selectOne')
            ->with('Touta\Ogam\Tests\Unit\Session\TestMapper.findById', $parameter)
            ->willReturn(null);

        $proxy = new MapperProxy($this->session, TestMapper::class, $this->configuration);

        $proxy->__call('findById', [$parameter]);
    }

    public function testParameterExtractionWithDefaultValue(): void
    {
        $statement = new MappedStatement(
            id: 'findWithDefault',
            namespace: 'Touta\Ogam\Tests\Unit\Session\TestMapper',
            type: StatementType::SELECT,
        );

        $this->configuration->addMappedStatement($statement);

        $this->session->expects($this->once())
            ->method('selectOne')
            ->with(
                'Touta\Ogam\Tests\Unit\Session\TestMapper.findWithDefault',
                $this->callback(function ($param) {
                    return $param['id'] === 1 && $param['default'] === 'none';
                }),
            )
            ->willReturn(null);

        $proxy = new MapperProxy($this->session, TestMapper::class, $this->configuration);

        $proxy->__call('findWithDefault', [1]);
    }

    public function testParameterExtractionWithMultipleArguments(): void
    {
        $statement = new MappedStatement(
            id: 'update',
            namespace: 'Touta\Ogam\Tests\Unit\Session\TestMapper',
            type: StatementType::UPDATE,
        );

        $this->configuration->addMappedStatement($statement);

        $this->session->expects($this->once())
            ->method('update')
            ->with(
                'Touta\Ogam\Tests\Unit\Session\TestMapper.update',
                $this->callback(function ($param) {
                    return $param['id'] === 5 && $param['name'] === 'NewName';
                }),
            )
            ->willReturn(1);

        $proxy = new MapperProxy($this->session, TestMapper::class, $this->configuration);

        $proxy->__call('update', [5, 'NewName']);
    }

    public function testMethodCaching(): void
    {
        $statement = new MappedStatement(
            id: 'findById',
            namespace: 'Touta\Ogam\Tests\Unit\Session\TestMapper',
            type: StatementType::SELECT,
        );

        $this->configuration->addMappedStatement($statement);

        $this->session->method('selectOne')
            ->willReturn(null);

        $proxy = new MapperProxy($this->session, TestMapper::class, $this->configuration);

        $proxy->__call('findById', [1]);
        $proxy->__call('findById', [2]);

        $this->assertTrue(true);
    }

    public function testCallableStatementType(): void
    {
        $statement = new MappedStatement(
            id: 'findAll',
            namespace: 'Touta\Ogam\Tests\Unit\Session\TestMapper',
            type: StatementType::CALLABLE,
        );

        $this->configuration->addMappedStatement($statement);

        $this->session->expects($this->once())
            ->method('selectList')
            ->with('Touta\Ogam\Tests\Unit\Session\TestMapper.findAll', null)
            ->willReturn([]);

        $proxy = new MapperProxy($this->session, TestMapper::class, $this->configuration);

        $result = $proxy->__call('findAll', []);

        $this->assertIsArray($result);
    }
}
