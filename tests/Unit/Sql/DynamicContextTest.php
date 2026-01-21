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

    // ========================================================================
    // Enhanced Expression Evaluation (Complex Expressions)
    // ========================================================================

    public function testEvaluateComparisonExpression(): void
    {
        $context = new DynamicContext($this->configuration, [
            'status' => 'active',
            'age' => 25,
        ]);

        $this->assertTrue($context->evaluateBoolean("status == 'active'"));
        $this->assertFalse($context->evaluateBoolean("status == 'inactive'"));
        $this->assertTrue($context->evaluateBoolean('age > 18'));
        $this->assertTrue($context->evaluateBoolean('age >= 25'));
        $this->assertFalse($context->evaluateBoolean('age < 18'));
    }

    public function testEvaluateNullCheckExpression(): void
    {
        $context = new DynamicContext($this->configuration, [
            'name' => 'John',
            'missing' => null,
        ]);

        $this->assertTrue($context->evaluateBoolean('name !== null'));
        $this->assertFalse($context->evaluateBoolean('name === null'));
        $this->assertTrue($context->evaluateBoolean('missing === null'));
        $this->assertFalse($context->evaluateBoolean('missing !== null'));
    }

    public function testEvaluateLogicalExpression(): void
    {
        $context = new DynamicContext($this->configuration, [
            'active' => true,
            'verified' => false,
            'age' => 25,
        ]);

        $this->assertTrue($context->evaluateBoolean('active && age > 18'));
        $this->assertFalse($context->evaluateBoolean('active && verified'));
        $this->assertTrue($context->evaluateBoolean('active || verified'));
        $this->assertTrue($context->evaluateBoolean('!verified'));
    }

    public function testEvaluateComplexExpression(): void
    {
        $context = new DynamicContext($this->configuration, [
            'user' => [
                'status' => 'active',
                'age' => 25,
            ],
            'includeInactive' => false,
        ]);

        // Complex real-world expression
        $this->assertTrue($context->evaluateBoolean(
            "(user.status == 'active' || includeInactive) && user.age >= 18",
        ));
    }

    public function testEvaluateWithBoundVariable(): void
    {
        $context = new DynamicContext($this->configuration, [
            'name' => 'John',
        ]);

        // Bind a variable
        $context->bind('pattern', '%john%');

        // Evaluate using both parameter and bound variable
        $this->assertSame('%john%', $context->evaluate('pattern'));
        $this->assertTrue($context->evaluateBoolean('name !== null'));
    }

    public function testEvaluateWithObjectParameter(): void
    {
        $user = new class {
            public string $name = 'John';

            public string $status = 'active';

            public function getAge(): int
            {
                return 25;
            }
        };

        $context = new DynamicContext($this->configuration, $user);

        $this->assertSame('John', $context->evaluate('name'));
        $this->assertTrue($context->evaluateBoolean("status == 'active'"));
    }

    public function testEvaluateStrictComparison(): void
    {
        $context = new DynamicContext($this->configuration, [
            'count' => 5,
            'text' => '5',
        ]);

        $this->assertTrue($context->evaluateBoolean('count === 5'));
        $this->assertFalse($context->evaluateBoolean("count === '5'"));
        $this->assertTrue($context->evaluateBoolean("text === '5'"));
        $this->assertFalse($context->evaluateBoolean('text === 5'));
    }

    public function testEvaluateWithLiterals(): void
    {
        $context = new DynamicContext($this->configuration, []);

        $this->assertTrue($context->evaluateBoolean('true'));
        $this->assertFalse($context->evaluateBoolean('false'));
        $this->assertNull($context->evaluate('null'));
        $this->assertSame(42, $context->evaluate('42'));
        $this->assertSame('hello', $context->evaluate("'hello'"));
    }
}
