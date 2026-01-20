<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Sql\Node;

use PHPUnit\Framework\TestCase;
use stdClass;
use Touta\Ogam\Configuration;
use Touta\Ogam\Sql\DynamicContext;
use Touta\Ogam\Sql\Node\IfSqlNode;
use Touta\Ogam\Sql\Node\SqlNode;
use Touta\Ogam\Sql\Node\TextSqlNode;

final class IfSqlNodeTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
    }

    public function testImplementsSqlNode(): void
    {
        $contents = new TextSqlNode('AND name = #{name}');
        $node = new IfSqlNode('name != null', $contents);

        $this->assertInstanceOf(SqlNode::class, $node);
    }

    public function testGetTest(): void
    {
        $contents = new TextSqlNode('AND name = #{name}');
        $node = new IfSqlNode('name != null', $contents);

        $this->assertSame('name != null', $node->getTest());
    }

    public function testGetContents(): void
    {
        $contents = new TextSqlNode('AND name = #{name}');
        $node = new IfSqlNode('name != null', $contents);

        $this->assertSame($contents, $node->getContents());
    }

    public function testApplyReturnsTrueWhenConditionIsTrue(): void
    {
        $contents = new TextSqlNode('AND name = #{name}');
        $node = new IfSqlNode('name', $contents);
        $context = new DynamicContext($this->configuration, ['name' => 'John']);

        $result = $node->apply($context);

        $this->assertTrue($result);
    }

    public function testApplyReturnsFalseWhenConditionIsFalse(): void
    {
        $contents = new TextSqlNode('AND name = #{name}');
        $node = new IfSqlNode('name', $contents);
        $context = new DynamicContext($this->configuration, ['name' => null]);

        $result = $node->apply($context);

        $this->assertFalse($result);
    }

    public function testApplyAppendsContentWhenConditionIsTrue(): void
    {
        $contents = new TextSqlNode('AND name = #{name}');
        $node = new IfSqlNode('name', $contents);
        $context = new DynamicContext($this->configuration, ['name' => 'John']);

        $node->apply($context);

        $this->assertSame('AND name = #{name}', $context->getSql());
    }

    public function testApplyDoesNotAppendContentWhenConditionIsFalse(): void
    {
        $contents = new TextSqlNode('AND name = #{name}');
        $node = new IfSqlNode('name', $contents);
        $context = new DynamicContext($this->configuration, ['name' => null]);

        $context->appendSql('SELECT * FROM users WHERE 1=1 ');
        $node->apply($context);

        $this->assertSame('SELECT * FROM users WHERE 1=1 ', $context->getSql());
    }

    public function testApplyWithBooleanTrue(): void
    {
        $contents = new TextSqlNode('AND active = 1');
        $node = new IfSqlNode('isActive', $contents);
        $context = new DynamicContext($this->configuration, ['isActive' => true]);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('AND active = 1', $context->getSql());
    }

    public function testApplyWithBooleanFalse(): void
    {
        $contents = new TextSqlNode('AND active = 1');
        $node = new IfSqlNode('isActive', $contents);
        $context = new DynamicContext($this->configuration, ['isActive' => false]);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyWithNonEmptyString(): void
    {
        $contents = new TextSqlNode('AND status = #{status}');
        $node = new IfSqlNode('status', $contents);
        $context = new DynamicContext($this->configuration, ['status' => 'active']);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('AND status = #{status}', $context->getSql());
    }

    public function testApplyWithEmptyString(): void
    {
        $contents = new TextSqlNode('AND status = #{status}');
        $node = new IfSqlNode('status', $contents);
        $context = new DynamicContext($this->configuration, ['status' => '']);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyWithNonEmptyArray(): void
    {
        $contents = new TextSqlNode('AND id IN (#{ids})');
        $node = new IfSqlNode('ids', $contents);
        $context = new DynamicContext($this->configuration, ['ids' => [1, 2, 3]]);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('AND id IN (#{ids})', $context->getSql());
    }

    public function testApplyWithEmptyArray(): void
    {
        $contents = new TextSqlNode('AND id IN (#{ids})');
        $node = new IfSqlNode('ids', $contents);
        $context = new DynamicContext($this->configuration, ['ids' => []]);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyWithNumericZero(): void
    {
        $contents = new TextSqlNode('AND count = 0');
        $node = new IfSqlNode('count', $contents);
        $context = new DynamicContext($this->configuration, ['count' => 0]);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyWithNumericNonZero(): void
    {
        $contents = new TextSqlNode('AND count = #{count}');
        $node = new IfSqlNode('count', $contents);
        $context = new DynamicContext($this->configuration, ['count' => 5]);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('AND count = #{count}', $context->getSql());
    }

    public function testApplyWithNestedProperty(): void
    {
        $contents = new TextSqlNode('AND user.name = #{user.name}');
        $node = new IfSqlNode('user.name', $contents);
        $context = new DynamicContext($this->configuration, [
            'user' => ['name' => 'John'],
        ]);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('AND user.name = #{user.name}', $context->getSql());
    }

    public function testApplyWithObjectProperty(): void
    {
        $user = new stdClass();
        $user->name = 'Jane';

        $contents = new TextSqlNode('AND name = #{user.name}');
        $node = new IfSqlNode('user.name', $contents);
        $context = new DynamicContext($this->configuration, ['user' => $user]);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('AND name = #{user.name}', $context->getSql());
    }

    public function testApplyWithMissingProperty(): void
    {
        $contents = new TextSqlNode('AND email = #{email}');
        $node = new IfSqlNode('email', $contents);
        $context = new DynamicContext($this->configuration, ['name' => 'John']);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyWithNullParameter(): void
    {
        $contents = new TextSqlNode('AND id = 1');
        $node = new IfSqlNode('id', $contents);
        $context = new DynamicContext($this->configuration, null);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyMultipleTimes(): void
    {
        $contents = new TextSqlNode('AND name = #{name}');
        $node = new IfSqlNode('name', $contents);
        $context = new DynamicContext($this->configuration, ['name' => 'John']);

        $node->apply($context);
        $node->apply($context);

        $this->assertSame('AND name = #{name}AND name = #{name}', $context->getSql());
    }
}
