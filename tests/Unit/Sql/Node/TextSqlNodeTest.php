<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Sql\Node;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\Sql\DynamicContext;
use Touta\Ogam\Sql\Node\SqlNode;
use Touta\Ogam\Sql\Node\TextSqlNode;

final class TextSqlNodeTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
    }

    public function testImplementsSqlNode(): void
    {
        $node = new TextSqlNode('SELECT * FROM users');

        $this->assertInstanceOf(SqlNode::class, $node);
    }

    public function testGetText(): void
    {
        $node = new TextSqlNode('SELECT * FROM users');

        $this->assertSame('SELECT * FROM users', $node->getText());
    }

    public function testApplyReturnsTrue(): void
    {
        $node = new TextSqlNode('SELECT * FROM users');
        $context = new DynamicContext($this->configuration, null);

        $result = $node->apply($context);

        $this->assertTrue($result);
    }

    public function testApplyAppendsSqlToContext(): void
    {
        $node = new TextSqlNode('SELECT * FROM users');
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame('SELECT * FROM users', $context->getSql());
    }

    public function testApplyAppendsToExistingSql(): void
    {
        $node = new TextSqlNode(' WHERE id = #{id}');
        $context = new DynamicContext($this->configuration, ['id' => 1]);

        $context->appendSql('SELECT * FROM users');
        $node->apply($context);

        $this->assertSame('SELECT * FROM users WHERE id = #{id}', $context->getSql());
    }

    public function testApplyWithEmptyText(): void
    {
        $node = new TextSqlNode('');
        $context = new DynamicContext($this->configuration, null);

        $result = $node->apply($context);

        $this->assertTrue($result);
        $this->assertSame('', $context->getSql());
    }

    public function testApplyWithMultilineText(): void
    {
        $sql = "SELECT *\nFROM users\nWHERE active = 1";
        $node = new TextSqlNode($sql);
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame($sql, $context->getSql());
    }

    public function testApplyWithSpecialCharacters(): void
    {
        $sql = "SELECT * FROM users WHERE name LIKE '%test%' AND status = 'active'";
        $node = new TextSqlNode($sql);
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);

        $this->assertSame($sql, $context->getSql());
    }

    public function testMultipleApplyCalls(): void
    {
        $node = new TextSqlNode('SELECT * ');
        $context = new DynamicContext($this->configuration, null);

        $node->apply($context);
        $node->apply($context);

        $this->assertSame('SELECT * SELECT * ', $context->getSql());
    }
}
