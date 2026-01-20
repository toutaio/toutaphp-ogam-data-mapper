<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Sql\Node;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\Sql\DynamicContext;
use Touta\Ogam\Sql\Node\ChooseSqlNode;
use Touta\Ogam\Sql\Node\IfSqlNode;
use Touta\Ogam\Sql\Node\SqlNode;
use Touta\Ogam\Sql\Node\TextSqlNode;

final class ChooseSqlNodeTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
    }

    public function testImplementsSqlNode(): void
    {
        $node = new ChooseSqlNode([]);

        $this->assertInstanceOf(SqlNode::class, $node);
    }

    public function testGetWhenNodes(): void
    {
        $when1 = new IfSqlNode('status == "active"', new TextSqlNode('AND active = 1'));
        $when2 = new IfSqlNode('status == "inactive"', new TextSqlNode('AND active = 0'));
        $whenNodes = [$when1, $when2];

        $node = new ChooseSqlNode($whenNodes);

        $this->assertSame($whenNodes, $node->getWhenNodes());
    }

    public function testGetOtherwise(): void
    {
        $otherwise = new TextSqlNode('AND 1=1');
        $node = new ChooseSqlNode([], $otherwise);

        $this->assertSame($otherwise, $node->getOtherwise());
    }

    public function testGetOtherwiseReturnsNullWhenNotProvided(): void
    {
        $node = new ChooseSqlNode([]);

        $this->assertNull($node->getOtherwise());
    }

    public function testApplyReturnsTrueWhenFirstWhenMatches(): void
    {
        $when1 = new IfSqlNode('status', new TextSqlNode('AND active = 1'));
        $when2 = new IfSqlNode('name', new TextSqlNode('AND name = #{name}'));

        $node = new ChooseSqlNode([$when1, $when2]);
        $context = new DynamicContext($this->configuration, ['status' => 'active']);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('AND active = 1', $context->getSql());
    }

    public function testApplyReturnsTrueWhenSecondWhenMatches(): void
    {
        $when1 = new IfSqlNode('status', new TextSqlNode('AND active = 1'));
        $when2 = new IfSqlNode('name', new TextSqlNode('AND name = #{name}'));

        $node = new ChooseSqlNode([$when1, $when2]);
        $context = new DynamicContext($this->configuration, ['name' => 'John']);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('AND name = #{name}', $context->getSql());
    }

    public function testApplyStopsAtFirstMatch(): void
    {
        $when1 = new IfSqlNode('status', new TextSqlNode('FIRST'));
        $when2 = new IfSqlNode('status', new TextSqlNode('SECOND'));

        $node = new ChooseSqlNode([$when1, $when2]);
        $context = new DynamicContext($this->configuration, ['status' => 'active']);

        $node->apply($context);

        $this->assertSame('FIRST', $context->getSql());
    }

    public function testApplyExecutesOtherwiseWhenNoWhenMatches(): void
    {
        $when1 = new IfSqlNode('status', new TextSqlNode('AND active = 1'));
        $when2 = new IfSqlNode('name', new TextSqlNode('AND name = #{name}'));
        $otherwise = new TextSqlNode('AND 1=1');

        $node = new ChooseSqlNode([$when1, $when2], $otherwise);
        $context = new DynamicContext($this->configuration, ['age' => 25]);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('AND 1=1', $context->getSql());
    }

    public function testApplyReturnsFalseWhenNoWhenMatchesAndNoOtherwise(): void
    {
        $when1 = new IfSqlNode('status', new TextSqlNode('AND active = 1'));
        $when2 = new IfSqlNode('name', new TextSqlNode('AND name = #{name}'));

        $node = new ChooseSqlNode([$when1, $when2]);
        $context = new DynamicContext($this->configuration, ['age' => 25]);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyWithEmptyWhenNodesExecutesOtherwise(): void
    {
        $otherwise = new TextSqlNode('DEFAULT CONDITION');

        $node = new ChooseSqlNode([], $otherwise);
        $context = new DynamicContext($this->configuration, null);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('DEFAULT CONDITION', $context->getSql());
    }

    public function testApplyWithEmptyWhenNodesAndNoOtherwise(): void
    {
        $node = new ChooseSqlNode([]);
        $context = new DynamicContext($this->configuration, null);

        $result = $node->apply($context);

        $this->assertFalse($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyDoesNotExecuteOtherwiseWhenWhenMatches(): void
    {
        $when = new IfSqlNode('status', new TextSqlNode('WHEN MATCHED'));
        $otherwise = new TextSqlNode('OTHERWISE');

        $node = new ChooseSqlNode([$when], $otherwise);
        $context = new DynamicContext($this->configuration, ['status' => 'active']);

        $node->apply($context);

        $this->assertSame('WHEN MATCHED', $context->getSql());
    }

    public function testApplyWithMultipleWhenNodesOnlyFirstMatches(): void
    {
        $when1 = new IfSqlNode('age', new TextSqlNode('AGE CONDITION'));
        $when2 = new IfSqlNode('name', new TextSqlNode('NAME CONDITION'));
        $when3 = new IfSqlNode('email', new TextSqlNode('EMAIL CONDITION'));

        $node = new ChooseSqlNode([$when1, $when2, $when3]);
        $context = new DynamicContext($this->configuration, [
            'age' => 25,
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $node->apply($context);

        $this->assertSame('AGE CONDITION', $context->getSql());
    }

    public function testApplyWithComplexNestedConditions(): void
    {
        $when1 = new IfSqlNode('user.status', new TextSqlNode('USER STATUS'));
        $when2 = new IfSqlNode('admin.level', new TextSqlNode('ADMIN LEVEL'));

        $node = new ChooseSqlNode([$when1, $when2]);
        $context = new DynamicContext($this->configuration, [
            'user' => ['status' => 'active'],
            'admin' => ['level' => null],
        ]);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('USER STATUS', $context->getSql());
    }

    public function testApplyAppendsToExistingSql(): void
    {
        $when = new IfSqlNode('status', new TextSqlNode('AND active = 1'));

        $node = new ChooseSqlNode([$when]);
        $context = new DynamicContext($this->configuration, ['status' => 'active']);

        $context->appendSql('SELECT * FROM users WHERE 1=1 ');
        $node->apply($context);

        $this->assertSame('SELECT * FROM users WHERE 1=1 AND active = 1', $context->getSql());
    }

    public function testApplyMultipleTimes(): void
    {
        $when = new IfSqlNode('status', new TextSqlNode('MATCHED'));

        $node = new ChooseSqlNode([$when]);
        $context = new DynamicContext($this->configuration, ['status' => 'active']);

        $node->apply($context);
        $node->apply($context);

        $this->assertSame('MATCHEDMATCHED', $context->getSql());
    }
}
