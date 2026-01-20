<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Attribute;

use Attribute;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Touta\Ogam\Attribute\Mapper;
use Touta\Ogam\Attribute\Result;
use Touta\Ogam\Attribute\Results;
use Touta\Ogam\Attribute\Select;

#[CoversClass(Result::class)]
#[CoversClass(Results::class)]
final class ResultAttributeTest extends TestCase
{
    #[Test]
    public function resultCanBeInstantiated(): void
    {
        $result = new Result(property: 'id', column: 'user_id');

        $this->assertInstanceOf(Result::class, $result);
    }

    #[Test]
    public function resultHasRequiredProperties(): void
    {
        $result = new Result(property: 'id', column: 'user_id');

        $this->assertSame('id', $result->property);
        $this->assertSame('user_id', $result->column);
    }

    #[Test]
    public function resultHasDefaultValues(): void
    {
        $result = new Result(property: 'id', column: 'user_id');

        $this->assertNull($result->phpType);
        $this->assertNull($result->typeHandler);
    }

    #[Test]
    public function resultAcceptsAllOptions(): void
    {
        $result = new Result(
            property: 'createdAt',
            column: 'created_at',
            phpType: DateTimeImmutable::class,
            typeHandler: 'App\\TypeHandler\\DateTimeHandler',
        );

        $this->assertSame('createdAt', $result->property);
        $this->assertSame('created_at', $result->column);
        $this->assertSame(DateTimeImmutable::class, $result->phpType);
        $this->assertSame('App\\TypeHandler\\DateTimeHandler', $result->typeHandler);
    }

    #[Test]
    public function resultIsTargetedAtMethodsAndRepeatable(): void
    {
        $reflection = new ReflectionClass(Result::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE, $instance->flags);
    }

    #[Test]
    public function resultsCanBeInstantiated(): void
    {
        $results = new Results([
            new Result(property: 'id', column: 'id'),
            new Result(property: 'name', column: 'name'),
        ]);

        $this->assertInstanceOf(Results::class, $results);
    }

    #[Test]
    public function resultsContainsMappings(): void
    {
        $results = new Results([
            new Result(property: 'id', column: 'id'),
            new Result(property: 'name', column: 'name'),
        ]);

        $this->assertCount(2, $results->value);
        $this->assertSame('id', $results->value[0]->property);
        $this->assertSame('name', $results->value[1]->property);
    }

    #[Test]
    public function resultsIsTargetedAtMethods(): void
    {
        $reflection = new ReflectionClass(Results::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame(Attribute::TARGET_METHOD, $instance->flags);
    }

    #[Test]
    public function repeatableResultsCanBeReadFromMethod(): void
    {
        $reflection = new ReflectionClass(TestResultMapperInterface::class);
        $method = $reflection->getMethod('findAll');
        $attributes = $method->getAttributes(Result::class);

        $this->assertCount(3, $attributes);

        $instances = array_map(fn($attr) => $attr->newInstance(), $attributes);

        $this->assertSame('id', $instances[0]->property);
        $this->assertSame('id', $instances[0]->column);

        $this->assertSame('username', $instances[1]->property);
        $this->assertSame('user_name', $instances[1]->column);

        $this->assertSame('email', $instances[2]->property);
        $this->assertSame('email_address', $instances[2]->column);
    }

    #[Test]
    public function resultsAttributeCanBeReadFromMethod(): void
    {
        $reflection = new ReflectionClass(TestResultMapperInterface::class);
        $method = $reflection->getMethod('findById');
        $attributes = $method->getAttributes(Results::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertCount(2, $instance->value);

        $this->assertSame('id', $instance->value[0]->property);
        $this->assertSame('id', $instance->value[0]->column);

        $this->assertSame('name', $instance->value[1]->property);
        $this->assertSame('user_name', $instance->value[1]->column);
    }
}

#[Mapper]
interface TestResultMapperInterface
{
    #[Select('SELECT id, user_name, email_address FROM users')]
    #[Result(property: 'id', column: 'id')]
    #[Result(property: 'username', column: 'user_name')]
    #[Result(property: 'email', column: 'email_address')]
    public function findAll(): array;

    #[Select('SELECT id, user_name FROM users WHERE id = :id')]
    #[Results([
        new Result(property: 'id', column: 'id'),
        new Result(property: 'name', column: 'user_name'),
    ])]
    public function findById(int $id): ?object;
}
