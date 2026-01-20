<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Mapping;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Mapping\ResultMapping;

final class ResultMappingTest extends TestCase
{
    public function testGetProperty(): void
    {
        $mapping = new ResultMapping('userId', 'id');

        $this->assertSame('userId', $mapping->getProperty());
    }

    public function testGetColumn(): void
    {
        $mapping = new ResultMapping('userId', 'id');

        $this->assertSame('id', $mapping->getColumn());
    }

    public function testGetPhpTypeDefault(): void
    {
        $mapping = new ResultMapping('userId', 'id');

        $this->assertNull($mapping->getPhpType());
    }

    public function testGetPhpTypeCustom(): void
    {
        $mapping = new ResultMapping('userId', 'id', 'int');

        $this->assertSame('int', $mapping->getPhpType());
    }

    public function testGetTypeHandlerDefault(): void
    {
        $mapping = new ResultMapping('userId', 'id');

        $this->assertNull($mapping->getTypeHandler());
    }

    public function testGetTypeHandlerCustom(): void
    {
        $mapping = new ResultMapping('userId', 'id', null, 'CustomTypeHandler');

        $this->assertSame('CustomTypeHandler', $mapping->getTypeHandler());
    }

    public function testAllParametersCombined(): void
    {
        $mapping = new ResultMapping(
            property: 'createdAt',
            column: 'created_at',
            phpType: \DateTime::class,
            typeHandler: 'DateTimeHandler',
        );

        $this->assertSame('createdAt', $mapping->getProperty());
        $this->assertSame('created_at', $mapping->getColumn());
        $this->assertSame(\DateTime::class, $mapping->getPhpType());
        $this->assertSame('DateTimeHandler', $mapping->getTypeHandler());
    }
}
