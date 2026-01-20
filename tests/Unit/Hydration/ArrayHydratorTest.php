<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Hydration;

use PHPUnit\Framework\TestCase;
use Touta\Ogam\Hydration\ArrayHydrator;
use Touta\Ogam\Mapping\ResultMap;
use Touta\Ogam\Mapping\ResultMapping;
use Touta\Ogam\Type\TypeHandlerRegistry;

final class ArrayHydratorTest extends TestCase
{
    private ArrayHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new ArrayHydrator(new TypeHandlerRegistry());
    }

    public function testHydrateWithoutResultMapReturnsRowAsIs(): void
    {
        $row = ['id' => 1, 'name' => 'John'];
        $result = $this->hydrator->hydrate($row, null, null);

        $this->assertSame($row, $result);
    }

    public function testHydrateWithUnderscoreToCamelCase(): void
    {
        $hydrator = new ArrayHydrator(new TypeHandlerRegistry(), mapUnderscoreToCamelCase: true);

        $row = ['user_name' => 'John', 'created_at' => '2024-01-01'];
        $result = $hydrator->hydrate($row, null, null);

        $this->assertSame(['userName' => 'John', 'createdAt' => '2024-01-01'], $result);
    }

    public function testHydrateWithResultMap(): void
    {
        $resultMap = new ResultMap(
            id: 'userMap',
            type: 'array',
            resultMappings: [
                new ResultMapping('userId', 'id', 'int'),
                new ResultMapping('fullName', 'name', 'string'),
            ],
            autoMapping: false,
        );

        $row = ['id' => '1', 'name' => 'John', 'extra' => 'ignored'];
        $result = $this->hydrator->hydrate($row, $resultMap, null);

        $this->assertSame(['userId' => 1, 'fullName' => 'John'], $result);
    }

    public function testHydrateWithResultMapAndAutoMapping(): void
    {
        $resultMap = new ResultMap(
            id: 'userMap',
            type: 'array',
            resultMappings: [
                new ResultMapping('userId', 'id', 'int'),
            ],
            autoMapping: true,
        );

        $row = ['id' => '1', 'name' => 'John'];
        $result = $this->hydrator->hydrate($row, $resultMap, null);

        $this->assertSame(['userId' => 1, 'name' => 'John'], $result);
    }

    public function testHydrateWithResultMapAndAutoMappingWithCamelCase(): void
    {
        $hydrator = new ArrayHydrator(new TypeHandlerRegistry(), mapUnderscoreToCamelCase: true);

        $resultMap = new ResultMap(
            id: 'userMap',
            type: 'array',
            resultMappings: [
                new ResultMapping('userId', 'id', 'int'),
            ],
            autoMapping: true,
        );

        $row = ['id' => '1', 'user_name' => 'John'];
        $result = $hydrator->hydrate($row, $resultMap, null);

        $this->assertSame(['userId' => 1, 'userName' => 'John'], $result);
    }

    public function testHydrateWithMissingColumnSkipsMapping(): void
    {
        $resultMap = new ResultMap(
            id: 'userMap',
            type: 'array',
            resultMappings: [
                new ResultMapping('userId', 'id', 'int'),
                new ResultMapping('email', 'email', 'string'),
            ],
            autoMapping: false,
        );

        $row = ['id' => '1'];
        $result = $this->hydrator->hydrate($row, $resultMap, null);

        $this->assertSame(['userId' => 1], $result);
    }

    public function testHydrateWithNullValuePreservesNull(): void
    {
        $resultMap = new ResultMap(
            id: 'userMap',
            type: 'array',
            resultMappings: [
                new ResultMapping('name', 'name', 'string'),
            ],
            autoMapping: false,
        );

        $row = ['name' => null];
        $result = $this->hydrator->hydrate($row, $resultMap, null);

        $this->assertSame(['name' => null], $result);
    }

    public function testHydrateWithoutPhpTypeReturnsRawValue(): void
    {
        $resultMap = new ResultMap(
            id: 'userMap',
            type: 'array',
            resultMappings: [
                new ResultMapping('name', 'name'),
            ],
            autoMapping: false,
        );

        $row = ['name' => 'John'];
        $result = $this->hydrator->hydrate($row, $resultMap, null);

        $this->assertSame(['name' => 'John'], $result);
    }

    public function testHydrateAllWithMultipleRows(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $results = $this->hydrator->hydrateAll($rows, null, null);

        $this->assertCount(2, $results);
        $this->assertSame($rows, $results);
    }

    public function testHydrateAllWithEmptyRows(): void
    {
        $results = $this->hydrator->hydrateAll([], null, null);

        $this->assertSame([], $results);
    }

    public function testHydrateAutoMappingDoesNotOverrideExplicit(): void
    {
        $resultMap = new ResultMap(
            id: 'userMap',
            type: 'array',
            resultMappings: [
                new ResultMapping('customName', 'name', 'string'),
            ],
            autoMapping: true,
        );

        $row = ['name' => 'John'];
        $result = $this->hydrator->hydrate($row, $resultMap, null);

        $this->assertSame(['customName' => 'John'], $result);
        $this->assertArrayNotHasKey('name', $result);
    }
}
