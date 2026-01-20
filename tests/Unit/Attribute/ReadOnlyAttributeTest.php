<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Touta\Ogam\Attribute\ReadOptimized;

#[CoversClass(ReadOptimized::class)]
final class ReadOnlyAttributeTest extends TestCase
{
    #[Test]
    public function canBeAppliedToClass(): void
    {
        $reflection = new ReflectionClass(ReadOptimizedTestClass::class);
        $attributes = $reflection->getAttributes(ReadOptimized::class);

        $this->assertCount(1, $attributes);
    }

    #[Test]
    public function canBeAppliedToMethod(): void
    {
        $reflection = new ReflectionMethod(ReadOptimizedTestMapper::class, 'findAll');
        $attributes = $reflection->getAttributes(ReadOptimized::class);

        $this->assertCount(1, $attributes);
    }

    #[Test]
    public function hasDefaultValues(): void
    {
        $attribute = new ReadOptimized();

        $this->assertTrue($attribute->skipLocalCache);
        $this->assertTrue($attribute->useConstructorHydration);
    }

    #[Test]
    public function canOverrideDefaultValues(): void
    {
        $attribute = new ReadOptimized(
            skipLocalCache: false,
            useConstructorHydration: false,
        );

        $this->assertFalse($attribute->skipLocalCache);
        $this->assertFalse($attribute->useConstructorHydration);
    }

    #[Test]
    public function attributeIsReadonly(): void
    {
        $reflection = new ReflectionClass(ReadOptimized::class);

        $this->assertTrue($reflection->isReadOnly());
    }
}

#[ReadOptimized]
final readonly class ReadOptimizedTestClass
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}

interface ReadOptimizedTestMapper
{
    #[ReadOptimized]
    public function findAll(): array;
}
