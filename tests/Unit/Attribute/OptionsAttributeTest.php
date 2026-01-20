<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Attribute;

use Attribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Touta\Ogam\Attribute\Insert;
use Touta\Ogam\Attribute\Mapper;
use Touta\Ogam\Attribute\Options;
use Touta\Ogam\Mapping\StatementType;

#[CoversClass(Options::class)]
final class OptionsAttributeTest extends TestCase
{
    #[Test]
    public function canBeInstantiated(): void
    {
        $options = new Options();

        $this->assertInstanceOf(Options::class, $options);
    }

    #[Test]
    public function hasDefaultValues(): void
    {
        $options = new Options();

        $this->assertFalse($options->useGeneratedKeys);
        $this->assertNull($options->keyProperty);
        $this->assertNull($options->keyColumn);
        $this->assertNull($options->resultSetType);
        $this->assertNull($options->statementType);
        $this->assertNull($options->fetchSize);
        $this->assertNull($options->timeout);
        $this->assertFalse($options->flushCache);
        $this->assertTrue($options->useCache);
    }

    #[Test]
    public function acceptsUseGeneratedKeys(): void
    {
        $options = new Options(useGeneratedKeys: true);

        $this->assertTrue($options->useGeneratedKeys);
    }

    #[Test]
    public function acceptsKeyProperty(): void
    {
        $options = new Options(
            useGeneratedKeys: true,
            keyProperty: 'id',
        );

        $this->assertTrue($options->useGeneratedKeys);
        $this->assertSame('id', $options->keyProperty);
    }

    #[Test]
    public function acceptsKeyColumn(): void
    {
        $options = new Options(
            useGeneratedKeys: true,
            keyProperty: 'id',
            keyColumn: 'user_id',
        );

        $this->assertSame('user_id', $options->keyColumn);
    }

    #[Test]
    public function acceptsStatementType(): void
    {
        $options = new Options(statementType: StatementType::INSERT);

        $this->assertSame(StatementType::INSERT, $options->statementType);
    }

    #[Test]
    public function acceptsFetchSize(): void
    {
        $options = new Options(fetchSize: 100);

        $this->assertSame(100, $options->fetchSize);
    }

    #[Test]
    public function acceptsTimeout(): void
    {
        $options = new Options(timeout: 30);

        $this->assertSame(30, $options->timeout);
    }

    #[Test]
    public function acceptsFlushCache(): void
    {
        $options = new Options(flushCache: true);

        $this->assertTrue($options->flushCache);
    }

    #[Test]
    public function acceptsUseCache(): void
    {
        $options = new Options(useCache: false);

        $this->assertFalse($options->useCache);
    }

    #[Test]
    public function acceptsAllOptions(): void
    {
        $options = new Options(
            useGeneratedKeys: true,
            keyProperty: 'id',
            keyColumn: 'user_id',
            resultSetType: 'FORWARD_ONLY',
            statementType: StatementType::INSERT,
            fetchSize: 50,
            timeout: 60,
            flushCache: true,
            useCache: false,
        );

        $this->assertTrue($options->useGeneratedKeys);
        $this->assertSame('id', $options->keyProperty);
        $this->assertSame('user_id', $options->keyColumn);
        $this->assertSame('FORWARD_ONLY', $options->resultSetType);
        $this->assertSame(StatementType::INSERT, $options->statementType);
        $this->assertSame(50, $options->fetchSize);
        $this->assertSame(60, $options->timeout);
        $this->assertTrue($options->flushCache);
        $this->assertFalse($options->useCache);
    }

    #[Test]
    public function isTargetedAtMethods(): void
    {
        $reflection = new ReflectionClass(Options::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame(Attribute::TARGET_METHOD, $instance->flags);
    }

    #[Test]
    public function canBeReadFromMethod(): void
    {
        $reflection = new ReflectionClass(TestOptionsMapperInterface::class);
        $method = $reflection->getMethod('insert');
        $attributes = $method->getAttributes(Options::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertTrue($instance->useGeneratedKeys);
        $this->assertSame('id', $instance->keyProperty);
    }
}

#[Mapper]
interface TestOptionsMapperInterface
{
    #[Insert('INSERT INTO users (name, email) VALUES (:name, :email)')]
    #[Options(useGeneratedKeys: true, keyProperty: 'id')]
    public function insert(object $user): int;
}
