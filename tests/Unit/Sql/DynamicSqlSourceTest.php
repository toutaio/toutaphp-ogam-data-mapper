<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Sql;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Sql\DynamicSqlSource;
use Touta\Ogam\Sql\Node\IfSqlNode;
use Touta\Ogam\Sql\Node\MixedSqlNode;
use Touta\Ogam\Sql\Node\TextSqlNode;
use Touta\Ogam\Sql\SqlSource;

final class DynamicSqlSourceTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
    }

    public function testImplementsSqlSource(): void
    {
        $rootNode = new TextSqlNode('SELECT * FROM users');
        $source = new DynamicSqlSource($this->configuration, $rootNode);

        $this->assertInstanceOf(SqlSource::class, $source);
    }

    public function testGetRootNode(): void
    {
        $rootNode = new TextSqlNode('SELECT * FROM users');
        $source = new DynamicSqlSource($this->configuration, $rootNode);

        $this->assertSame($rootNode, $source->getRootNode());
    }

    public function testGetBoundSqlWithStaticContent(): void
    {
        $rootNode = new TextSqlNode('SELECT * FROM users');
        $source = new DynamicSqlSource($this->configuration, $rootNode);

        $boundSql = $source->getBoundSql(null);

        $this->assertInstanceOf(BoundSql::class, $boundSql);
        $this->assertStringContainsString('SELECT * FROM users', $boundSql->getSql());
    }

    public function testGetBoundSqlWithDynamicContent(): void
    {
        $rootNode = new MixedSqlNode([
            new TextSqlNode('SELECT * FROM users WHERE 1=1'),
            new IfSqlNode('name', new TextSqlNode(' AND name = #{name}')),
        ]);

        $source = new DynamicSqlSource($this->configuration, $rootNode);

        $boundSql = $source->getBoundSql(['name' => 'John']);

        $this->assertStringContainsString('SELECT * FROM users WHERE 1=1', $boundSql->getSql());
        $this->assertStringContainsString('AND name = ?', $boundSql->getSql());
    }

    public function testGetBoundSqlWithNullParameter(): void
    {
        $rootNode = new TextSqlNode('SELECT COUNT(*) FROM users');
        $source = new DynamicSqlSource($this->configuration, $rootNode);

        $boundSql = $source->getBoundSql(null);

        $this->assertInstanceOf(BoundSql::class, $boundSql);
        $this->assertStringContainsString('SELECT COUNT(*) FROM users', $boundSql->getSql());
    }

    public function testGetBoundSqlWithArrayParameter(): void
    {
        $rootNode = new MixedSqlNode([
            new TextSqlNode('SELECT * FROM users'),
            new IfSqlNode('id', new TextSqlNode(' WHERE id = #{id}')),
        ]);

        $source = new DynamicSqlSource($this->configuration, $rootNode);

        $boundSql = $source->getBoundSql(['id' => 1]);

        $this->assertStringContainsString('WHERE id = ?', $boundSql->getSql());
    }

    public function testGetBoundSqlWithObjectParameter(): void
    {
        $parameter = new class {
            public int $id = 42;

            public string $name = 'Test';
        };

        $rootNode = new MixedSqlNode([
            new TextSqlNode('SELECT * FROM users'),
            new IfSqlNode('id', new TextSqlNode(' WHERE id = #{id}')),
        ]);

        $source = new DynamicSqlSource($this->configuration, $rootNode);

        $boundSql = $source->getBoundSql($parameter);

        $this->assertStringContainsString('WHERE id = ?', $boundSql->getSql());
    }

    public function testGetBoundSqlWithConditionalFalse(): void
    {
        $rootNode = new MixedSqlNode([
            new TextSqlNode('SELECT * FROM users WHERE 1=1'),
            new IfSqlNode('name', new TextSqlNode(' AND name = #{name}')),
        ]);

        $source = new DynamicSqlSource($this->configuration, $rootNode);

        $boundSql = $source->getBoundSql(['name' => null]);

        $sql = $boundSql->getSql();
        $this->assertStringContainsString('SELECT * FROM users WHERE 1=1', $sql);
        $this->assertStringNotContainsString('AND name', $sql);
    }

    public function testGetBoundSqlMergesBindings(): void
    {
        $rootNode = new TextSqlNode('SELECT * FROM users WHERE id = #{id}');
        $source = new DynamicSqlSource($this->configuration, $rootNode);

        $boundSql = $source->getBoundSql(['id' => 123]);

        $this->assertInstanceOf(BoundSql::class, $boundSql);
    }

    public function testGetBoundSqlWithComplexDynamicSQL(): void
    {
        $rootNode = new MixedSqlNode([
            new TextSqlNode('SELECT * FROM users WHERE 1=1'),
            new IfSqlNode('name', new TextSqlNode(' AND name = #{name}')),
            new IfSqlNode('email', new TextSqlNode(' AND email = #{email}')),
        ]);

        $source = new DynamicSqlSource($this->configuration, $rootNode);

        $boundSql = $source->getBoundSql([
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $sql = $boundSql->getSql();
        $this->assertStringContainsString('AND name = ?', $sql);
        $this->assertStringContainsString('AND email = ?', $sql);
    }

    public function testGetBoundSqlPreservesParameterValues(): void
    {
        $rootNode = new TextSqlNode('SELECT * FROM users WHERE id = #{id} AND name = #{name}');
        $source = new DynamicSqlSource($this->configuration, $rootNode);

        $parameter = ['id' => 42, 'name' => 'Alice'];
        $boundSql = $source->getBoundSql($parameter);

        $this->assertInstanceOf(BoundSql::class, $boundSql);
        $this->assertStringContainsString('?', $boundSql->getSql());
    }

    public function testGetBoundSqlWithObjectContainingPrivateProperties(): void
    {
        $parameter = new class {
            private int $id = 99;

            private string $status = 'active';

            public function getId(): int
            {
                return $this->id;
            }

            public function getStatus(): string
            {
                return $this->status;
            }
        };

        $rootNode = new TextSqlNode('SELECT * FROM records');
        $source = new DynamicSqlSource($this->configuration, $rootNode);

        $boundSql = $source->getBoundSql($parameter);

        $this->assertInstanceOf(BoundSql::class, $boundSql);
    }

    public function testGetBoundSqlMultipleCalls(): void
    {
        $rootNode = new MixedSqlNode([
            new TextSqlNode('SELECT * FROM users'),
            new IfSqlNode('active', new TextSqlNode(' WHERE active = #{active}')),
        ]);

        $source = new DynamicSqlSource($this->configuration, $rootNode);

        $boundSql1 = $source->getBoundSql(['active' => true]);
        $boundSql2 = $source->getBoundSql(['active' => false]);

        $this->assertStringContainsString('WHERE active = ?', $boundSql1->getSql());
        $this->assertStringNotContainsString('WHERE active', $boundSql2->getSql());
    }
}
