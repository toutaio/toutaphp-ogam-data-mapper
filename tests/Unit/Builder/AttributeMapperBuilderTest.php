<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Builder;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Touta\Ogam\Attribute\Delete;
use Touta\Ogam\Attribute\Insert;
use Touta\Ogam\Attribute\Mapper;
use Touta\Ogam\Attribute\Options;
use Touta\Ogam\Attribute\Result;
use Touta\Ogam\Attribute\Results;
use Touta\Ogam\Attribute\Select;
use Touta\Ogam\Attribute\Update;
use Touta\Ogam\Builder\AttributeMapperBuilder;
use Touta\Ogam\Configuration;
use Touta\Ogam\Mapping\StatementType;

#[CoversClass(AttributeMapperBuilder::class)]
final class AttributeMapperBuilderTest extends TestCase
{
    private Configuration $configuration;

    private AttributeMapperBuilder $builder;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        $this->builder = new AttributeMapperBuilder($this->configuration);
    }

    #[Test]
    public function canBeInstantiated(): void
    {
        $this->assertInstanceOf(AttributeMapperBuilder::class, $this->builder);
    }

    #[Test]
    public function parsesMapperInterface(): void
    {
        $this->builder->parse(TestAttributeMapperInterface::class);

        $this->assertTrue($this->configuration->hasMapper(TestAttributeMapperInterface::class));
    }

    #[Test]
    public function parsesSelectStatement(): void
    {
        $this->builder->parse(TestAttributeMapperInterface::class);

        $statement = $this->configuration->getMappedStatement(
            TestAttributeMapperInterface::class . '.findById',
        );

        $this->assertNotNull($statement);
        $this->assertSame('findById', $statement->getId());
        $this->assertSame(TestAttributeMapperInterface::class, $statement->getNamespace());
        $this->assertSame(StatementType::SELECT, $statement->getType());
    }

    #[Test]
    public function parsesInsertStatement(): void
    {
        $this->builder->parse(TestAttributeMapperInterface::class);

        $statement = $this->configuration->getMappedStatement(
            TestAttributeMapperInterface::class . '.insert',
        );

        $this->assertNotNull($statement);
        $this->assertSame('insert', $statement->getId());
        $this->assertSame(StatementType::INSERT, $statement->getType());
    }

    #[Test]
    public function parsesUpdateStatement(): void
    {
        $this->builder->parse(TestAttributeMapperInterface::class);

        $statement = $this->configuration->getMappedStatement(
            TestAttributeMapperInterface::class . '.update',
        );

        $this->assertNotNull($statement);
        $this->assertSame('update', $statement->getId());
        $this->assertSame(StatementType::UPDATE, $statement->getType());
    }

    #[Test]
    public function parsesDeleteStatement(): void
    {
        $this->builder->parse(TestAttributeMapperInterface::class);

        $statement = $this->configuration->getMappedStatement(
            TestAttributeMapperInterface::class . '.delete',
        );

        $this->assertNotNull($statement);
        $this->assertSame('delete', $statement->getId());
        $this->assertSame(StatementType::DELETE, $statement->getType());
    }

    #[Test]
    public function parsesSelectWithOptions(): void
    {
        $this->builder->parse(TestAttributeMapperInterface::class);

        $statement = $this->configuration->getMappedStatement(
            TestAttributeMapperInterface::class . '.findByIdWithOptions',
        );

        $this->assertNotNull($statement);
        $this->assertSame('userResultMap', $statement->getResultMapId());
        $this->assertSame(30, $statement->getTimeout());
    }

    #[Test]
    public function parsesInsertWithGeneratedKeys(): void
    {
        $this->builder->parse(TestAttributeMapperInterface::class);

        $statement = $this->configuration->getMappedStatement(
            TestAttributeMapperInterface::class . '.insertWithGeneratedKeys',
        );

        $this->assertNotNull($statement);
        $this->assertTrue($statement->isUseGeneratedKeys());
        $this->assertSame('id', $statement->getKeyProperty());
    }

    #[Test]
    public function usesCustomNamespaceFromMapperAttribute(): void
    {
        $this->builder->parse(TestCustomNamespaceMapperInterface::class);

        $statement = $this->configuration->getMappedStatement(
            'App\\Mapper\\CustomMapper.findById',
        );

        $this->assertNotNull($statement);
        $this->assertSame('App\\Mapper\\CustomMapper', $statement->getNamespace());
    }

    #[Test]
    public function parsesResultMappings(): void
    {
        $this->builder->parse(TestResultMappingMapperInterface::class);

        $resultMap = $this->configuration->getResultMap(
            TestResultMappingMapperInterface::class . '.findAllResultMap',
        );

        $this->assertNotNull($resultMap);
        $this->assertCount(3, $resultMap->getResultMappings());

        $mappings = $resultMap->getResultMappings();
        $this->assertSame('id', $mappings[0]->getProperty());
        $this->assertSame('user_id', $mappings[0]->getColumn());

        $this->assertSame('username', $mappings[1]->getProperty());
        $this->assertSame('user_name', $mappings[1]->getColumn());

        $this->assertSame('email', $mappings[2]->getProperty());
        $this->assertSame('email_address', $mappings[2]->getColumn());
    }

    #[Test]
    public function parsesResultsAttribute(): void
    {
        $this->builder->parse(TestResultMappingMapperInterface::class);

        $resultMap = $this->configuration->getResultMap(
            TestResultMappingMapperInterface::class . '.findByIdResultMap',
        );

        $this->assertNotNull($resultMap);
        $this->assertCount(2, $resultMap->getResultMappings());
    }

    #[Test]
    public function skipsMethodsWithoutStatementAttributes(): void
    {
        $this->builder->parse(TestAttributeMapperInterface::class);

        $statement = $this->configuration->getMappedStatement(
            TestAttributeMapperInterface::class . '.notAStatement',
        );

        $this->assertNull($statement);
    }

    #[Test]
    public function throwsExceptionForNonInterface(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be an interface');

        $this->builder->parse(stdClass::class);
    }

    #[Test]
    public function throwsExceptionForMissingMapperAttribute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must have #[Mapper] attribute');

        $this->builder->parse(TestMissingMapperAttributeInterface::class);
    }
}

#[Mapper]
interface TestAttributeMapperInterface
{
    #[Select('SELECT * FROM users WHERE id = :id')]
    public function findById(int $id): ?object;

    #[Select(
        sql: 'SELECT * FROM users WHERE id = :id',
        resultMap: 'userResultMap',
        timeout: 30,
    )]
    public function findByIdWithOptions(int $id): ?object;

    #[Insert('INSERT INTO users (name, email) VALUES (:name, :email)')]
    public function insert(object $user): int;

    #[Insert('INSERT INTO users (name, email) VALUES (:name, :email)')]
    #[Options(useGeneratedKeys: true, keyProperty: 'id')]
    public function insertWithGeneratedKeys(object $user): int;

    #[Update('UPDATE users SET name = :name WHERE id = :id')]
    public function update(object $user): int;

    #[Delete('DELETE FROM users WHERE id = :id')]
    public function delete(int $id): int;

    public function notAStatement(): void;
}

#[Mapper(namespace: 'App\\Mapper\\CustomMapper')]
interface TestCustomNamespaceMapperInterface
{
    #[Select('SELECT * FROM users WHERE id = :id')]
    public function findById(int $id): ?object;
}

#[Mapper]
interface TestResultMappingMapperInterface
{
    #[Select('SELECT user_id, user_name, email_address FROM users')]
    #[Result(property: 'id', column: 'user_id')]
    #[Result(property: 'username', column: 'user_name')]
    #[Result(property: 'email', column: 'email_address')]
    public function findAll(): array;

    #[Select('SELECT user_id, user_name FROM users WHERE user_id = :id')]
    #[Results([
        new Result(property: 'id', column: 'user_id'),
        new Result(property: 'name', column: 'user_name'),
    ])]
    public function findById(int $id): ?object;
}

interface TestMissingMapperAttributeInterface
{
    public function findById(int $id): ?object;
}
