<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Cache;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Cache\CacheKey;

enum TestCacheStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum TestCacheIntStatus: int
{
    case Active = 1;
    case Inactive = 0;
}

enum TestCacheUnitStatus
{
    case Active;
    case Inactive;
}

final class CacheKeyTest extends TestCase
{
    public function testGetStatementId(): void
    {
        $key = new CacheKey('UserMapper.findById', ['id' => 1]);

        $this->assertSame('UserMapper.findById', $key->getStatementId());
    }

    public function testGetParameters(): void
    {
        $params = ['id' => 1, 'name' => 'John'];
        $key = new CacheKey('UserMapper.find', $params);

        $this->assertSame($params, $key->getParameters());
    }

    public function testGetOffsetDefault(): void
    {
        $key = new CacheKey('UserMapper.findAll', []);

        $this->assertSame(0, $key->getOffset());
    }

    public function testGetLimitDefault(): void
    {
        $key = new CacheKey('UserMapper.findAll', []);

        $this->assertSame(0, $key->getLimit());
    }

    public function testGetOffsetCustom(): void
    {
        $key = new CacheKey('UserMapper.findAll', [], offset: 10);

        $this->assertSame(10, $key->getOffset());
    }

    public function testGetLimitCustom(): void
    {
        $key = new CacheKey('UserMapper.findAll', [], limit: 50);

        $this->assertSame(50, $key->getLimit());
    }

    public function testToString(): void
    {
        $key = new CacheKey('UserMapper.findById', ['id' => 1]);

        $this->assertStringStartsWith('ogam:', $key->toString());
        $this->assertStringStartsWith('ogam:', (string) $key);
    }

    public function testEqualsWithSameKey(): void
    {
        $key1 = new CacheKey('UserMapper.findById', ['id' => 1]);
        $key2 = new CacheKey('UserMapper.findById', ['id' => 1]);

        $this->assertTrue($key1->equals($key2));
    }

    public function testEqualsWithDifferentStatement(): void
    {
        $key1 = new CacheKey('UserMapper.findById', ['id' => 1]);
        $key2 = new CacheKey('UserMapper.findByName', ['id' => 1]);

        $this->assertFalse($key1->equals($key2));
    }

    public function testEqualsWithDifferentParameters(): void
    {
        $key1 = new CacheKey('UserMapper.findById', ['id' => 1]);
        $key2 = new CacheKey('UserMapper.findById', ['id' => 2]);

        $this->assertFalse($key1->equals($key2));
    }

    public function testEqualsWithDifferentOffset(): void
    {
        $key1 = new CacheKey('UserMapper.findAll', [], offset: 0);
        $key2 = new CacheKey('UserMapper.findAll', [], offset: 10);

        $this->assertFalse($key1->equals($key2));
    }

    public function testEqualsWithDifferentLimit(): void
    {
        $key1 = new CacheKey('UserMapper.findAll', [], limit: 10);
        $key2 = new CacheKey('UserMapper.findAll', [], limit: 20);

        $this->assertFalse($key1->equals($key2));
    }

    public function testHashConsistency(): void
    {
        $key1 = new CacheKey('UserMapper.findById', ['id' => 1]);
        $key2 = new CacheKey('UserMapper.findById', ['id' => 1]);

        $this->assertSame($key1->toString(), $key2->toString());
    }

    public function testDateTimeParameterSerialization(): void
    {
        $date = new DateTime('2024-06-15 10:30:00');
        $key1 = new CacheKey('UserMapper.findByDate', ['date' => $date]);
        $key2 = new CacheKey('UserMapper.findByDate', ['date' => new DateTime('2024-06-15 10:30:00')]);

        $this->assertTrue($key1->equals($key2));
    }

    public function testDateTimeImmutableParameterSerialization(): void
    {
        $date = new DateTimeImmutable('2024-06-15 10:30:00');
        $key1 = new CacheKey('UserMapper.findByDate', ['date' => $date]);
        $key2 = new CacheKey('UserMapper.findByDate', ['date' => new DateTimeImmutable('2024-06-15 10:30:00')]);

        $this->assertTrue($key1->equals($key2));
    }

    public function testDifferentDateTimesProduceDifferentKeys(): void
    {
        $key1 = new CacheKey('UserMapper.findByDate', ['date' => new DateTime('2024-06-15')]);
        $key2 = new CacheKey('UserMapper.findByDate', ['date' => new DateTime('2024-06-16')]);

        $this->assertFalse($key1->equals($key2));
    }

    public function testBackedEnumParameterSerialization(): void
    {
        $key1 = new CacheKey('UserMapper.findByStatus', ['status' => TestCacheStatus::Active]);
        $key2 = new CacheKey('UserMapper.findByStatus', ['status' => TestCacheStatus::Active]);

        $this->assertTrue($key1->equals($key2));
    }

    public function testDifferentBackedEnumsProduceDifferentKeys(): void
    {
        $key1 = new CacheKey('UserMapper.findByStatus', ['status' => TestCacheStatus::Active]);
        $key2 = new CacheKey('UserMapper.findByStatus', ['status' => TestCacheStatus::Inactive]);

        $this->assertFalse($key1->equals($key2));
    }

    public function testIntBackedEnumParameterSerialization(): void
    {
        $key1 = new CacheKey('UserMapper.findByStatus', ['status' => TestCacheIntStatus::Active]);
        $key2 = new CacheKey('UserMapper.findByStatus', ['status' => TestCacheIntStatus::Active]);

        $this->assertTrue($key1->equals($key2));
    }

    public function testUnitEnumParameterSerialization(): void
    {
        $key1 = new CacheKey('UserMapper.findByStatus', ['status' => TestCacheUnitStatus::Active]);
        $key2 = new CacheKey('UserMapper.findByStatus', ['status' => TestCacheUnitStatus::Active]);

        $this->assertTrue($key1->equals($key2));
    }

    public function testDifferentUnitEnumsProduceDifferentKeys(): void
    {
        $key1 = new CacheKey('UserMapper.findByStatus', ['status' => TestCacheUnitStatus::Active]);
        $key2 = new CacheKey('UserMapper.findByStatus', ['status' => TestCacheUnitStatus::Inactive]);

        $this->assertFalse($key1->equals($key2));
    }

    public function testObjectParameterSerialization(): void
    {
        $obj = new \stdClass();
        $key = new CacheKey('UserMapper.find', ['obj' => $obj]);

        $this->assertStringStartsWith('ogam:', $key->toString());
    }

    public function testArrayParameterSerialization(): void
    {
        $key1 = new CacheKey('UserMapper.findByIds', ['ids' => [1, 2, 3]]);
        $key2 = new CacheKey('UserMapper.findByIds', ['ids' => [1, 2, 3]]);

        $this->assertTrue($key1->equals($key2));
    }

    public function testDifferentArraysProduceDifferentKeys(): void
    {
        $key1 = new CacheKey('UserMapper.findByIds', ['ids' => [1, 2, 3]]);
        $key2 = new CacheKey('UserMapper.findByIds', ['ids' => [1, 2, 4]]);

        $this->assertFalse($key1->equals($key2));
    }

    public function testNestedArrayParameterSerialization(): void
    {
        $key1 = new CacheKey('UserMapper.find', ['data' => ['a' => ['b' => 1]]]);
        $key2 = new CacheKey('UserMapper.find', ['data' => ['a' => ['b' => 1]]]);

        $this->assertTrue($key1->equals($key2));
    }

    public function testEmptyParametersProduceSameKey(): void
    {
        $key1 = new CacheKey('UserMapper.findAll', []);
        $key2 = new CacheKey('UserMapper.findAll', []);

        $this->assertTrue($key1->equals($key2));
    }

    public function testScalarParameters(): void
    {
        $key1 = new CacheKey('UserMapper.find', ['str' => 'hello', 'int' => 42, 'float' => 3.14, 'bool' => true]);
        $key2 = new CacheKey('UserMapper.find', ['str' => 'hello', 'int' => 42, 'float' => 3.14, 'bool' => true]);

        $this->assertTrue($key1->equals($key2));
    }
}
