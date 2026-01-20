<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Attribute;

use Attribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Touta\Ogam\Attribute\Mapper;

#[CoversClass(Mapper::class)]
final class MapperAttributeTest extends TestCase
{
    #[Test]
    public function canBeInstantiated(): void
    {
        $mapper = new Mapper();

        $this->assertInstanceOf(Mapper::class, $mapper);
    }

    #[Test]
    public function hasDefaultNamespace(): void
    {
        $mapper = new Mapper();

        $this->assertNull($mapper->namespace);
    }

    #[Test]
    public function acceptsNamespace(): void
    {
        $mapper = new Mapper(namespace: 'App\\Mapper\\UserMapper');

        $this->assertSame('App\\Mapper\\UserMapper', $mapper->namespace);
    }

    #[Test]
    public function isTargetedAtInterfaces(): void
    {
        $reflection = new ReflectionClass(Mapper::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame(Attribute::TARGET_CLASS, $instance->flags);
    }

    #[Test]
    public function canBeReadFromInterface(): void
    {
        $reflection = new ReflectionClass(TestMapperInterface::class);
        $attributes = $reflection->getAttributes(Mapper::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame('App\\Mapper\\TestMapper', $instance->namespace);
    }
}

#[Mapper(namespace: 'App\\Mapper\\TestMapper')]
interface TestMapperInterface {}
