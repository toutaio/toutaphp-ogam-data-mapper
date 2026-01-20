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
use Touta\Ogam\Sql\Node\TrimSqlNode;

final class TrimSqlNodeTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
    }

    public function testImplementsSqlNode(): void
    {
        $contents = new TextSqlNode('content');
        $node = new TrimSqlNode($contents);

        $this->assertInstanceOf(SqlNode::class, $node);
    }

    public function testGetContents(): void
    {
        $contents = new TextSqlNode('content');
        $node = new TrimSqlNode($contents);

        $this->assertSame($contents, $node->getContents());
    }

    public function testGetPrefix(): void
    {
        $contents = new TextSqlNode('content');
        $node = new TrimSqlNode($contents, 'WHERE ', '', '', '');

        $this->assertSame('WHERE ', $node->getPrefix());
    }

    public function testGetSuffix(): void
    {
        $contents = new TextSqlNode('content');
        $node = new TrimSqlNode($contents, '', '', ' LIMIT 10', '');

        $this->assertSame(' LIMIT 10', $node->getSuffix());
    }

    public function testApplyWithEmptyContent(): void
    {
        $contents = new TextSqlNode('');
        $node = new TrimSqlNode($contents, 'WHERE ', '', '', '');
        $context = new DynamicContext($this->configuration, null);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyWithWhitespaceOnlyContent(): void
    {
        $contents = new TextSqlNode('   ');
        $node = new TrimSqlNode($contents, 'WHERE ', '', '', '');
        $context = new DynamicContext($this->configuration, null);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyAddsPrefix(): void
    {
        $contents = new TextSqlNode('id = 1');
        $node = new TrimSqlNode($contents, 'WHERE ', '', '', '');
        $context = new DynamicContext($this->configuration, null);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('WHERE id = 1', $context->getSql());
    }

    public function testApplyAddsSuffix(): void
    {
        $contents = new TextSqlNode('id = 1');
        $node = new TrimSqlNode($contents, '', '', ' LIMIT 10', '');
        $context = new DynamicContext($this->configuration, null);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('id = 1 LIMIT 10', $context->getSql());
    }

    public function testApplyAddsPrefixAndSuffix(): void
    {
        $contents = new TextSqlNode('id = 1');
        $node = new TrimSqlNode($contents, 'WHERE ', '', ' LIMIT 10', '');
        $context = new DynamicContext($this->configuration, null);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('WHERE id = 1 LIMIT 10', $context->getSql());
    }

    public function testApplyRemovesPrefixOverride(): void
    {
        $contents = new TextSqlNode('AND id = 1');
        $node = new TrimSqlNode($contents, 'WHERE ', 'AND ', '', '');
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame('WHERE id = 1', $context->getSql());
    }

    public function testApplyRemovesPrefixOverrideCaseInsensitive(): void
    {
        $contents = new TextSqlNode('and id = 1');
        $node = new TrimSqlNode($contents, 'WHERE ', 'AND ', '', '');
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame('WHERE id = 1', $context->getSql());
    }

    public function testApplyRemovesOrPrefixOverride(): void
    {
        $contents = new TextSqlNode('OR status = "active"');
        $node = new TrimSqlNode($contents, 'WHERE ', 'AND |OR ', '', '');
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame('WHERE status = "active"', $context->getSql());
    }

    public function testApplyRemovesMultiplePrefixOverridesFirstMatch(): void
    {
        $contents1 = new TextSqlNode('AND id = 1');
        $node1 = new TrimSqlNode($contents1, 'WHERE ', 'AND |OR ', '', '');
        $context1 = new DynamicContext($this->configuration, null);

        $node1->apply($context1);
        $this->assertSame('WHERE id = 1', $context1->getSql());

        $contents2 = new TextSqlNode('OR status = "active"');
        $node2 = new TrimSqlNode($contents2, 'WHERE ', 'AND |OR ', '', '');
        $context2 = new DynamicContext($this->configuration, null);

        $node2->apply($context2);
        $this->assertSame('WHERE status = "active"', $context2->getSql());
    }

    public function testApplyRemovesSuffixOverride(): void
    {
        $contents = new TextSqlNode('name = #{name},');
        $node = new TrimSqlNode($contents, 'SET ', '', '', ',');
        $context = new DynamicContext($this->configuration, ['name' => 'John']);

        $node->apply($context);

        $this->assertSame('SET name = #{name}', $context->getSql());
    }

    public function testApplyRemovesSuffixOverrideCaseInsensitive(): void
    {
        $contents = new TextSqlNode('content,');
        $node = new TrimSqlNode($contents, '', '', '', ',');
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame('content', $context->getSql());
    }

    public function testApplyWithPrefixAndSuffixOverrides(): void
    {
        $contents = new TextSqlNode('AND id = 1,');
        $node = new TrimSqlNode($contents, 'WHERE ', 'AND ', '', ',');
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame('WHERE id = 1', $context->getSql());
    }

    public function testApplyTrimsWhitespaceBeforeCheckingOverrides(): void
    {
        $contents = new TextSqlNode('  AND id = 1  ');
        $node = new TrimSqlNode($contents, 'WHERE ', 'AND ', '', '');
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame('WHERE id = 1', $context->getSql());
    }

    public function testApplyWithComplexPrefixOverrides(): void
    {
        $contents = new TextSqlNode('AND name = #{name} OR email = #{email}');
        $node = new TrimSqlNode($contents, 'WHERE ', 'AND |OR |WHERE ', '', '');
        $context = new DynamicContext($this->configuration, ['name' => 'John', 'email' => 'john@example.com']);

        $node->apply($context);

        $this->assertSame('WHERE name = #{name} OR email = #{email}', $context->getSql());
    }

    public function testApplyWithConditionalContent(): void
    {
        $ifNode = new IfSqlNode('name', new TextSqlNode('AND name = #{name}'));
        $node = new TrimSqlNode($ifNode, 'WHERE ', 'AND |OR ', '', '');
        $context = new DynamicContext($this->configuration, ['name' => 'John']);

        $node->apply($context);

        $this->assertSame('WHERE name = #{name}', $context->getSql());
    }

    public function testApplyWithConditionalContentThatEvaluatesToFalse(): void
    {
        $ifNode = new IfSqlNode('name', new TextSqlNode('AND name = #{name}'));
        $node = new TrimSqlNode($ifNode, 'WHERE ', 'AND |OR ', '', '');
        $context = new DynamicContext($this->configuration, ['age' => 25]);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyWithMixedSqlNode(): void
    {
        $mixed = new MixedSqlNode([
            new IfSqlNode('name', new TextSqlNode('AND name = #{name}')),
            new IfSqlNode('age', new TextSqlNode(' AND age = #{age}')),
        ]);

        $node = new TrimSqlNode($mixed, 'WHERE ', 'AND |OR ', '', '');
        $context = new DynamicContext($this->configuration, [
            'name' => 'John',
            'age' => 25,
        ]);

        $node->apply($context);

        $this->assertSame('WHERE name = #{name} AND age = #{age}', $context->getSql());
    }

    public function testApplyAppendsToExistingSql(): void
    {
        $contents = new TextSqlNode('AND id = 1');
        $node = new TrimSqlNode($contents, 'WHERE ', 'AND ', '', '');
        $context = new DynamicContext($this->configuration, null);

        $context->appendSql('SELECT * FROM users ');
        $node->apply($context);

        $this->assertSame('SELECT * FROM users WHERE id = 1', $context->getSql());
    }

    public function testApplyWithEmptyPrefix(): void
    {
        $contents = new TextSqlNode('content');
        $node = new TrimSqlNode($contents, '', '', '', '');
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame('content', $context->getSql());
    }

    public function testApplyWithEmptyPrefixOverrides(): void
    {
        $contents = new TextSqlNode('AND content');
        $node = new TrimSqlNode($contents, 'PREFIX ', '', '', '');
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame('PREFIX AND content', $context->getSql());
    }

    public function testApplyWithEmptySuffixOverrides(): void
    {
        $contents = new TextSqlNode('content,');
        $node = new TrimSqlNode($contents, '', '', ' SUFFIX', '');
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame('content, SUFFIX', $context->getSql());
    }

    public function testApplyDoesNotModifyContentWithoutMatchingOverrides(): void
    {
        $contents = new TextSqlNode('WHERE id = 1');
        $node = new TrimSqlNode($contents, '', 'AND |OR ', '', '');
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame('WHERE id = 1', $context->getSql());
    }

    public function testApplyMultipleTimes(): void
    {
        $contents = new TextSqlNode('AND id = 1');
        $node = new TrimSqlNode($contents, 'WHERE ', 'AND ', '', '');
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);
        $node->apply($context);

        $this->assertSame('WHERE id = 1WHERE id = 1', $context->getSql());
    }

    public function testApplyRemovesPrefixOverrideWithTrailingSpace(): void
    {
        $contents = new TextSqlNode('AND id = 1');
        $node = new TrimSqlNode($contents, 'WHERE ', 'AND |OR ', '', '');
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame('WHERE id = 1', $context->getSql());
    }

    public function testApplyRemovesSuffixOverrideWithoutTrailingSpace(): void
    {
        $contents = new TextSqlNode('name = #{name},');
        $node = new TrimSqlNode($contents, '', '', '', ',');
        $context = new DynamicContext($this->configuration, ['name' => 'John']);

        $node->apply($context);

        $this->assertSame('name = #{name}', $context->getSql());
    }
}
