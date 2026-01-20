<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Type\Handler;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Type\Handler\IntegerHandler;

final class IntegerHandlerTest extends TestCase
{
    private IntegerHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new IntegerHandler();
    }

    public function testGetPhpType(): void
    {
        $this->assertSame('int', $this->handler->getPhpType());
    }

    public function testSetParameterWithInteger(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', 42, PDO::PARAM_INT);

        $this->handler->setParameter($statement, ':test', 42, null);
    }

    public function testSetParameterWithZero(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', 0, PDO::PARAM_INT);

        $this->handler->setParameter($statement, ':test', 0, null);
    }

    public function testSetParameterWithNegative(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', -100, PDO::PARAM_INT);

        $this->handler->setParameter($statement, ':test', -100, null);
    }

    public function testSetParameterWithNull(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', null, PDO::PARAM_NULL);

        $this->handler->setParameter($statement, ':test', null, null);
    }

    public function testSetParameterWithNumericString(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', 123, PDO::PARAM_INT);

        $this->handler->setParameter($statement, ':test', '123', null);
    }

    public function testSetParameterWithFloat(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', 3, PDO::PARAM_INT);

        $this->handler->setParameter($statement, ':test', 3.14, null);
    }

    public function testSetParameterWithNonNumericReturnsZero(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', 0, PDO::PARAM_INT);

        $this->handler->setParameter($statement, ':test', 'not a number', null);
    }

    public function testSetParameterWithIntegerIndex(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(1, 42, PDO::PARAM_INT);

        $this->handler->setParameter($statement, 1, 42, null);
    }

    public function testGetResultWithInteger(): void
    {
        $row = ['count' => 100];
        $result = $this->handler->getResult($row, 'count');

        $this->assertSame(100, $result);
    }

    public function testGetResultWithZero(): void
    {
        $row = ['count' => 0];
        $result = $this->handler->getResult($row, 'count');

        $this->assertSame(0, $result);
    }

    public function testGetResultWithNegative(): void
    {
        $row = ['count' => -50];
        $result = $this->handler->getResult($row, 'count');

        $this->assertSame(-50, $result);
    }

    public function testGetResultWithNumericString(): void
    {
        $row = ['count' => '456'];
        $result = $this->handler->getResult($row, 'count');

        $this->assertSame(456, $result);
    }

    public function testGetResultWithFloat(): void
    {
        $row = ['count' => 7.89];
        $result = $this->handler->getResult($row, 'count');

        $this->assertSame(7, $result);
    }

    public function testGetResultWithNull(): void
    {
        $row = ['count' => null];
        $result = $this->handler->getResult($row, 'count');

        $this->assertNull($result);
    }

    public function testGetResultWithMissingColumn(): void
    {
        $row = ['other' => 10];
        $result = $this->handler->getResult($row, 'count');

        $this->assertNull($result);
    }

    public function testGetResultWithNonNumericReturnsZero(): void
    {
        $row = ['count' => 'not a number'];
        $result = $this->handler->getResult($row, 'count');

        $this->assertSame(0, $result);
    }

    public function testGetResultByIndex(): void
    {
        $row = ['col1' => 'value', 'count' => 42];
        $result = $this->handler->getResultByIndex($row, 1);

        $this->assertSame(42, $result);
    }

    public function testGetResultByIndexWithNull(): void
    {
        $row = ['col1' => 'value', 'count' => null];
        $result = $this->handler->getResultByIndex($row, 1);

        $this->assertNull($result);
    }

    public function testGetResultByIndexOutOfBounds(): void
    {
        $row = ['count' => 42];
        $result = $this->handler->getResultByIndex($row, 5);

        $this->assertNull($result);
    }

    public function testGetResultWithLargeInteger(): void
    {
        $row = ['count' => PHP_INT_MAX];
        $result = $this->handler->getResult($row, 'count');

        $this->assertSame(PHP_INT_MAX, $result);
    }

    public function testGetResultWithLargeNegativeInteger(): void
    {
        $row = ['count' => PHP_INT_MIN];
        $result = $this->handler->getResult($row, 'count');

        $this->assertSame(PHP_INT_MIN, $result);
    }
}
