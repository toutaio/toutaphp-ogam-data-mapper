<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Sql;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\Sql\DynamicContext;

final class DynamicContextTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
    }

    public function testAppendSql(): void
    {
        $context = new DynamicContext($this->configuration, []);

        $context->appendSql('SELECT * ');
        $context->appendSql('FROM users');

        $this->assertSame('SELECT * FROM users', $context->getSql());
    }

    public function testEvaluateSimpleProperty(): void
    {
        $context = new DynamicContext($this->configuration, ['name' => 'John']);

        $this->assertSame('John', $context->evaluate('name'));
    }

    public function testEvaluateNestedProperty(): void
    {
        $context = new DynamicContext($this->configuration, [
            'user' => ['name' => 'John', 'age' => 30],
        ]);

        $this->assertSame('John', $context->evaluate('user.name'));
        $this->assertSame(30, $context->evaluate('user.age'));
    }

    public function testEvaluateObjectProperty(): void
    {
        $user = new class {
            public string $name = 'John';

            public function getAge(): int
            {
                return 30;
            }

            public function isActive(): bool
            {
                return true;
            }
        };

        $context = new DynamicContext($this->configuration, ['user' => $user]);

        $this->assertSame('John', $context->evaluate('user.name'));
        $this->assertSame(30, $context->evaluate('user.age'));
        $this->assertTrue($context->evaluate('user.active'));
    }

    public function testEvaluateBooleanTruthy(): void
    {
        $context = new DynamicContext($this->configuration, [
            'hasValue' => true,
            'name' => 'John',
            'count' => 5,
            'items' => [1, 2, 3],
        ]);

        $this->assertTrue($context->evaluateBoolean('hasValue'));
        $this->assertTrue($context->evaluateBoolean('name'));
        $this->assertTrue($context->evaluateBoolean('count'));
        $this->assertTrue($context->evaluateBoolean('items'));
    }

    public function testEvaluateBooleanFalsy(): void
    {
        $context = new DynamicContext($this->configuration, [
            'hasValue' => false,
            'name' => '',
            'count' => 0,
            'items' => [],
            'missing' => null,
        ]);

        $this->assertFalse($context->evaluateBoolean('hasValue'));
        $this->assertFalse($context->evaluateBoolean('name'));
        $this->assertFalse($context->evaluateBoolean('missing'));
        $this->assertFalse($context->evaluateBoolean('nonexistent'));
        $this->assertFalse($context->evaluateBoolean('items'));
    }

    public function testBind(): void
    {
        $context = new DynamicContext($this->configuration, ['original' => 'value']);

        $context->bind('custom', 'custom_value');

        $this->assertSame('custom_value', $context->evaluate('custom'));
        $this->assertSame('value', $context->evaluate('original'));
    }

    public function testBindOverridesEvaluate(): void
    {
        $context = new DynamicContext($this->configuration, ['name' => 'original']);

        $context->bind('name', 'bound');

        $this->assertSame('bound', $context->evaluate('name'));
    }

    public function testGetUniqueNumber(): void
    {
        $context = new DynamicContext($this->configuration, []);

        $this->assertSame(0, $context->getUniqueNumber());
        $this->assertSame(1, $context->getUniqueNumber());
        $this->assertSame(2, $context->getUniqueNumber());
    }

    public function testNullParameter(): void
    {
        $context = new DynamicContext($this->configuration, null);

        $this->assertNull($context->evaluate('anything'));
        $this->assertFalse($context->evaluateBoolean('anything'));
    }
}
