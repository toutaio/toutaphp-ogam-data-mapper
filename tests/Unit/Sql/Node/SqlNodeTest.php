<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Sql\Node;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\Sql\DynamicContext;
use Touta\Ogam\Sql\Node\ChooseSqlNode;
use Touta\Ogam\Sql\Node\ForEachSqlNode;
use Touta\Ogam\Sql\Node\IfSqlNode;
use Touta\Ogam\Sql\Node\MixedSqlNode;
use Touta\Ogam\Sql\Node\SetSqlNode;
use Touta\Ogam\Sql\Node\TextSqlNode;
use Touta\Ogam\Sql\Node\TrimSqlNode;
use Touta\Ogam\Sql\Node\WhereSqlNode;

final class SqlNodeTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
    }

    public function testTextSqlNode(): void
    {
        $node = new TextSqlNode('SELECT * FROM users');
        $context = new DynamicContext($this->configuration, []);

        $node->apply($context);

        $this->assertSame('SELECT * FROM users', $context->getSql());
    }

    public function testMixedSqlNode(): void
    {
        $node = new MixedSqlNode([
            new TextSqlNode('SELECT * '),
            new TextSqlNode('FROM users'),
        ]);
        $context = new DynamicContext($this->configuration, []);

        $node->apply($context);

        $this->assertSame('SELECT * FROM users', $context->getSql());
    }

    public function testIfSqlNodeTrue(): void
    {
        $node = new IfSqlNode('name', new TextSqlNode('AND name = #{name}'));
        $context = new DynamicContext($this->configuration, ['name' => 'John']);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('AND name = #{name}', $context->getSql());
    }

    public function testIfSqlNodeFalse(): void
    {
        $node = new IfSqlNode('name', new TextSqlNode('AND name = #{name}'));
        $context = new DynamicContext($this->configuration, ['name' => '']);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testChooseSqlNode(): void
    {
        $node = new ChooseSqlNode(
            [
                new IfSqlNode('status', new TextSqlNode('status = #{status}')),
                new IfSqlNode('type', new TextSqlNode('type = #{type}')),
            ],
            new TextSqlNode('1 = 1'),
        );

        // First when matches
        $context = new DynamicContext($this->configuration, ['status' => 'active']);
        $node->apply($context);
        $this->assertSame('status = #{status}', $context->getSql());

        // Second when matches
        $context = new DynamicContext($this->configuration, ['type' => 'admin']);
        $node->apply($context);
        $this->assertSame('type = #{type}', $context->getSql());

        // Otherwise
        $context = new DynamicContext($this->configuration, []);
        $node->apply($context);
        $this->assertSame('1 = 1', $context->getSql());
    }

    public function testForEachSqlNode(): void
    {
        $node = new ForEachSqlNode(
            'ids',
            'id',
            null,
            new TextSqlNode('#{id}'),
            '(',
            ')',
            ', ',
        );
        $context = new DynamicContext($this->configuration, ['ids' => [1, 2, 3]]);

        $node->apply($context);

        // ForEach generates unique parameter names for each iteration
        $this->assertSame('(#{__frch_id_0}, #{__frch_id_1}, #{__frch_id_2})', $context->getSql());

        // Verify bindings were created with actual values
        $bindings = $context->getBindings();
        $this->assertSame(1, $bindings['__frch_id_0']);
        $this->assertSame(2, $bindings['__frch_id_1']);
        $this->assertSame(3, $bindings['__frch_id_2']);
    }

    public function testForEachSqlNodeWithIndex(): void
    {
        $node = new ForEachSqlNode(
            'items',
            'item',
            'idx',
            new TextSqlNode('#{item}'),
            '',
            '',
            ',',
        );
        $context = new DynamicContext($this->configuration, ['items' => ['a', 'b']]);

        $node->apply($context);

        // ForEach generates unique parameter names for each iteration
        $this->assertSame('#{__frch_item_0},#{__frch_item_1}', $context->getSql());

        // Verify bindings
        $bindings = $context->getBindings();
        $this->assertSame('a', $bindings['__frch_item_0']);
        $this->assertSame('b', $bindings['__frch_item_1']);
    }

    public function testForEachSqlNodeEmpty(): void
    {
        $node = new ForEachSqlNode(
            'ids',
            'id',
            null,
            new TextSqlNode('#{id}'),
            '(',
            ')',
            ', ',
        );
        $context = new DynamicContext($this->configuration, ['ids' => []]);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testWhereSqlNode(): void
    {
        $node = new WhereSqlNode(
            new MixedSqlNode([
                new IfSqlNode('name', new TextSqlNode(' AND name = #{name} ')),
                new IfSqlNode('age', new TextSqlNode(' AND age > #{age} ')),
            ]),
        );

        // Both conditions
        $context = new DynamicContext($this->configuration, ['name' => 'John', 'age' => 18]);
        $node->apply($context);
        $this->assertStringContainsString('WHERE', $context->getSql());
        $this->assertStringContainsString('name = #{name}', $context->getSql());

        // No conditions
        $context = new DynamicContext($this->configuration, []);
        $result = $node->apply($context);
        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testSetSqlNode(): void
    {
        $node = new SetSqlNode(
            new MixedSqlNode([
                new IfSqlNode('name', new TextSqlNode(' name = #{name}, ')),
                new IfSqlNode('email', new TextSqlNode(' email = #{email}, ')),
            ]),
        );

        $context = new DynamicContext($this->configuration, ['name' => 'John', 'email' => 'john@example.com']);
        $node->apply($context);

        $this->assertStringContainsString('SET', $context->getSql());
        $this->assertStringContainsString('name = #{name}', $context->getSql());
    }

    public function testTrimSqlNode(): void
    {
        $node = new TrimSqlNode(
            new TextSqlNode('AND name = #{name}'),
            'WHERE ',
            'AND |OR ',
            '',
            '',
        );

        $context = new DynamicContext($this->configuration, ['name' => 'John']);
        $node->apply($context);

        $this->assertSame('WHERE name = #{name}', $context->getSql());
    }
}
