<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Mapping;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Mapping\Association;
use Touta\Ogam\Mapping\ResultMapping;

final class AssociationTest extends TestCase
{
    public function testGetProperty(): void
    {
        $association = new Association('address', 'Address');

        $this->assertSame('address', $association->getProperty());
    }

    public function testGetJavaType(): void
    {
        $association = new Association('address', 'Address');

        $this->assertSame('Address', $association->getJavaType());
    }

    public function testGetResultMapIdDefault(): void
    {
        $association = new Association('address', 'Address');

        $this->assertNull($association->getResultMapId());
    }

    public function testGetResultMapIdCustom(): void
    {
        $association = new Association('address', 'Address', 'addressResultMap');

        $this->assertSame('addressResultMap', $association->getResultMapId());
    }

    public function testGetColumnPrefixDefault(): void
    {
        $association = new Association('address', 'Address');

        $this->assertSame('', $association->getColumnPrefix());
    }

    public function testGetColumnPrefixCustom(): void
    {
        $association = new Association('address', 'Address', columnPrefix: 'addr_');

        $this->assertSame('addr_', $association->getColumnPrefix());
    }

    public function testGetIdMappingsDefault(): void
    {
        $association = new Association('address', 'Address');

        $this->assertSame([], $association->getIdMappings());
    }

    public function testGetIdMappingsCustom(): void
    {
        $idMapping = new ResultMapping('id', 'address_id', 'int');
        $association = new Association('address', 'Address', idMappings: [$idMapping]);

        $this->assertSame([$idMapping], $association->getIdMappings());
    }

    public function testGetResultMappingsDefault(): void
    {
        $association = new Association('address', 'Address');

        $this->assertSame([], $association->getResultMappings());
    }

    public function testGetResultMappingsCustom(): void
    {
        $mapping = new ResultMapping('street', 'address_street', 'string');
        $association = new Association('address', 'Address', resultMappings: [$mapping]);

        $this->assertSame([$mapping], $association->getResultMappings());
    }

    public function testGetAllMappingsCombinesIdAndResult(): void
    {
        $idMapping = new ResultMapping('id', 'address_id', 'int');
        $resultMapping = new ResultMapping('street', 'address_street', 'string');

        $association = new Association(
            'address',
            'Address',
            idMappings: [$idMapping],
            resultMappings: [$resultMapping],
        );

        $allMappings = $association->getAllMappings();

        $this->assertCount(2, $allMappings);
        $this->assertSame($idMapping, $allMappings[0]);
        $this->assertSame($resultMapping, $allMappings[1]);
    }

    public function testUsesResultMapWithoutResultMapId(): void
    {
        $association = new Association('address', 'Address');

        $this->assertFalse($association->usesResultMap());
    }

    public function testUsesResultMapWithResultMapId(): void
    {
        $association = new Association('address', 'Address', 'addressResultMap');

        $this->assertTrue($association->usesResultMap());
    }

    public function testAllParametersCombined(): void
    {
        $idMapping = new ResultMapping('id', 'addr_id', 'int');
        $resultMapping = new ResultMapping('city', 'addr_city', 'string');

        $association = new Association(
            property: 'billingAddress',
            javaType: 'Address',
            resultMapId: 'billingAddressMap',
            columnPrefix: 'billing_',
            idMappings: [$idMapping],
            resultMappings: [$resultMapping],
        );

        $this->assertSame('billingAddress', $association->getProperty());
        $this->assertSame('Address', $association->getJavaType());
        $this->assertSame('billingAddressMap', $association->getResultMapId());
        $this->assertSame('billing_', $association->getColumnPrefix());
        $this->assertSame([$idMapping], $association->getIdMappings());
        $this->assertSame([$resultMapping], $association->getResultMappings());
        $this->assertTrue($association->usesResultMap());
    }
}
