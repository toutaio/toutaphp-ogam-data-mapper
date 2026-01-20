<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Attribute;

use Attribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Touta\Ogam\Attribute\Mapper;
use Touta\Ogam\Attribute\Param;
use Touta\Ogam\Attribute\Select;

#[CoversClass(Param::class)]
final class ParamAttributeTest extends TestCase
{
    #[Test]
    public function canBeInstantiated(): void
    {
        $param = new Param('userId');

        $this->assertInstanceOf(Param::class, $param);
    }

    #[Test]
    public function hasName(): void
    {
        $param = new Param('userId');

        $this->assertSame('userId', $param->name);
    }

    #[Test]
    public function isTargetedAtParameters(): void
    {
        $reflection = new ReflectionClass(Param::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame(Attribute::TARGET_PARAMETER, $instance->flags);
    }

    #[Test]
    public function canBeReadFromMethodParameter(): void
    {
        $reflection = new ReflectionClass(TestParamMapperInterface::class);
        $method = $reflection->getMethod('findByEmail');
        $parameters = $method->getParameters();

        $this->assertCount(1, $parameters);

        $emailParam = $parameters[0];
        $attributes = $emailParam->getAttributes(Param::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame('pattern', $instance->name);
    }

    #[Test]
    public function canBeUsedOnMultipleParameters(): void
    {
        $reflection = new ReflectionClass(TestParamMapperInterface::class);
        $method = $reflection->getMethod('findByNameAndStatus');
        $parameters = $method->getParameters();

        $this->assertCount(2, $parameters);

        // First parameter
        $nameParam = $parameters[0];
        $nameAttrs = $nameParam->getAttributes(Param::class);
        $this->assertCount(1, $nameAttrs);
        $this->assertSame('name', $nameAttrs[0]->newInstance()->name);

        // Second parameter
        $statusParam = $parameters[1];
        $statusAttrs = $statusParam->getAttributes(Param::class);
        $this->assertCount(1, $statusAttrs);
        $this->assertSame('status', $statusAttrs[0]->newInstance()->name);
    }

    #[Test]
    public function parameterWithoutAttributeHasNoAttributes(): void
    {
        $reflection = new ReflectionClass(TestParamMapperInterface::class);
        $method = $reflection->getMethod('findById');
        $parameters = $method->getParameters();

        $this->assertCount(1, $parameters);

        $idParam = $parameters[0];
        $attributes = $idParam->getAttributes(Param::class);

        $this->assertCount(0, $attributes);
    }
}

#[Mapper]
interface TestParamMapperInterface
{
    #[Select('SELECT * FROM users WHERE id = :id')]
    public function findById(int $id): ?object;

    #[Select('SELECT * FROM users WHERE email LIKE :pattern')]
    public function findByEmail(#[Param('pattern')] string $emailPattern): array;

    #[Select('SELECT * FROM users WHERE name = :name AND status = :status')]
    public function findByNameAndStatus(
        #[Param('name')]
        string $userName,
        #[Param('status')]
        string $userStatus,
    ): array;
}
