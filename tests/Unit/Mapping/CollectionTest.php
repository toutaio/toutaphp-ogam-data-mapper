<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Mapping;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Mapping\Collection;
use Touta\Ogam\Mapping\ResultMapping;

final class CollectionTest extends TestCase
{
    public function testGetProperty(): void
    {
        $collection = new Collection('orders', 'Order');

        $this->assertSame('orders', $collection->getProperty());
    }

    public function testGetOfType(): void
    {
        $collection = new Collection('orders', 'Order');

        $this->assertSame('Order', $collection->getOfType());
    }

    public function testGetResultMapIdDefault(): void
    {
        $collection = new Collection('orders', 'Order');

        $this->assertNull($collection->getResultMapId());
    }

    public function testGetResultMapIdCustom(): void
    {
        $collection = new Collection('orders', 'Order', 'orderResultMap');

        $this->assertSame('orderResultMap', $collection->getResultMapId());
    }

    public function testGetColumnPrefixDefault(): void
    {
        $collection = new Collection('orders', 'Order');

        $this->assertSame('', $collection->getColumnPrefix());
    }

    public function testGetColumnPrefixCustom(): void
    {
        $collection = new Collection('orders', 'Order', columnPrefix: 'order_');

        $this->assertSame('order_', $collection->getColumnPrefix());
    }

    public function testGetIdMappingsDefault(): void
    {
        $collection = new Collection('orders', 'Order');

        $this->assertSame([], $collection->getIdMappings());
    }

    public function testGetIdMappingsCustom(): void
    {
        $idMapping = new ResultMapping('id', 'order_id', 'int');
        $collection = new Collection('orders', 'Order', idMappings: [$idMapping]);

        $this->assertSame([$idMapping], $collection->getIdMappings());
    }

    public function testGetResultMappingsDefault(): void
    {
        $collection = new Collection('orders', 'Order');

        $this->assertSame([], $collection->getResultMappings());
    }

    public function testGetResultMappingsCustom(): void
    {
        $mapping = new ResultMapping('total', 'order_total', 'float');
        $collection = new Collection('orders', 'Order', resultMappings: [$mapping]);

        $this->assertSame([$mapping], $collection->getResultMappings());
    }

    public function testGetAllMappingsCombinesIdAndResult(): void
    {
        $idMapping = new ResultMapping('id', 'order_id', 'int');
        $resultMapping = new ResultMapping('total', 'order_total', 'float');

        $collection = new Collection(
            'orders',
            'Order',
            idMappings: [$idMapping],
            resultMappings: [$resultMapping],
        );

        $allMappings = $collection->getAllMappings();

        $this->assertCount(2, $allMappings);
        $this->assertSame($idMapping, $allMappings[0]);
        $this->assertSame($resultMapping, $allMappings[1]);
    }

    public function testUsesResultMapWithoutResultMapId(): void
    {
        $collection = new Collection('orders', 'Order');

        $this->assertFalse($collection->usesResultMap());
    }

    public function testUsesResultMapWithResultMapId(): void
    {
        $collection = new Collection('orders', 'Order', 'orderResultMap');

        $this->assertTrue($collection->usesResultMap());
    }

    public function testAllParametersCombined(): void
    {
        $idMapping = new ResultMapping('id', 'order_id', 'int');
        $resultMapping = new ResultMapping('status', 'order_status', 'string');

        $collection = new Collection(
            property: 'pendingOrders',
            ofType: 'Order',
            resultMapId: 'pendingOrderMap',
            columnPrefix: 'pending_',
            idMappings: [$idMapping],
            resultMappings: [$resultMapping],
        );

        $this->assertSame('pendingOrders', $collection->getProperty());
        $this->assertSame('Order', $collection->getOfType());
        $this->assertSame('pendingOrderMap', $collection->getResultMapId());
        $this->assertSame('pending_', $collection->getColumnPrefix());
        $this->assertSame([$idMapping], $collection->getIdMappings());
        $this->assertSame([$resultMapping], $collection->getResultMappings());
        $this->assertTrue($collection->usesResultMap());
    }
}
