<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Sql\Node;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\Sql\DynamicContext;
use Touta\Ogam\Sql\Node\IfSqlNode;
use Touta\Ogam\Sql\Node\MixedSqlNode;
use Touta\Ogam\Sql\Node\SqlNode;
use Touta\Ogam\Sql\Node\TextSqlNode;

final class MixedSqlNodeTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
    }

    public function testImplementsSqlNode(): void
    {
        $node = new MixedSqlNode([]);

        $this->assertInstanceOf(SqlNode::class, $node);
    }

    public function testGetContents(): void
    {
        $child1 = new TextSqlNode('SELECT * FROM users');
        $child2 = new TextSqlNode(' WHERE id = #{id}');
        $contents = [$child1, $child2];

        $node = new MixedSqlNode($contents);

        $this->assertSame($contents, $node->getContents());
    }

    public function testApplyWithEmptyContents(): void
    {
        $node = new MixedSqlNode([]);
        $context = new DynamicContext($this->configuration, null);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyWithSingleTextNode(): void
    {
        $child = new TextSqlNode('SELECT * FROM users');
        $node = new MixedSqlNode([$child]);
        $context = new DynamicContext($this->configuration, null);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('SELECT * FROM users', $context->getSql());
    }

    public function testApplyWithMultipleTextNodes(): void
    {
        $child1 = new TextSqlNode('SELECT * FROM users');
        $child2 = new TextSqlNode(' WHERE active = 1');
        $child3 = new TextSqlNode(' ORDER BY name');

        $node = new MixedSqlNode([$child1, $child2, $child3]);
        $context = new DynamicContext($this->configuration, null);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('SELECT * FROM users WHERE active = 1 ORDER BY name', $context->getSql());
    }

    public function testApplyWithIfNodeThatEvaluatesToTrue(): void
    {
        $text = new TextSqlNode('SELECT * FROM users WHERE 1=1');
        $ifNode = new IfSqlNode('name', new TextSqlNode(' AND name = #{name}'));

        $node = new MixedSqlNode([$text, $ifNode]);
        $context = new DynamicContext($this->configuration, ['name' => 'John']);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('SELECT * FROM users WHERE 1=1 AND name = #{name}', $context->getSql());
    }

    public function testApplyWithIfNodeThatEvaluatesToFalse(): void
    {
        $text = new TextSqlNode('SELECT * FROM users WHERE 1=1');
        $ifNode = new IfSqlNode('name', new TextSqlNode(' AND name = #{name}'));

        $node = new MixedSqlNode([$text, $ifNode]);
        $context = new DynamicContext($this->configuration, ['age' => 25]);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('SELECT * FROM users WHERE 1=1', $context->getSql());
    }

    public function testApplyReturnsTrueIfAnyChildReturnsTrue(): void
    {
        $ifNode1 = new IfSqlNode('name', new TextSqlNode('name condition'));
        $ifNode2 = new IfSqlNode('age', new TextSqlNode('age condition'));

        $node = new MixedSqlNode([$ifNode1, $ifNode2]);
        $context = new DynamicContext($this->configuration, ['age' => 25]);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('age condition', $context->getSql());
    }

    public function testApplyReturnsFalseIfAllChildrenReturnFalse(): void
    {
        $ifNode1 = new IfSqlNode('name', new TextSqlNode('name condition'));
        $ifNode2 = new IfSqlNode('email', new TextSqlNode('email condition'));

        $node = new MixedSqlNode([$ifNode1, $ifNode2]);
        $context = new DynamicContext($this->configuration, ['age' => 25]);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyWithMixedNodeTypes(): void
    {
        $text1 = new TextSqlNode('SELECT * FROM users WHERE 1=1');
        $ifNode1 = new IfSqlNode('name', new TextSqlNode(' AND name = #{name}'));
        $ifNode2 = new IfSqlNode('age', new TextSqlNode(' AND age > #{age}'));
        $text2 = new TextSqlNode(' ORDER BY id');

        $node = new MixedSqlNode([$text1, $ifNode1, $ifNode2, $text2]);
        $context = new DynamicContext($this->configuration, [
            'name' => 'John',
            'age' => 25,
        ]);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame(
            'SELECT * FROM users WHERE 1=1 AND name = #{name} AND age > #{age} ORDER BY id',
            $context->getSql()
        );
    }

    public function testApplyProcessesAllChildren(): void
    {
        $child1 = new TextSqlNode('FIRST');
        $child2 = new TextSqlNode('SECOND');
        $child3 = new TextSqlNode('THIRD');

        $node = new MixedSqlNode([$child1, $child2, $child3]);
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame('FIRSTSECONDTHIRD', $context->getSql());
    }

    public function testApplyWithNestedMixedNodes(): void
    {
        $innerNode = new MixedSqlNode([
            new TextSqlNode('INNER1'),
            new TextSqlNode('INNER2'),
        ]);

        $node = new MixedSqlNode([
            new TextSqlNode('OUTER1'),
            $innerNode,
            new TextSqlNode('OUTER2'),
        ]);

        $context = new DynamicContext($this->configuration, null);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('OUTER1INNER1INNER2OUTER2', $context->getSql());
    }

    public function testApplyAppendsToExistingSql(): void
    {
        $child = new TextSqlNode('NEW CONTENT');
        $node = new MixedSqlNode([$child]);
        $context = new DynamicContext($this->configuration, null);

        $context->appendSql('EXISTING ');
        $node->apply($context);

        $this->assertSame('EXISTING NEW CONTENT', $context->getSql());
    }

    public function testApplyPreservesChildOrder(): void
    {
        $children = [];
        for ($i = 1; $i <= 5; $i++) {
            $children[] = new TextSqlNode("PART{$i}");
        }

        $node = new MixedSqlNode($children);
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame('PART1PART2PART3PART4PART5', $context->getSql());
    }

    public function testApplyMultipleTimes(): void
    {
        $child = new TextSqlNode('REPEATED');
        $node = new MixedSqlNode([$child]);
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);
        $node->apply($context);

        $this->assertSame('REPEATEDREPEATED', $context->getSql());
    }

    public function testApplyWithComplexConditionalLogic(): void
    {
        $text1 = new TextSqlNode('SELECT * FROM users WHERE 1=1');
        $ifNode1 = new IfSqlNode('filters.name', new TextSqlNode(' AND name = #{filters.name}'));
        $ifNode2 = new IfSqlNode('filters.active', new TextSqlNode(' AND active = 1'));
        $ifNode3 = new IfSqlNode('filters.age', new TextSqlNode(' AND age > #{filters.age}'));

        $node = new MixedSqlNode([$text1, $ifNode1, $ifNode2, $ifNode3]);
        $context = new DynamicContext($this->configuration, [
            'filters' => [
                'name' => null,
                'active' => true,
                'age' => 18,
            ],
        ]);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame(
            'SELECT * FROM users WHERE 1=1 AND active = 1 AND age > #{filters.age}',
            $context->getSql()
        );
    }
}
