<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Sql;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Configuration;
use Touta\Ogam\Mapping\ParameterMode;
use Touta\Ogam\Sql\SqlSourceBuilder;

final class SqlSourceBuilderTest extends TestCase
{
    private SqlSourceBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new SqlSourceBuilder(new Configuration());
    }

    public function testParsesSimpleParameter(): void
    {
        $sql = 'SELECT * FROM users WHERE id = #{id}';

        $boundSql = $this->builder->parse($sql, ['id' => 1]);

        $this->assertSame('SELECT * FROM users WHERE id = ?', $boundSql->getSql());
        $this->assertCount(1, $boundSql->getParameterMappings());
        $this->assertSame('id', $boundSql->getParameterMappings()[0]->getProperty());
    }

    public function testParsesMultipleParameters(): void
    {
        $sql = 'SELECT * FROM users WHERE name = #{name} AND age > #{age}';

        $boundSql = $this->builder->parse($sql, ['name' => 'John', 'age' => 18]);

        $this->assertSame('SELECT * FROM users WHERE name = ? AND age > ?', $boundSql->getSql());
        $this->assertCount(2, $boundSql->getParameterMappings());
        $this->assertSame('name', $boundSql->getParameterMappings()[0]->getProperty());
        $this->assertSame('age', $boundSql->getParameterMappings()[1]->getProperty());
    }

    public function testParsesNestedProperty(): void
    {
        $sql = 'SELECT * FROM users WHERE id = #{user.id}';

        $boundSql = $this->builder->parse($sql, ['user' => ['id' => 1]]);

        $this->assertSame('SELECT * FROM users WHERE id = ?', $boundSql->getSql());
        $this->assertSame('user.id', $boundSql->getParameterMappings()[0]->getProperty());
    }

    public function testParsesParameterWithAttributes(): void
    {
        $sql = 'SELECT * FROM users WHERE id = #{id, phpType=int, sqlType=INTEGER}';

        $boundSql = $this->builder->parse($sql, ['id' => 1]);

        $this->assertSame('SELECT * FROM users WHERE id = ?', $boundSql->getSql());

        $mapping = $boundSql->getParameterMappings()[0];
        $this->assertSame('id', $mapping->getProperty());
        $this->assertSame('int', $mapping->getPhpType());
        $this->assertSame('INTEGER', $mapping->getSqlType());
    }

    public function testParsesParameterModeAttribute(): void
    {
        $sql = 'CALL get_user(#{id, mode=IN}, #{result, mode=OUT})';

        $boundSql = $this->builder->parse($sql, ['id' => 1]);

        $this->assertCount(2, $boundSql->getParameterMappings());
        $this->assertSame(ParameterMode::IN, $boundSql->getParameterMappings()[0]->getMode());
        $this->assertSame(ParameterMode::OUT, $boundSql->getParameterMappings()[1]->getMode());
    }

    public function testStringSubstitution(): void
    {
        $sql = 'SELECT * FROM ${tableName} WHERE id = #{id}';

        $boundSql = $this->builder->parse($sql, ['tableName' => 'users', 'id' => 1]);

        $this->assertSame('SELECT * FROM users WHERE id = ?', $boundSql->getSql());
        $this->assertCount(1, $boundSql->getParameterMappings());
    }

    public function testPreservesWhitespace(): void
    {
        $sql = "SELECT *\nFROM users\nWHERE id = #{id}";

        $boundSql = $this->builder->parse($sql, ['id' => 1]);

        $this->assertStringContainsString("\n", $boundSql->getSql());
    }

    public function testHandlesNoParameters(): void
    {
        $sql = 'SELECT * FROM users';

        $boundSql = $this->builder->parse($sql, null);

        $this->assertSame('SELECT * FROM users', $boundSql->getSql());
        $this->assertCount(0, $boundSql->getParameterMappings());
    }
}
