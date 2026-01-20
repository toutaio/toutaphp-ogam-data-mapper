<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Hydration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Touta\Ogam\Hydration\ObjectHydrator;
use Touta\Ogam\Mapping\ResultMap;
use Touta\Ogam\Mapping\ResultMapping;
use Touta\Ogam\Type\TypeHandlerRegistry;

class TestUserEntity
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public ?string $email = null,
    ) {}
}

class TestMutableEntity
{
    public int $id;
    public string $name;

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

class TestEmptyConstructorEntity
{
    public int $id;
    public string $name;
}

class TestEntityWithReadonly
{
    public string $name = '';
    private int $privateId = 0;

    public function getId(): int
    {
        return $this->privateId;
    }
}

final class ObjectHydratorTest extends TestCase
{
    private ObjectHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new ObjectHydrator(new TypeHandlerRegistry());
    }

    public function testHydrateWithoutTypeReturnsRow(): void
    {
        $row = ['id' => 1, 'name' => 'John'];
        $result = $this->hydrator->hydrate($row, null, null);

        $this->assertSame($row, $result);
    }

    public function testHydrateWithInvalidClassReturnsRow(): void
    {
        $row = ['id' => 1, 'name' => 'John'];
        $result = $this->hydrator->hydrate($row, null, 'NonExistentClass');

        $this->assertSame($row, $result);
    }

    public function testHydrateViaConstructor(): void
    {
        $row = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
        $result = $this->hydrator->hydrate($row, null, TestUserEntity::class);

        $this->assertInstanceOf(TestUserEntity::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('John', $result->name);
        $this->assertSame('john@example.com', $result->email);
    }

    public function testHydrateViaConstructorWithDefaultValue(): void
    {
        $row = ['id' => 1, 'name' => 'John'];
        $result = $this->hydrator->hydrate($row, null, TestUserEntity::class);

        $this->assertInstanceOf(TestUserEntity::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('John', $result->name);
        $this->assertNull($result->email);
    }

    public function testHydrateViaSetters(): void
    {
        $row = ['id' => 1, 'name' => 'John'];
        $result = $this->hydrator->hydrate($row, null, TestMutableEntity::class);

        $this->assertInstanceOf(TestMutableEntity::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('John', $result->name);
    }

    public function testHydrateViaDirectPropertyAccess(): void
    {
        $row = ['id' => 1, 'name' => 'John'];
        $result = $this->hydrator->hydrate($row, null, TestEmptyConstructorEntity::class);

        $this->assertInstanceOf(TestEmptyConstructorEntity::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('John', $result->name);
    }

    public function testHydrateWithResultMap(): void
    {
        $resultMap = new ResultMap(
            id: 'userMap',
            type: TestUserEntity::class,
            resultMappings: [
                new ResultMapping('id', 'user_id', 'int'),
                new ResultMapping('name', 'user_name', 'string'),
            ],
            autoMapping: false,
        );

        $row = ['user_id' => '1', 'user_name' => 'John'];
        $result = $this->hydrator->hydrate($row, $resultMap, TestUserEntity::class);

        $this->assertInstanceOf(TestUserEntity::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('John', $result->name);
    }

    public function testHydrateWithResultMapAndAutoMapping(): void
    {
        $resultMap = new ResultMap(
            id: 'userMap',
            type: TestUserEntity::class,
            resultMappings: [
                new ResultMapping('id', 'user_id', 'int'),
            ],
            autoMapping: true,
        );

        $row = ['user_id' => '1', 'name' => 'John'];
        $result = $this->hydrator->hydrate($row, $resultMap, TestUserEntity::class);

        $this->assertInstanceOf(TestUserEntity::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('John', $result->name);
    }

    public function testHydrateWithUnderscoreToCamelCase(): void
    {
        $hydrator = new ObjectHydrator(new TypeHandlerRegistry(), mapUnderscoreToCamelCase: true);

        $row = ['id' => 1, 'user_name' => 'John'];
        $result = $hydrator->hydrate($row, null, TestMutableEntity::class);

        $this->assertInstanceOf(TestMutableEntity::class, $result);
        $this->assertSame(1, $result->id);
    }

    public function testHydrateAllWithMultipleRows(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $results = $this->hydrator->hydrateAll($rows, null, TestUserEntity::class);

        $this->assertCount(2, $results);
        $this->assertInstanceOf(TestUserEntity::class, $results[0]);
        $this->assertInstanceOf(TestUserEntity::class, $results[1]);
        $this->assertSame(1, $results[0]->id);
        $this->assertSame(2, $results[1]->id);
    }

    public function testHydrateAllWithEmptyRows(): void
    {
        $results = $this->hydrator->hydrateAll([], null, TestUserEntity::class);

        $this->assertSame([], $results);
    }

    public function testHydrateThrowsForMissingRequiredConstructorParam(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing required constructor parameter');

        $row = ['id' => 1];
        $this->hydrator->hydrate($row, null, TestUserEntity::class);
    }

    public function testHydrateWithNullableConstructorParam(): void
    {
        $row = ['id' => 1, 'name' => 'John'];
        $result = $this->hydrator->hydrate($row, null, TestUserEntity::class);

        $this->assertInstanceOf(TestUserEntity::class, $result);
        $this->assertNull($result->email);
    }

    public function testHydrateWithResultMapTypeOverridesResultType(): void
    {
        $resultMap = new ResultMap(
            id: 'userMap',
            type: TestUserEntity::class,
            resultMappings: [],
            autoMapping: true,
        );

        $row = ['id' => 1, 'name' => 'John'];
        $result = $this->hydrator->hydrate($row, $resultMap, null);

        $this->assertInstanceOf(TestUserEntity::class, $result);
    }

    public function testHydrateCanSetPrivatePropertiesViaReflection(): void
    {
        $row = ['privateId' => 99, 'name' => 'John'];
        $result = $this->hydrator->hydrate($row, null, TestEntityWithReadonly::class);

        $this->assertInstanceOf(TestEntityWithReadonly::class, $result);
        $this->assertSame(99, $result->getId()); // Private property is set via reflection
        $this->assertSame('John', $result->name);
    }

    public function testHydrateWithMissingColumnInResultMap(): void
    {
        $resultMap = new ResultMap(
            id: 'userMap',
            type: TestUserEntity::class,
            resultMappings: [
                new ResultMapping('id', 'id', 'int'),
                new ResultMapping('name', 'name', 'string'),
                new ResultMapping('email', 'email', 'string'),
            ],
            autoMapping: false,
        );

        $row = ['id' => '1', 'name' => 'John'];
        $result = $this->hydrator->hydrate($row, $resultMap, TestUserEntity::class);

        $this->assertInstanceOf(TestUserEntity::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('John', $result->name);
        $this->assertNull($result->email);
    }

    public function testHydrateWithNullValueInMapping(): void
    {
        $resultMap = new ResultMap(
            id: 'userMap',
            type: TestUserEntity::class,
            resultMappings: [
                new ResultMapping('id', 'id', 'int'),
                new ResultMapping('name', 'name', 'string'),
                new ResultMapping('email', 'email', 'string'),
            ],
            autoMapping: false,
        );

        $row = ['id' => '1', 'name' => 'John', 'email' => null];
        $result = $this->hydrator->hydrate($row, $resultMap, TestUserEntity::class);

        $this->assertInstanceOf(TestUserEntity::class, $result);
        $this->assertNull($result->email);
    }
}
