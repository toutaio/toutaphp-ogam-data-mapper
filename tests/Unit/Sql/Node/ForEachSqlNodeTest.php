<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Sql\Node;

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\Sql\DynamicContext;
use Touta\Ogam\Sql\Node\ForEachSqlNode;
use Touta\Ogam\Sql\Node\SqlNode;
use Touta\Ogam\Sql\Node\TextSqlNode;

final class ForEachSqlNodeTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
    }

    public function testImplementsSqlNode(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents);

        $this->assertInstanceOf(SqlNode::class, $node);
    }

    public function testGetCollection(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents);

        $this->assertSame('list', $node->getCollection());
    }

    public function testGetItem(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents);

        $this->assertSame('item', $node->getItem());
    }

    public function testGetIndex(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', 'idx', $contents);

        $this->assertSame('idx', $node->getIndex());
    }

    public function testGetIndexReturnsNullWhenNotSet(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents);

        $this->assertNull($node->getIndex());
    }

    public function testGetContents(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents);

        $this->assertSame($contents, $node->getContents());
    }

    public function testGetOpen(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents, '(');

        $this->assertSame('(', $node->getOpen());
    }

    public function testGetClose(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents, '(', ')');

        $this->assertSame(')', $node->getClose());
    }

    public function testGetSeparator(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents, '(', ')', ',');

        $this->assertSame(',', $node->getSeparator());
    }

    public function testApplyReturnsFalseWhenCollectionIsEmpty(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents);
        $context = new DynamicContext($this->configuration, ['list' => []]);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyReturnsFalseWhenCollectionIsNotIterable(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents);
        $context = new DynamicContext($this->configuration, ['list' => 'not-iterable']);

        $result = $node->apply($context);

        $this->assertFalse($result);
    }

    public function testApplyReturnsTrueWithSingleItem(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents);
        $context = new DynamicContext($this->configuration, ['list' => [1]]);

        $result = $node->apply($context);

        $this->assertTrue($result);
    }

    public function testApplyWithSingleItem(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents, '(', ')', ',');
        $context = new DynamicContext($this->configuration, ['list' => [1]]);

        $node->apply($context);

        $sql = $context->getSql();
        $this->assertStringStartsWith('(', $sql);
        $this->assertStringEndsWith(')', $sql);
        $this->assertStringContainsString('__frch_item_', $sql);
    }

    public function testApplyWithMultipleItems(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents, '(', ')', ',');
        $context = new DynamicContext($this->configuration, ['list' => [1, 2, 3]]);

        $node->apply($context);

        $sql = $context->getSql();
        $this->assertStringStartsWith('(', $sql);
        $this->assertStringEndsWith(')', $sql);
        $this->assertSame(2, substr_count($sql, ','));
    }

    public function testApplyBindsItemValues(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents);
        $context = new DynamicContext($this->configuration, ['list' => [10, 20, 30]]);

        $node->apply($context);

        $bindings = $context->getBindings();
        $this->assertCount(3, $bindings);
        $this->assertContains(10, $bindings);
        $this->assertContains(20, $bindings);
        $this->assertContains(30, $bindings);
    }

    public function testApplyWithIndex(): void
    {
        $contents = new TextSqlNode('#{idx}:#{item}');
        $node = new ForEachSqlNode('list', 'item', 'idx', $contents, '', '', ',');
        $context = new DynamicContext($this->configuration, ['list' => ['a', 'b', 'c']]);

        $node->apply($context);

        $bindings = $context->getBindings();
        $this->assertCount(6, $bindings); // 3 items + 3 indices
    }

    public function testApplyWithAssociativeArray(): void
    {
        $contents = new TextSqlNode('#{idx}=#{item}');
        $node = new ForEachSqlNode('map', 'item', 'idx', $contents, '', '', ',');
        $context = new DynamicContext($this->configuration, [
            'map' => ['key1' => 'val1', 'key2' => 'val2'],
        ]);

        $node->apply($context);

        $bindings = $context->getBindings();
        $this->assertContains('val1', $bindings);
        $this->assertContains('val2', $bindings);
        $this->assertContains('key1', $bindings);
        $this->assertContains('key2', $bindings);
    }

    public function testApplyGeneratesUniquePlaceholders(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents, '', '', ',');
        $context = new DynamicContext($this->configuration, ['list' => [1, 2]]);

        $node->apply($context);

        $sql = $context->getSql();
        preg_match_all('/__frch_item_(\d+)/', $sql, $matches);
        $numbers = $matches[1];

        $this->assertCount(2, $numbers);
        $this->assertNotEquals($numbers[0], $numbers[1]);
    }

    public function testApplyWithOpenCloseButNoItems(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents, '(', ')', ',');
        $context = new DynamicContext($this->configuration, ['list' => []]);

        $node->apply($context);

        $this->assertSame('', $context->getSql());
    }

    public function testApplyWithNoSeparator(): void
    {
        $contents = new TextSqlNode('#{item} ');
        $node = new ForEachSqlNode('list', 'item', null, $contents);
        $context = new DynamicContext($this->configuration, ['list' => [1, 2, 3]]);

        $node->apply($context);

        $sql = $context->getSql();
        $this->assertStringNotContainsString(',', $sql);
    }

    public function testApplyWithIterator(): void
    {
        $contents = new TextSqlNode('#{item}');
        $iterator = new ArrayIterator([1, 2, 3]);
        $node = new ForEachSqlNode('list', 'item', null, $contents, '(', ')', ',');
        $context = new DynamicContext($this->configuration, ['list' => $iterator]);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $sql = $context->getSql();
        $this->assertStringStartsWith('(', $sql);
        $this->assertStringEndsWith(')', $sql);
    }

    public function testApplyPreservesOtherParameters(): void
    {
        $contents = new TextSqlNode('#{item}');
        $node = new ForEachSqlNode('list', 'item', null, $contents);
        $context = new DynamicContext($this->configuration, [
            'list' => [1, 2],
            'name' => 'test',
        ]);

        $node->apply($context);

        $bindings = $context->getBindings();
        $this->assertArrayNotHasKey('name', $bindings);
    }

    public function testApplyWithComplexContent(): void
    {
        $contents = new TextSqlNode('(#{item.id}, #{item.name})');
        $node = new ForEachSqlNode('users', 'item', null, $contents, '', '', ',');
        $context = new DynamicContext($this->configuration, [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ]);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $bindings = $context->getBindings();
        $this->assertCount(2, $bindings);
    }
}
