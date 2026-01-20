<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Mapping;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Mapping\Association;
use Touta\Ogam\Mapping\Collection;
use Touta\Ogam\Mapping\Discriminator;
use Touta\Ogam\Mapping\ResultMap;
use Touta\Ogam\Mapping\ResultMapping;

final class ResultMapTest extends TestCase
{
    public function testGetId(): void
    {
        $resultMap = new ResultMap('userResultMap', 'User');

        $this->assertSame('userResultMap', $resultMap->getId());
    }

    public function testGetType(): void
    {
        $resultMap = new ResultMap('userResultMap', 'User');

        $this->assertSame('User', $resultMap->getType());
    }

    public function testGetIdMappingsDefault(): void
    {
        $resultMap = new ResultMap('userResultMap', 'User');

        $this->assertSame([], $resultMap->getIdMappings());
    }

    public function testGetIdMappingsCustom(): void
    {
        $idMapping = new ResultMapping('id', 'id', 'int');
        $resultMap = new ResultMap(
            'userResultMap',
            'User',
            idMappings: [$idMapping],
        );

        $this->assertSame([$idMapping], $resultMap->getIdMappings());
    }

    public function testGetResultMappingsDefault(): void
    {
        $resultMap = new ResultMap('userResultMap', 'User');

        $this->assertSame([], $resultMap->getResultMappings());
    }

    public function testGetResultMappingsCustom(): void
    {
        $mapping = new ResultMapping('name', 'name', 'string');
        $resultMap = new ResultMap(
            'userResultMap',
            'User',
            resultMappings: [$mapping],
        );

        $this->assertSame([$mapping], $resultMap->getResultMappings());
    }

    public function testGetAllMappingsCombinesIdAndResult(): void
    {
        $idMapping = new ResultMapping('id', 'id', 'int');
        $resultMapping = new ResultMapping('name', 'name', 'string');

        $resultMap = new ResultMap(
            'userResultMap',
            'User',
            idMappings: [$idMapping],
            resultMappings: [$resultMapping],
        );

        $allMappings = $resultMap->getAllMappings();

        $this->assertCount(2, $allMappings);
        $this->assertSame($idMapping, $allMappings[0]);
        $this->assertSame($resultMapping, $allMappings[1]);
    }

    public function testGetAssociationsDefault(): void
    {
        $resultMap = new ResultMap('userResultMap', 'User');

        $this->assertSame([], $resultMap->getAssociations());
    }

    public function testGetAssociationsCustom(): void
    {
        $association = new Association('address', 'Address');
        $resultMap = new ResultMap(
            'userResultMap',
            'User',
            associations: [$association],
        );

        $this->assertSame([$association], $resultMap->getAssociations());
    }

    public function testGetCollectionsDefault(): void
    {
        $resultMap = new ResultMap('userResultMap', 'User');

        $this->assertSame([], $resultMap->getCollections());
    }

    public function testGetCollectionsCustom(): void
    {
        $collection = new Collection('orders', 'Order');
        $resultMap = new ResultMap(
            'userResultMap',
            'User',
            collections: [$collection],
        );

        $this->assertSame([$collection], $resultMap->getCollections());
    }

    public function testGetDiscriminatorDefault(): void
    {
        $resultMap = new ResultMap('userResultMap', 'User');

        $this->assertNull($resultMap->getDiscriminator());
    }

    public function testGetDiscriminatorCustom(): void
    {
        $discriminator = new Discriminator('type', 'string', ['admin' => 'adminMap']);
        $resultMap = new ResultMap(
            'userResultMap',
            'User',
            discriminator: $discriminator,
        );

        $this->assertSame($discriminator, $resultMap->getDiscriminator());
    }

    public function testIsAutoMappingDefault(): void
    {
        $resultMap = new ResultMap('userResultMap', 'User');

        $this->assertTrue($resultMap->isAutoMapping());
    }

    public function testIsAutoMappingCustom(): void
    {
        $resultMap = new ResultMap('userResultMap', 'User', autoMapping: false);

        $this->assertFalse($resultMap->isAutoMapping());
    }

    public function testGetExtendsDefault(): void
    {
        $resultMap = new ResultMap('userResultMap', 'User');

        $this->assertNull($resultMap->getExtends());
    }

    public function testGetExtendsCustom(): void
    {
        $resultMap = new ResultMap('userResultMap', 'User', extends: 'baseResultMap');

        $this->assertSame('baseResultMap', $resultMap->getExtends());
    }

    public function testHasNestedMappingsWithoutNested(): void
    {
        $resultMap = new ResultMap('userResultMap', 'User');

        $this->assertFalse($resultMap->hasNestedMappings());
    }

    public function testHasNestedMappingsWithAssociation(): void
    {
        $resultMap = new ResultMap(
            'userResultMap',
            'User',
            associations: [new Association('address', 'Address')],
        );

        $this->assertTrue($resultMap->hasNestedMappings());
    }

    public function testHasNestedMappingsWithCollection(): void
    {
        $resultMap = new ResultMap(
            'userResultMap',
            'User',
            collections: [new Collection('orders', 'Order')],
        );

        $this->assertTrue($resultMap->hasNestedMappings());
    }

    public function testHasNestedMappingsWithBoth(): void
    {
        $resultMap = new ResultMap(
            'userResultMap',
            'User',
            associations: [new Association('address', 'Address')],
            collections: [new Collection('orders', 'Order')],
        );

        $this->assertTrue($resultMap->hasNestedMappings());
    }
}
