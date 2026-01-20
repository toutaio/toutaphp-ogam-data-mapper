<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Hydration;

use DateTime;
use PHPUnit\Framework\TestCase;
use Stringable;
use Touta\Ogam\Hydration\ScalarHydrator;
use Touta\Ogam\Type\TypeHandlerRegistry;

final class ScalarHydratorTest extends TestCase
{
    private ScalarHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new ScalarHydrator(new TypeHandlerRegistry());
    }

    public function testHydrateWithEmptyRow(): void
    {
        $result = $this->hydrator->hydrate([], null, null);

        $this->assertNull($result);
    }

    public function testHydrateWithNullValue(): void
    {
        $row = ['count' => null];
        $result = $this->hydrator->hydrate($row, null, null);

        $this->assertNull($result);
    }

    public function testHydrateWithNoResultType(): void
    {
        $row = ['count' => 42];
        $result = $this->hydrator->hydrate($row, null, null);

        $this->assertSame(42, $result);
    }

    public function testHydrateWithIntegerType(): void
    {
        $row = ['count' => '42'];
        $result = $this->hydrator->hydrate($row, null, 'int');

        $this->assertSame(42, $result);
    }

    public function testHydrateWithIntegerTypeFull(): void
    {
        $row = ['count' => '123'];
        $result = $this->hydrator->hydrate($row, null, 'integer');

        $this->assertSame(123, $result);
    }

    public function testHydrateWithFloatType(): void
    {
        $row = ['value' => '3.14'];
        $result = $this->hydrator->hydrate($row, null, 'float');

        $this->assertSame(3.14, $result);
    }

    public function testHydrateWithDoubleType(): void
    {
        $row = ['value' => '2.71'];
        $result = $this->hydrator->hydrate($row, null, 'double');

        $this->assertSame(2.71, $result);
    }

    public function testHydrateWithStringType(): void
    {
        $row = ['name' => 123];
        $result = $this->hydrator->hydrate($row, null, 'string');

        $this->assertSame('123', $result);
    }

    public function testHydrateWithBoolType(): void
    {
        $row = ['active' => 1];
        $result = $this->hydrator->hydrate($row, null, 'bool');

        $this->assertTrue($result);
    }

    public function testHydrateWithBooleanType(): void
    {
        $row = ['active' => 0];
        $result = $this->hydrator->hydrate($row, null, 'boolean');

        $this->assertFalse($result);
    }

    public function testHydrateWithNonNumericIntegerReturnsZero(): void
    {
        $row = ['count' => 'abc'];
        $result = $this->hydrator->hydrate($row, null, 'int');

        $this->assertSame(0, $result);
    }

    public function testHydrateWithNonNumericFloatReturnsZero(): void
    {
        $row = ['value' => 'abc'];
        $result = $this->hydrator->hydrate($row, null, 'float');

        $this->assertSame(0.0, $result);
    }

    public function testHydrateWithBooleanValueToInt(): void
    {
        $row = ['value' => true];
        $result = $this->hydrator->hydrate($row, null, 'int');

        $this->assertSame(1, $result);
    }

    public function testHydrateWithBooleanValueToFloat(): void
    {
        $row = ['value' => true];
        $result = $this->hydrator->hydrate($row, null, 'float');

        $this->assertSame(1.0, $result);
    }

    public function testHydrateWithStringableToString(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringable';
            }
        };

        $row = ['value' => $stringable];
        $result = $this->hydrator->hydrate($row, null, 'string');

        $this->assertSame('stringable', $result);
    }

    public function testHydrateWithNonScalarToStringReturnsEmpty(): void
    {
        $row = ['value' => ['array']];
        $result = $this->hydrator->hydrate($row, null, 'string');

        $this->assertSame('', $result);
    }

    public function testHydrateWithCustomTypeHandler(): void
    {
        $row = ['created_at' => '2024-06-15 10:30:00'];
        $result = $this->hydrator->hydrate($row, null, DateTime::class);

        $this->assertInstanceOf(DateTime::class, $result);
    }

    public function testHydrateAllWithMultipleRows(): void
    {
        $rows = [
            ['count' => '1'],
            ['count' => '2'],
            ['count' => '3'],
        ];

        $results = $this->hydrator->hydrateAll($rows, null, 'int');

        $this->assertCount(3, $results);
        $this->assertSame([1, 2, 3], $results);
    }

    public function testHydrateAllWithEmptyRows(): void
    {
        $results = $this->hydrator->hydrateAll([], null, 'int');

        $this->assertSame([], $results);
    }

    public function testHydrateReturnsFirstColumnValue(): void
    {
        $row = ['first' => 'a', 'second' => 'b', 'third' => 'c'];
        $result = $this->hydrator->hydrate($row, null, null);

        $this->assertSame('a', $result);
    }
}
