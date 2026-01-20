<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Sql\Node;

use PHPUnit\Framework\TestCase;
use stdClass;
use Touta\Ogam\Configuration;
use Touta\Ogam\Sql\DynamicContext;
use Touta\Ogam\Sql\Node\BindSqlNode;
use Touta\Ogam\Sql\Node\SqlNode;

final class BindSqlNodeTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
    }

    public function testImplementsSqlNode(): void
    {
        $node = new BindSqlNode('pattern', 'name');

        $this->assertInstanceOf(SqlNode::class, $node);
    }

    public function testGetName(): void
    {
        $node = new BindSqlNode('pattern', 'name');

        $this->assertSame('pattern', $node->getName());
    }

    public function testGetValue(): void
    {
        $node = new BindSqlNode('pattern', 'name');

        $this->assertSame('name', $node->getValue());
    }

    public function testApplyReturnsTrue(): void
    {
        $node = new BindSqlNode('pattern', 'name');
        $context = new DynamicContext($this->configuration, ['name' => 'John']);

        $result = $node->apply($context);

        $this->assertTrue($result);
    }

    public function testApplyBindsValue(): void
    {
        $node = new BindSqlNode('pattern', 'name');
        $context = new DynamicContext($this->configuration, ['name' => 'John']);

        $node->apply($context);

        $this->assertSame('John', $context->evaluate('pattern'));
    }

    public function testApplyWithNestedProperty(): void
    {
        $node = new BindSqlNode('fullAddress', 'user.address');
        $context = new DynamicContext($this->configuration, [
            'user' => ['address' => '123 Main St'],
        ]);

        $node->apply($context);

        $this->assertSame('123 Main St', $context->evaluate('fullAddress'));
    }

    public function testApplyWithObjectProperty(): void
    {
        $user = new stdClass();
        $user->name = 'Jane';

        $node = new BindSqlNode('userName', 'user.name');
        $context = new DynamicContext($this->configuration, ['user' => $user]);

        $node->apply($context);

        $this->assertSame('Jane', $context->evaluate('userName'));
    }

    public function testApplyOverwritesPreviousBinding(): void
    {
        $node = new BindSqlNode('value', 'newValue');
        $context = new DynamicContext($this->configuration, ['oldValue' => 'old', 'newValue' => 'new']);

        $context->bind('value', 'old');
        $this->assertSame('old', $context->evaluate('value'));

        $node->apply($context);
        $this->assertSame('new', $context->evaluate('value'));
    }

    public function testApplyWithNullValue(): void
    {
        $node = new BindSqlNode('nullValue', 'missing');
        $context = new DynamicContext($this->configuration, ['missing' => null]);

        $node->apply($context);

        $this->assertNull($context->evaluate('nullValue'));
    }
}
