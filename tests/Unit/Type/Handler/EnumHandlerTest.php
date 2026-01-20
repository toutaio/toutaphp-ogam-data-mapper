<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Type\Handler;

use BackedEnum;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Type\Handler\EnumHandler;
use UnitEnum;
use ValueError;

enum StringStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
}

enum IntStatus: int
{
    case Active = 1;
    case Inactive = 0;
    case Pending = 2;
}

enum UnitStatus
{
    case Active;
    case Inactive;
    case Pending;
}

final class EnumHandlerTest extends TestCase
{
    public function testGetPhpTypeReturnsEnumClass(): void
    {
        $handler = new EnumHandler(StringStatus::class);

        $this->assertSame(StringStatus::class, $handler->getPhpType());
    }

    public function testConstructorThrowsForNonEnum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "stdClass" is not an enum');

        new EnumHandler(\stdClass::class);
    }

    public function testSetParameterWithStringBackedEnum(): void
    {
        $handler = new EnumHandler(StringStatus::class);

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', 'active', PDO::PARAM_STR);

        $handler->setParameter($statement, ':test', StringStatus::Active, null);
    }

    public function testSetParameterWithIntBackedEnum(): void
    {
        $handler = new EnumHandler(IntStatus::class);

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', 1, PDO::PARAM_INT);

        $handler->setParameter($statement, ':test', IntStatus::Active, null);
    }

    public function testSetParameterWithUnitEnum(): void
    {
        $handler = new EnumHandler(UnitStatus::class);

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', 'Active', PDO::PARAM_STR);

        $handler->setParameter($statement, ':test', UnitStatus::Active, null);
    }

    public function testSetParameterWithNull(): void
    {
        $handler = new EnumHandler(StringStatus::class);

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', null, PDO::PARAM_NULL);

        $handler->setParameter($statement, ':test', null, null);
    }

    public function testSetParameterWithRawStringValue(): void
    {
        $handler = new EnumHandler(StringStatus::class);

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', 'active', PDO::PARAM_STR);

        $handler->setParameter($statement, ':test', 'active', null);
    }

    public function testSetParameterWithRawIntValue(): void
    {
        $handler = new EnumHandler(IntStatus::class);

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', 1, PDO::PARAM_INT);

        $handler->setParameter($statement, ':test', 1, null);
    }

    public function testSetParameterWithIntegerIndex(): void
    {
        $handler = new EnumHandler(StringStatus::class);

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(1, 'active', PDO::PARAM_STR);

        $handler->setParameter($statement, 1, StringStatus::Active, null);
    }

    public function testGetResultWithStringBackedEnum(): void
    {
        $handler = new EnumHandler(StringStatus::class);
        $row = ['status' => 'active'];
        $result = $handler->getResult($row, 'status');

        $this->assertSame(StringStatus::Active, $result);
    }

    public function testGetResultWithIntBackedEnum(): void
    {
        $handler = new EnumHandler(IntStatus::class);
        $row = ['status' => 1];
        $result = $handler->getResult($row, 'status');

        $this->assertSame(IntStatus::Active, $result);
    }

    public function testGetResultWithUnitEnum(): void
    {
        $handler = new EnumHandler(UnitStatus::class);
        $row = ['status' => 'Active'];
        $result = $handler->getResult($row, 'status');

        $this->assertSame(UnitStatus::Active, $result);
    }

    public function testGetResultWithNull(): void
    {
        $handler = new EnumHandler(StringStatus::class);
        $row = ['status' => null];
        $result = $handler->getResult($row, 'status');

        $this->assertNull($result);
    }

    public function testGetResultWithMissingColumn(): void
    {
        $handler = new EnumHandler(StringStatus::class);
        $row = ['other' => 'value'];
        $result = $handler->getResult($row, 'status');

        $this->assertNull($result);
    }

    public function testGetResultWithAlreadyEnumInstance(): void
    {
        $handler = new EnumHandler(StringStatus::class);
        $row = ['status' => StringStatus::Active];
        $result = $handler->getResult($row, 'status');

        $this->assertSame(StringStatus::Active, $result);
    }

    public function testGetResultThrowsForInvalidBackedEnumValue(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Value "unknown" is not a valid backing value for enum');

        $handler = new EnumHandler(StringStatus::class);
        $row = ['status' => 'unknown'];
        $handler->getResult($row, 'status');
    }

    public function testGetResultThrowsForInvalidUnitEnumValue(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Value "Unknown" is not a valid case name for enum');

        $handler = new EnumHandler(UnitStatus::class);
        $row = ['status' => 'Unknown'];
        $handler->getResult($row, 'status');
    }

    public function testGetResultThrowsForInvalidTypeForBackedEnum(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Value of type "array" is not valid for backed enum');

        $handler = new EnumHandler(StringStatus::class);
        $row = ['status' => ['invalid']];
        $handler->getResult($row, 'status');
    }

    public function testGetResultByIndex(): void
    {
        $handler = new EnumHandler(StringStatus::class);
        $row = ['col1' => 'value', 'status' => 'pending'];
        $result = $handler->getResultByIndex($row, 1);

        $this->assertSame(StringStatus::Pending, $result);
    }

    public function testGetResultByIndexWithNull(): void
    {
        $handler = new EnumHandler(StringStatus::class);
        $row = ['col1' => 'value', 'status' => null];
        $result = $handler->getResultByIndex($row, 1);

        $this->assertNull($result);
    }

    public function testGetResultByIndexOutOfBounds(): void
    {
        $handler = new EnumHandler(StringStatus::class);
        $row = ['status' => 'active'];
        $result = $handler->getResultByIndex($row, 5);

        $this->assertNull($result);
    }

    public function testGetResultWithAllStringStatusCases(): void
    {
        $handler = new EnumHandler(StringStatus::class);

        foreach (StringStatus::cases() as $case) {
            $row = ['status' => $case->value];
            $result = $handler->getResult($row, 'status');
            $this->assertSame($case, $result);
        }
    }

    public function testGetResultWithAllIntStatusCases(): void
    {
        $handler = new EnumHandler(IntStatus::class);

        foreach (IntStatus::cases() as $case) {
            $row = ['status' => $case->value];
            $result = $handler->getResult($row, 'status');
            $this->assertSame($case, $result);
        }
    }

    public function testGetResultWithAllUnitStatusCases(): void
    {
        $handler = new EnumHandler(UnitStatus::class);

        foreach (UnitStatus::cases() as $case) {
            $row = ['status' => $case->name];
            $result = $handler->getResult($row, 'status');
            $this->assertSame($case, $result);
        }
    }
}
