<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Mapping;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Mapping\BoundSql;
use Touta\Ogam\Mapping\ParameterMapping;

final class BoundSqlTest extends TestCase
{
    public function testGetSql(): void
    {
        $boundSql = new BoundSql('SELECT * FROM users WHERE id = ?');

        $this->assertSame('SELECT * FROM users WHERE id = ?', $boundSql->getSql());
    }

    public function testGetParameterMappingsDefault(): void
    {
        $boundSql = new BoundSql('SELECT * FROM users');

        $this->assertSame([], $boundSql->getParameterMappings());
    }

    public function testGetParameterMappingsCustom(): void
    {
        $mapping = new ParameterMapping('id', 'int');
        $boundSql = new BoundSql('SELECT * FROM users WHERE id = ?', [$mapping]);

        $this->assertSame([$mapping], $boundSql->getParameterMappings());
    }

    public function testGetAdditionalParametersDefault(): void
    {
        $boundSql = new BoundSql('SELECT * FROM users');

        $this->assertSame([], $boundSql->getAdditionalParameters());
    }

    public function testGetAdditionalParametersCustom(): void
    {
        $boundSql = new BoundSql(
            'SELECT * FROM users',
            [],
            ['__frch_item_0' => 1, '__frch_item_1' => 2],
        );

        $this->assertSame(['__frch_item_0' => 1, '__frch_item_1' => 2], $boundSql->getAdditionalParameters());
    }

    public function testHasAdditionalParameterTrue(): void
    {
        $boundSql = new BoundSql(
            'SELECT * FROM users',
            [],
            ['__frch_item_0' => 1],
        );

        $this->assertTrue($boundSql->hasAdditionalParameter('__frch_item_0'));
    }

    public function testHasAdditionalParameterFalse(): void
    {
        $boundSql = new BoundSql('SELECT * FROM users');

        $this->assertFalse($boundSql->hasAdditionalParameter('__frch_item_0'));
    }

    public function testGetAdditionalParameterExisting(): void
    {
        $boundSql = new BoundSql(
            'SELECT * FROM users',
            [],
            ['__frch_item_0' => 42],
        );

        $this->assertSame(42, $boundSql->getAdditionalParameter('__frch_item_0'));
    }

    public function testGetAdditionalParameterNonExisting(): void
    {
        $boundSql = new BoundSql('SELECT * FROM users');

        $this->assertNull($boundSql->getAdditionalParameter('__frch_item_0'));
    }

    public function testSetAdditionalParameter(): void
    {
        $boundSql = new BoundSql('SELECT * FROM users');
        $boundSql->setAdditionalParameter('__frch_item_0', 'value');

        $this->assertTrue($boundSql->hasAdditionalParameter('__frch_item_0'));
        $this->assertSame('value', $boundSql->getAdditionalParameter('__frch_item_0'));
    }

    public function testSetAdditionalParameterOverwrites(): void
    {
        $boundSql = new BoundSql(
            'SELECT * FROM users',
            [],
            ['__frch_item_0' => 'old'],
        );

        $boundSql->setAdditionalParameter('__frch_item_0', 'new');

        $this->assertSame('new', $boundSql->getAdditionalParameter('__frch_item_0'));
    }

    public function testSetAdditionalParameterWithNull(): void
    {
        $boundSql = new BoundSql('SELECT * FROM users');
        $boundSql->setAdditionalParameter('nullable', null);

        $this->assertTrue($boundSql->hasAdditionalParameter('nullable'));
        $this->assertNull($boundSql->getAdditionalParameter('nullable'));
    }

    public function testComplexBoundSql(): void
    {
        $mappings = [
            new ParameterMapping('name', 'string'),
            new ParameterMapping('status', 'string'),
        ];

        $boundSql = new BoundSql(
            'SELECT * FROM users WHERE name = ? AND status IN (?, ?)',
            $mappings,
            ['__frch_status_0' => 'active', '__frch_status_1' => 'pending'],
        );

        $this->assertSame(
            'SELECT * FROM users WHERE name = ? AND status IN (?, ?)',
            $boundSql->getSql(),
        );
        $this->assertCount(2, $boundSql->getParameterMappings());
        $this->assertSame('active', $boundSql->getAdditionalParameter('__frch_status_0'));
        $this->assertSame('pending', $boundSql->getAdditionalParameter('__frch_status_1'));
    }
}
