<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Mapping;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Mapping\ParameterMapping;
use Touta\Ogam\Mapping\ParameterMode;

final class ParameterMappingTest extends TestCase
{
    public function testGetProperty(): void
    {
        $mapping = new ParameterMapping('userId');

        $this->assertSame('userId', $mapping->getProperty());
    }

    public function testGetPhpTypeDefault(): void
    {
        $mapping = new ParameterMapping('userId');

        $this->assertNull($mapping->getPhpType());
    }

    public function testGetPhpTypeCustom(): void
    {
        $mapping = new ParameterMapping('userId', 'int');

        $this->assertSame('int', $mapping->getPhpType());
    }

    public function testGetSqlTypeDefault(): void
    {
        $mapping = new ParameterMapping('userId');

        $this->assertNull($mapping->getSqlType());
    }

    public function testGetSqlTypeCustom(): void
    {
        $mapping = new ParameterMapping('userId', null, 'INTEGER');

        $this->assertSame('INTEGER', $mapping->getSqlType());
    }

    public function testGetModeDefault(): void
    {
        $mapping = new ParameterMapping('userId');

        $this->assertSame(ParameterMode::IN, $mapping->getMode());
    }

    public function testGetModeCustom(): void
    {
        $mapping = new ParameterMapping('userId', mode: ParameterMode::OUT);

        $this->assertSame(ParameterMode::OUT, $mapping->getMode());
    }

    public function testGetTypeHandlerDefault(): void
    {
        $mapping = new ParameterMapping('userId');

        $this->assertNull($mapping->getTypeHandler());
    }

    public function testGetTypeHandlerCustom(): void
    {
        $mapping = new ParameterMapping('userId', typeHandler: 'CustomHandler');

        $this->assertSame('CustomHandler', $mapping->getTypeHandler());
    }

    public function testIsInputParameterWithIn(): void
    {
        $mapping = new ParameterMapping('userId', mode: ParameterMode::IN);

        $this->assertTrue($mapping->isInputParameter());
    }

    public function testIsInputParameterWithInout(): void
    {
        $mapping = new ParameterMapping('userId', mode: ParameterMode::INOUT);

        $this->assertTrue($mapping->isInputParameter());
    }

    public function testIsInputParameterWithOut(): void
    {
        $mapping = new ParameterMapping('userId', mode: ParameterMode::OUT);

        $this->assertFalse($mapping->isInputParameter());
    }

    public function testIsOutputParameterWithOut(): void
    {
        $mapping = new ParameterMapping('userId', mode: ParameterMode::OUT);

        $this->assertTrue($mapping->isOutputParameter());
    }

    public function testIsOutputParameterWithInout(): void
    {
        $mapping = new ParameterMapping('userId', mode: ParameterMode::INOUT);

        $this->assertTrue($mapping->isOutputParameter());
    }

    public function testIsOutputParameterWithIn(): void
    {
        $mapping = new ParameterMapping('userId', mode: ParameterMode::IN);

        $this->assertFalse($mapping->isOutputParameter());
    }

    public function testAllParametersCombined(): void
    {
        $mapping = new ParameterMapping(
            property: 'result',
            phpType: 'int',
            sqlType: 'INTEGER',
            mode: ParameterMode::INOUT,
            typeHandler: 'IntegerHandler',
        );

        $this->assertSame('result', $mapping->getProperty());
        $this->assertSame('int', $mapping->getPhpType());
        $this->assertSame('INTEGER', $mapping->getSqlType());
        $this->assertSame(ParameterMode::INOUT, $mapping->getMode());
        $this->assertSame('IntegerHandler', $mapping->getTypeHandler());
        $this->assertTrue($mapping->isInputParameter());
        $this->assertTrue($mapping->isOutputParameter());
    }

    public function testNestedPropertyName(): void
    {
        $mapping = new ParameterMapping('user.address.city');

        $this->assertSame('user.address.city', $mapping->getProperty());
    }
}
