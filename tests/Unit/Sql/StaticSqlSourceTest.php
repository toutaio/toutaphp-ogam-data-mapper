<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Sql;

use PHPUnit\Framework\TestCase;
use stdClass;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\ParameterMapping;
use Touta\Ogam\Sql\SqlSource;
use Touta\Ogam\Sql\StaticSqlSource;

final class StaticSqlSourceTest extends TestCase
{
    public function testImplementsSqlSource(): void
    {
        $source = new StaticSqlSource('SELECT * FROM users');

        $this->assertInstanceOf(SqlSource::class, $source);
    }

    public function testGetSql(): void
    {
        $source = new StaticSqlSource('SELECT * FROM users WHERE id = ?');

        $this->assertSame('SELECT * FROM users WHERE id = ?', $source->getSql());
    }

    public function testGetParameterMappingsDefault(): void
    {
        $source = new StaticSqlSource('SELECT * FROM users');

        $this->assertSame([], $source->getParameterMappings());
    }

    public function testGetParameterMappingsCustom(): void
    {
        $mapping = new ParameterMapping('id', 'int');
        $source = new StaticSqlSource('SELECT * FROM users WHERE id = ?', [$mapping]);

        $this->assertSame([$mapping], $source->getParameterMappings());
    }

    public function testGetBoundSqlReturnsBoundSql(): void
    {
        $source = new StaticSqlSource('SELECT * FROM users');

        $boundSql = $source->getBoundSql(null);

        $this->assertInstanceOf(BoundSql::class, $boundSql);
    }

    public function testGetBoundSqlContainsSql(): void
    {
        $source = new StaticSqlSource('SELECT * FROM users WHERE id = ?');

        $boundSql = $source->getBoundSql(null);

        $this->assertSame('SELECT * FROM users WHERE id = ?', $boundSql->getSql());
    }

    public function testGetBoundSqlContainsParameterMappings(): void
    {
        $mapping = new ParameterMapping('id', 'int');
        $source = new StaticSqlSource('SELECT * FROM users WHERE id = ?', [$mapping]);

        $boundSql = $source->getBoundSql(null);

        $this->assertSame([$mapping], $boundSql->getParameterMappings());
    }

    public function testGetBoundSqlWithArrayParameter(): void
    {
        $source = new StaticSqlSource('SELECT * FROM users WHERE id = ?');

        $boundSql = $source->getBoundSql(['id' => 1]);

        $this->assertInstanceOf(BoundSql::class, $boundSql);
    }

    public function testGetBoundSqlWithObjectParameter(): void
    {
        $source = new StaticSqlSource('SELECT * FROM users WHERE id = ?');

        $param = new stdClass();
        $param->id = 1;
        $boundSql = $source->getBoundSql($param);

        $this->assertInstanceOf(BoundSql::class, $boundSql);
    }

    public function testGetBoundSqlAlwaysReturnsSameSql(): void
    {
        $source = new StaticSqlSource('SELECT * FROM users');

        $boundSql1 = $source->getBoundSql(['param' => 1]);
        $boundSql2 = $source->getBoundSql(['param' => 2]);

        $this->assertSame($boundSql1->getSql(), $boundSql2->getSql());
    }

    public function testMultipleParameterMappings(): void
    {
        $mappings = [
            new ParameterMapping('name', 'string'),
            new ParameterMapping('status', 'string'),
            new ParameterMapping('limit', 'int'),
        ];

        $source = new StaticSqlSource(
            'SELECT * FROM users WHERE name = ? AND status = ? LIMIT ?',
            $mappings,
        );

        $this->assertCount(3, $source->getParameterMappings());
        $this->assertSame($mappings, $source->getParameterMappings());
    }

    public function testComplexSql(): void
    {
        $sql = <<<'SQL'
            SELECT u.id, u.name, COUNT(o.id) as order_count
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id
            WHERE u.status = ?
            GROUP BY u.id, u.name
            HAVING COUNT(o.id) > ?
            ORDER BY order_count DESC
            LIMIT ?
            SQL;

        $source = new StaticSqlSource($sql);

        $this->assertSame($sql, $source->getSql());
        $this->assertSame($sql, $source->getBoundSql(null)->getSql());
    }
}
