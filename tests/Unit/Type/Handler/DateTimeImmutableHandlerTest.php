<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Type\Handler;

use DateTime;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Stringable;
use Touta\Ogam\Type\Handler\DateTimeImmutableHandler;

final class DateTimeImmutableHandlerTest extends TestCase
{
    private DateTimeImmutableHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new DateTimeImmutableHandler();
    }

    public function testGetPhpType(): void
    {
        $this->assertSame(DateTimeImmutable::class, $this->handler->getPhpType());
    }

    public function testSetParameterWithDateTimeImmutable(): void
    {
        $date = new DateTimeImmutable('2024-06-15 10:30:00');

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '2024-06-15 10:30:00', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', $date, null);
    }

    public function testSetParameterWithDateTime(): void
    {
        $date = new DateTime('2024-06-15 10:30:00');

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '2024-06-15 10:30:00', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', $date, null);
    }

    public function testSetParameterWithString(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '2024-06-15 10:30:00', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', '2024-06-15 10:30:00', null);
    }

    public function testSetParameterWithNull(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', null, PDO::PARAM_NULL);

        $this->handler->setParameter($statement, ':test', null, null);
    }

    public function testSetParameterWithStringable(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return '2024-06-15 10:30:00';
            }
        };

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '2024-06-15 10:30:00', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', $stringable, null);
    }

    public function testSetParameterWithNonScalarReturnsEmptyString(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', ['array'], null);
    }

    public function testSetParameterWithIntegerIndex(): void
    {
        $date = new DateTimeImmutable('2024-06-15 10:30:00');

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(1, '2024-06-15 10:30:00', PDO::PARAM_STR);

        $this->handler->setParameter($statement, 1, $date, null);
    }

    public function testGetResultWithDateTimeString(): void
    {
        $row = ['created_at' => '2024-06-15 10:30:00'];
        $result = $this->handler->getResult($row, 'created_at');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2024-06-15 10:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testGetResultWithDateTimeImmutable(): void
    {
        $date = new DateTimeImmutable('2024-06-15 10:30:00');
        $row = ['created_at' => $date];
        $result = $this->handler->getResult($row, 'created_at');

        $this->assertSame($date, $result);
    }

    public function testGetResultWithDateTime(): void
    {
        $date = new DateTime('2024-06-15 10:30:00');
        $row = ['created_at' => $date];
        $result = $this->handler->getResult($row, 'created_at');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2024-06-15 10:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testGetResultWithNull(): void
    {
        $row = ['created_at' => null];
        $result = $this->handler->getResult($row, 'created_at');

        $this->assertNull($result);
    }

    public function testGetResultWithMissingColumn(): void
    {
        $row = ['other' => '2024-06-15'];
        $result = $this->handler->getResult($row, 'created_at');

        $this->assertNull($result);
    }

    public function testGetResultByIndex(): void
    {
        $row = ['col1' => 'value', 'created_at' => '2024-06-15 10:30:00'];
        $result = $this->handler->getResultByIndex($row, 1);

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2024-06-15 10:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testGetResultByIndexWithNull(): void
    {
        $row = ['col1' => 'value', 'created_at' => null];
        $result = $this->handler->getResultByIndex($row, 1);

        $this->assertNull($result);
    }

    public function testGetResultByIndexOutOfBounds(): void
    {
        $row = ['created_at' => '2024-06-15'];
        $result = $this->handler->getResultByIndex($row, 5);

        $this->assertNull($result);
    }

    public function testGetResultWithNaturalDateString(): void
    {
        $row = ['created_at' => 'June 15, 2024'];
        $result = $this->handler->getResult($row, 'created_at');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2024-06-15', $result->format('Y-m-d'));
    }

    public function testCustomFormat(): void
    {
        $handler = new DateTimeImmutableHandler('d/m/Y');
        $date = new DateTimeImmutable('2024-06-15');

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '15/06/2024', PDO::PARAM_STR);

        $handler->setParameter($statement, ':test', $date, null);
    }

    public function testCustomFormatGetResult(): void
    {
        $handler = new DateTimeImmutableHandler('d/m/Y');
        $row = ['created_at' => '15/06/2024'];
        $result = $handler->getResult($row, 'created_at');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2024-06-15', $result->format('Y-m-d'));
    }

    public function testGetResultWithStringable(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return '2024-06-15 10:30:00';
            }
        };

        $row = ['created_at' => $stringable];
        $result = $this->handler->getResult($row, 'created_at');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2024-06-15 10:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testImmutabilityOfResult(): void
    {
        $row = ['created_at' => '2024-06-15 10:30:00'];
        $result = $this->handler->getResult($row, 'created_at');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $modified = $result->modify('+1 day');

        $this->assertNotSame($result, $modified);
        $this->assertSame('2024-06-15', $result->format('Y-m-d'));
        $this->assertSame('2024-06-16', $modified->format('Y-m-d'));
    }
}
