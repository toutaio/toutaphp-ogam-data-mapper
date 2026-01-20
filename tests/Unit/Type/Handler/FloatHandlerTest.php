<?php

declare(strict_types=1);

namespace Touta\Ogam\Tests\Unit\Type\Handler;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Touta\Ogam\Type\Handler\FloatHandler;

final class FloatHandlerTest extends TestCase
{
    private FloatHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new FloatHandler();
    }

    public function testGetPhpType(): void
    {
        $this->assertSame('float', $this->handler->getPhpType());
    }

    public function testSetParameterWithFloat(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '3.14', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', 3.14, null);
    }

    public function testSetParameterWithZero(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '0', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', 0.0, null);
    }

    public function testSetParameterWithNegative(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '-99.99', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', -99.99, null);
    }

    public function testSetParameterWithNull(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', null, PDO::PARAM_NULL);

        $this->handler->setParameter($statement, ':test', null, null);
    }

    public function testSetParameterWithInteger(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '42', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', 42, null);
    }

    public function testSetParameterWithNumericString(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '123.456', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', '123.456', null);
    }

    public function testSetParameterWithNonNumericReturnsZero(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(':test', '0', PDO::PARAM_STR);

        $this->handler->setParameter($statement, ':test', 'not a number', null);
    }

    public function testSetParameterWithIntegerIndex(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(1, '9.99', PDO::PARAM_STR);

        $this->handler->setParameter($statement, 1, 9.99, null);
    }

    public function testGetResultWithFloat(): void
    {
        $row = ['price' => 19.99];
        $result = $this->handler->getResult($row, 'price');

        $this->assertSame(19.99, $result);
    }

    public function testGetResultWithZero(): void
    {
        $row = ['price' => 0.0];
        $result = $this->handler->getResult($row, 'price');

        $this->assertSame(0.0, $result);
    }

    public function testGetResultWithNegative(): void
    {
        $row = ['price' => -15.5];
        $result = $this->handler->getResult($row, 'price');

        $this->assertSame(-15.5, $result);
    }

    public function testGetResultWithInteger(): void
    {
        $row = ['price' => 100];
        $result = $this->handler->getResult($row, 'price');

        $this->assertSame(100.0, $result);
    }

    public function testGetResultWithNumericString(): void
    {
        $row = ['price' => '99.99'];
        $result = $this->handler->getResult($row, 'price');

        $this->assertSame(99.99, $result);
    }

    public function testGetResultWithNull(): void
    {
        $row = ['price' => null];
        $result = $this->handler->getResult($row, 'price');

        $this->assertNull($result);
    }

    public function testGetResultWithMissingColumn(): void
    {
        $row = ['other' => 10.0];
        $result = $this->handler->getResult($row, 'price');

        $this->assertNull($result);
    }

    public function testGetResultWithNonNumericReturnsZero(): void
    {
        $row = ['price' => 'not a number'];
        $result = $this->handler->getResult($row, 'price');

        $this->assertSame(0.0, $result);
    }

    public function testGetResultByIndex(): void
    {
        $row = ['col1' => 'value', 'price' => 42.5];
        $result = $this->handler->getResultByIndex($row, 1);

        $this->assertSame(42.5, $result);
    }

    public function testGetResultByIndexWithNull(): void
    {
        $row = ['col1' => 'value', 'price' => null];
        $result = $this->handler->getResultByIndex($row, 1);

        $this->assertNull($result);
    }

    public function testGetResultByIndexOutOfBounds(): void
    {
        $row = ['price' => 42.5];
        $result = $this->handler->getResultByIndex($row, 5);

        $this->assertNull($result);
    }

    public function testGetResultWithSmallFloat(): void
    {
        $row = ['price' => 0.0001];
        $result = $this->handler->getResult($row, 'price');

        $this->assertSame(0.0001, $result);
    }

    public function testGetResultWithScientificNotation(): void
    {
        $row = ['price' => '1.5e10'];
        $result = $this->handler->getResult($row, 'price');

        $this->assertSame(15000000000.0, $result);
    }
}
